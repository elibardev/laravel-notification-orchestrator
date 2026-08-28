const wire = new BroadcastChannel('orchestrator-browser-test');
const listeners = new Map();
let connected = () => {};
const deliver = event => { for (const listener of listeners.get('.' + event.event) ?? []) listener(event); };
wire.onmessage = event => deliver(event.data);
window.Echo = {
    private: () => ({
        listen(name, listener) { if (!listeners.has(name)) listeners.set(name, new Set()); listeners.get(name).add(listener); },
        subscribed() {}, stopListening(name, listener) { listeners.get(name)?.delete(listener); },
    }),
    connector: { pusher: { connection: { bind(name, listener) { connected = listener; }, unbind() {} } } },
};
document.addEventListener('DOMContentLoaded', () => {
    const status = document.querySelector('#fixture-status');
    document.querySelector('#create').onclick = async () => {
        const response = await fetch('/fixture/create', { method: 'POST', headers: { Accept: 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content } });
        const event = await response.json(); deliver(event); wire.postMessage(event); status.textContent = 'Notificación creada';
    };
    document.querySelector('#reconnect').onclick = async () => { await connected(); status.textContent = 'Reconexión sincronizada'; };
    document.querySelector('#stale').onclick = () => {
        const event = { schema: '1.0', event: 'notification.unread', occurred_at: '2000-01-01T00:00:00Z', meta: { unread_count: 999 } };
        deliver(event); deliver(event); wire.postMessage(event); status.textContent = 'Eventos antiguos enviados';
    };
    queueMicrotask(() => document.querySelector('[data-notifications="inbox"]').notificationClient.actions.register('demo', () => { status.textContent = 'Handler de aplicación ejecutado'; }));
});
