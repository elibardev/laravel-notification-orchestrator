const events = ['notification.created', 'notification.read', 'notification.unread', 'notification.read_all'];

export function connectEcho(echo, name, { onEvent, onReconnect }) {
    const channel = echo.private(name);
    const listeners = events.map(event => {
        const listener = payload => onEvent({ ...payload, event });
        channel.listen('.' + event, listener);
        return [event, listener];
    });
    channel.subscribed?.(onReconnect);
    const connection = echo.connector?.pusher?.connection;
    const socket = echo.connector?.socket;
    connection?.bind('connected', onReconnect);
    socket?.on('connect', onReconnect);
    return () => {
        for (const [event, listener] of listeners) channel.stopListening?.('.' + event, listener);
        connection?.unbind('connected', onReconnect);
        socket?.off('connect', onReconnect);
    };
}
