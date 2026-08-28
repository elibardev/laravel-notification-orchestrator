import { createNotificationClient } from './notification-client.js';

const clients = new Map();
function text(tag, value) { const element = document.createElement(tag); element.textContent = value; return element; }
export function mountNotifications(root, client) {
    if (root.dataset.mounted) return;
    root.dataset.mounted = 'true';
    root.notificationClient = client;
    const error = root.querySelector('[data-notification-error]');
    const showError = () => { if (error) error.textContent = 'No se pudo completar la operación. Intente actualizar.'; };
    const run = work => Promise.resolve().then(work).catch(showError);
    const list = root.querySelector('[data-notification-list]');
    function render(state) {
        const badge = root.querySelector('[data-notification-count]');
        if (badge) badge.textContent = String(state.unreadCount);
        if (!list) return;
        const focus = document.activeElement?.dataset?.notificationFocus;
        list.replaceChildren();
        for (const item of state.items) {
            const row = text('li', '');
            row.dataset.notificationId = item.id;
            row.append(text('strong', item.title), text('p', item.message),
                text('small', item.state.read ? 'Leída' : 'No leída'));
            const toggle = text('button', item.state.read ? 'Marcar no leída' : 'Marcar leída');
            toggle.type = 'button'; toggle.dataset.notificationFocus = item.id + ':toggle';
            toggle.addEventListener('click', () => run(() => item.state.read ? client.markUnread(item.id) : client.markRead(item.id)));
            row.append(toggle);
            for (const action of item.actions ?? []) {
                const button = text('button', action.label); button.type = 'button';
                button.dataset.notificationFocus = item.id + ':' + action.id;
                button.addEventListener('click', () => run(async () => {
                    try { await client.markRead(item.id); } catch { showError(); }
                    await client.actions.execute(action);
                }));
                row.append(button);
            }
            list.append(row);
        }
        if (!state.items.length) list.append(text('li', 'No hay notificaciones.'));
        if (focus) [...list.querySelectorAll('[data-notification-focus]')].find(el => el.dataset.notificationFocus === focus)?.focus();
        const more = root.querySelector('[data-notification-more]');
        if (more) more.hidden = !state.nextCursor;
    }
    client.on('change', render); client.on('error', showError); render(client.state);
    root.querySelector('[data-notification-refresh]')?.addEventListener('click', () => run(async () => { await client.synchronize(); if (error) error.textContent = ''; }));
    root.querySelector('[data-notification-read-all]')?.addEventListener('click', () => run(() => client.markAllRead()));
    root.querySelector('[data-notification-more]')?.addEventListener('click', () => run(() => client.loadMore()));
    const details = root.querySelector('details');
    details?.querySelector('summary')?.addEventListener('keydown', event => {
        if (event.key === 'Enter' || event.key === ' ') { event.preventDefault(); details.open = !details.open; }
    });
    details?.addEventListener('keydown', event => {
        if (event.key === 'Escape') { details.open = false; details.querySelector('summary').focus(); }
    });
    if (root.dataset.notifications === 'toast') {
        client.on('created', item => {
            const toast = text('div', ''); toast.className = 'notifications-toast';
            toast.append(text('strong', item.title), text('p', item.message));
            const close = text('button', 'Cerrar'); close.type = 'button';
            close.addEventListener('click', () => toast.remove()); toast.append(close);
            root.append(toast);
            while (root.querySelectorAll('.notifications-toast').length > 5) root.querySelector('.notifications-toast').remove();
        });
    }
}
export function startNotifications() {
    for (const root of document.querySelectorAll('[data-notifications]')) {
        const base = root.dataset.apiBase;
        if (!clients.has(base)) clients.set(base, createNotificationClient({ apiBaseUrl: base, echo: window.Echo ?? null }));
        const client = clients.get(base);
        mountNotifications(root, client);
    }
    for (const client of clients.values()) client.bootstrap().catch(() => {
        document.querySelectorAll('[data-notification-error]').forEach(el => { el.textContent = 'No se pudieron cargar las notificaciones.'; });
    });
}
if (typeof document !== 'undefined') {
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', startNotifications, { once: true });
    else startNotifications();
}
