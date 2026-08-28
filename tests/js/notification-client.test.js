import test from 'node:test';
import assert from 'node:assert/strict';
import { createNotificationClient } from '../../resources/js/notification-client.js';
function fixture() {
    let items = [{ id: 'personal-1', title: 'One', state: { read: false }, actions: [] }];
    const calls = [];
    const client = createNotificationClient({ fetch: async (url, options) => {
        calls.push([url, options.method]);
        if (options.method !== 'GET') {
            items[0].state.read = !url.endsWith('/unread');
            return { ok: true, json: async () => ({ meta: { unread_count: items[0].state.read ? 0 : 1 } }) };
        }
        return { ok: true, json: async () => ({ notifications: structuredClone(items), meta: { unread_count: items.filter(i => !i.state.read).length } }) };
    } });
    return { client, calls, add: item => items.push(item) };
}
test('bootstrap, explicit mutations and stale events converge without polling', async () => {
    const { client, calls } = fixture();
    await client.bootstrap(); assert.equal(client.state.unreadCount, 1);
    await client.markRead('personal-1'); assert.equal(client.state.unreadCount, 0);
    await client.receive({ schema: '1.0', event: 'notification.unread', meta: { unread_count: 99 } });
    assert.equal(client.state.unreadCount, 0);
    await client.markUnread('personal-1'); assert.equal(client.state.unreadCount, 1);
    await client.markAllRead(); assert.equal(client.state.unreadCount, 0);
    const count = calls.length;
    await new Promise(resolve => setTimeout(resolve, 25));
    assert.equal(calls.length, count);
});
test('multiple clients reconnect to the same authoritative state', async () => {
    let count = 3;
    const fetch = async () => ({ ok: true, json: async () => ({ notifications: [], meta: { unread_count: count } }) });
    const a = createNotificationClient({ fetch }), b = createNotificationClient({ fetch });
    await Promise.all([a.bootstrap(), b.bootstrap()]);
    count = 1;
    await Promise.all([a.reconnect(), b.reconnect()]);
    assert.equal(a.state.unreadCount, 1); assert.equal(b.state.unreadCount, 1);
});
test('duplicate created events produce one toast hook and never imply read', async () => {
    const { client, calls, add } = fixture(); let created = 0;
    client.on('created', () => created++);
    await client.bootstrap();
    add({ id: 'personal-2', title: 'Two', state: { read: false }, actions: [] });
    const event = { schema: '1.0', event: 'notification.created', notification: { id: 'personal-2' } };
    await Promise.all([client.receive(event), client.receive(event)]);
    assert.equal(created, 1); assert.equal(client.state.items.length, 2);
    assert.equal(client.state.unreadCount, 2);
    assert.ok(calls.every(call => call[1] === 'GET'));
});
test('action handlers require app registration and reject unsafe URLs', async () => {
    const urls = [];
    const client = createNotificationClient({ navigate: url => urls.push(url) });
    await assert.rejects(client.actions.execute({ type: 'navigate', url: 'javascript:alert(1)' }));
    await assert.rejects(client.actions.execute({ type: 'navigate', url: '//attacker.test' }));
    await assert.rejects(client.actions.execute({ type: 'command', id: 'approve' }));
    let ran = false; client.actions.register('approve', () => { ran = true; });
    await client.actions.execute({ type: 'command', id: 'approve' }); assert.ok(ran);
    await client.actions.execute({ type: 'navigate', url: '/authorized-target' });
    assert.deepEqual(urls, ['/authorized-target']);
});
test('event arriving during bootstrap invalidates the older response', async () => {
    let release, count = 0, requests = 0;
    const client = createNotificationClient({ fetch: async () => {
        const captured = count; requests++;
        if (requests === 1) await new Promise(resolve => { release = resolve; });
        return { ok: true, json: async () => ({ notifications: [], meta: { unread_count: captured } }) };
    } });
    const boot = client.bootstrap();
    count = 4;
    const event = client.receive({ schema: '1.0', event: 'notification.read_all', meta: { unread_count: 0 } });
    release(); await Promise.all([boot, event]);
    assert.equal(client.state.unreadCount, 4); assert.equal(requests, 2);
});
test('Echo subscribes once, reconnects and removes only its listeners', async () => {
    const events = new Map(); let reconnect, removed = 0, requests = 0;
    const channel = { listen: (name, fn) => events.set(name, fn), subscribed: () => {}, stopListening: () => removed++ };
    const echo = { private: () => channel, connector: { pusher: { connection: { bind: (name, fn) => { reconnect = fn; }, unbind: () => {} } } } };
    const client = createNotificationClient({ echo, fetch: async () => {
        requests++; return { ok: true, json: async () => ({ notifications: [], meta: { unread_count: requests }, realtime: { enabled: true, channel: 'notifications.account.1' } }) };
    } });
    await client.bootstrap(); assert.equal(events.size, 4);
    await reconnect(); assert.equal(client.state.unreadCount, 2);
    client.destroy(); assert.equal(removed, 4);
});
