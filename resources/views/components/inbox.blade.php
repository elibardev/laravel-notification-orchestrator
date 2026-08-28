<section {{ $attributes->class('notifications-ui') }} data-notifications="inbox" data-api-base="{{ url(config('notification-orchestrator.api.prefix')) }}" aria-label="Bandeja de notificaciones">
    <h2>Notificaciones <span data-notification-count aria-live="polite">0</span></h2>
    <button type="button" data-notification-refresh>Actualizar</button>
    <button type="button" data-notification-read-all>Marcar todas leídas</button>
    <p data-notification-error role="alert"></p>
    <ul data-notification-list aria-label="Notificaciones"></ul>
    <button type="button" data-notification-more hidden>Ver más</button>
</section>
<x-notifications::assets />
