@once
    @if(config('notification-orchestrator.blade.styles', true))
        <link rel="stylesheet" href="{{ asset('vendor/notification-orchestrator/css/notifications.css') }}">
    @endif
    <script type="module" src="{{ asset('vendor/notification-orchestrator/js/blade-adapter.js') }}"></script>
@endonce
