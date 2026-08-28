import { connectEcho } from './echo-adapter.js';

export function createNotificationClient({ apiBaseUrl = '/api/notifications', echo = null,
    fetch: requestFetch = (...args) => fetch(...args), headers = {}, limit = 20,
    navigate = url => window.location.assign(url) } = {}) {
    const base = apiBaseUrl.replace(/\/$/, '');
    const listeners = new Map();
    const commands = new Map();
    const seen = new Set();
    let state = { items: [], unreadCount: 0, nextCursor: null };
    let refresh = null, dirty = false, destroyed = false, disconnect = null, channelName = null;
    let mutations = Promise.resolve();
    const emit = (event, value) => {
        for (const listener of listeners.get(event) ?? []) listener(value);
    };
    const snapshot = () => ({ ...state, items: state.items.map(item => structuredClone(item)) });
    async function request(path, method = 'GET') {
        const csrf = typeof document === 'undefined' ? null : document.querySelector('meta[name="csrf-token"]')?.content;
        const response = await requestFetch(base + path, {
            method, credentials: 'same-origin',
            headers: { Accept: 'application/json', ...(csrf ? { 'X-CSRF-TOKEN': csrf } : {}), ...headers },
        });
        if (!response.ok) throw new Error('Notification request failed (' + response.status + ').');
        return response.json();
    }
    function remember(id) {
        seen.add(id);
        if (seen.size > 500) seen.delete(seen.values().next().value);
    }
    function apply(data) {
        const count = data.meta?.unread_count;
        if (!Number.isInteger(count) || count < 0 || !Array.isArray(data.notifications)) throw new Error('Invalid notification response.');
        const unique = new Map(data.notifications.map(item => [item.id, item]));
        state = { items: [...unique.values()], unreadCount: count, nextCursor: data.meta.next_cursor ?? null };
        for (const item of state.items) remember(item.id);
        emit('change', snapshot());
    }
    async function synchronize() {
        if (destroyed) return;
        dirty = true;
        if (refresh) return refresh;
        refresh = (async () => {
            while (dirty && !destroyed) {
                dirty = false;
                const data = await request('/bootstrap?limit=' + encodeURIComponent(limit));
                const nextChannel = data.realtime?.enabled ? data.realtime.channel : null;
                if (echo && nextChannel && channelName !== nextChannel) {
                    disconnect?.(); channelName = nextChannel;
                    disconnect = connectEcho(echo, nextChannel, {
                        onEvent: event => receive(event).catch(error => emit('error', error)),
                        onReconnect: () => synchronize().catch(error => emit('error', error)),
                    });
                }
                if (!dirty && !destroyed) apply(data);
            }
        })();
        try { await refresh; } finally { refresh = null; }
    }
    async function receive(event) {
        if (destroyed || event.schema !== '1.0' || !['notification.created','notification.read','notification.unread','notification.read_all'].includes(event.event)) return;
        const id = event.notification?.id;
        const fresh = event.event === 'notification.created' && typeof id === 'string' && !seen.has(id);
        if (fresh) remember(id);
        // Events can be duplicated or reordered: refresh authoritative data instead
        // of applying stale counters or guessing read-all membership.
        await synchronize();
        if (fresh && !destroyed) {
            const item = state.items.find(item => item.id === id);
            if (item) emit('created', structuredClone(item));
        }
    }
    function mutate(path, method) {
        const work = mutations.catch(() => {}).then(async () => {
            if (destroyed) return;
            dirty = true;
            const result = await request(path, method);
            await synchronize();
            return result;
        });
        mutations = work;
        return work;
    }
    return {
        get state() { return snapshot(); },
        on(event, listener) {
            if (!listeners.has(event)) listeners.set(event, new Set());
            listeners.get(event).add(listener);
            return () => listeners.get(event)?.delete(listener);
        },
        bootstrap: synchronize, synchronize, reconnect: synchronize, receive,
        markRead: id => mutate('/' + encodeURIComponent(id) + '/read', 'PATCH'),
        markUnread: id => mutate('/' + encodeURIComponent(id) + '/unread', 'PATCH'),
        markAllRead: () => mutate('/read-all', 'POST'),
        async loadMore() {
            const cursor = state.nextCursor;
            if (!cursor) return;
            const before = state;
            const data = await request('?limit=' + encodeURIComponent(limit) + '&cursor=' + encodeURIComponent(cursor));
            if (state !== before || destroyed) return;
            apply({ ...data, notifications: [...state.items, ...data.notifications] });
        },
        actions: {
            register(id, handler) {
                if (commands.has(id)) throw new Error('Action handler already registered.');
                commands.set(id, handler);
                return () => commands.delete(id);
            },
            async execute(action) {
                if (action.type === 'command') {
                    const handler = commands.get(action.id);
                    if (!handler) throw new Error('No application handler for this action.');
                    return handler(action);
                }
                if (action.type !== 'navigate' || typeof action.url !== 'string' ||
                    /[\s\\\u0000-\u001f]/.test(action.url) || action.url.startsWith('//') ||
                    !(/^(https?:\/\/|\/)/i.test(action.url))) throw new Error('Unsafe notification navigation.');
                return navigate(action.url);
            },
        },
        destroy() { destroyed = true; disconnect?.(); listeners.clear(); commands.clear(); },
    };
}
