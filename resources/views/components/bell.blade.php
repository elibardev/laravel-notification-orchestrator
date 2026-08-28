<div {{ $attributes->class('notifications-ui') }} data-notifications="bell" data-api-base="{{ url(config('notification-orchestrator.api.prefix')) }}">
    <details>
        <summary aria-label="Notificaciones">Notificaciones <span data-notification-count aria-live="polite">0</span></summary>
        <div data-notification-panel>
            <button type="button" data-notification-refresh>Actualizar</button>
            <button type="button" data-notification-read-all>Marcar todas leídas</button>
            <p data-notification-error role="alert"></p>
            <ul data-notification-list aria-label="Últimas notificaciones"></ul>
            <button type="button" data-notification-more hidden>Ver más</button>
        </div>
    </details>
</div>
<x-notifications::assets />
