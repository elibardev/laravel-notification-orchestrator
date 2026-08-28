<div {{ $attributes->class('notifications-ui') }} data-notifications="toast" data-api-base="{{ url(config('notification-orchestrator.api.prefix')) }}" aria-live="polite" aria-relevant="additions"></div>
<x-notifications::assets />
