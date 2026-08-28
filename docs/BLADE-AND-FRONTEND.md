# Blade and Frontend Integration

## Status

Draft v0.1 --- accepted architectural direction.

## Objective

Provide a first-class frontend experience for Laravel Blade applications
without requiring Vue, React or a separate frontend framework.

The package should offer:

1.  Blade components;
2.  a lightweight JavaScript client;
3.  optional Alpine.js integration when available;
4.  Laravel Echo integration for realtime;
5.  API-backed authoritative synchronization;
6.  publishable/customizable views.

The package must remain usable without these frontend components.

## Design principle

The Blade layer is an adapter over the same HTTP and realtime protocol
used by mobile or SPA clients.

It must not contain separate notification business logic.

``` text
Backend notification protocol
        |
        +-- Blade components
        +-- JS/TS client
        +-- Vue/React application
        +-- mobile application
```

## Minimum Blade experience

An application should be able to place:

``` blade
<x-notifications::bell />
```

in its layout.

This component should provide:

``` text
bell icon
authoritative unread badge
dropdown/panel
latest notifications
mark read
mark all read
notification actions
realtime updates when broadcast is enabled
```

A second component may provide a full inbox:

``` blade
<x-notifications::inbox />
```

## Proposed Blade components

Initial:

``` text
<x-notifications::bell />
<x-notifications::inbox />
<x-notifications::item />
```

Potential helper:

``` text
<x-notifications::toast-container />
```

The item component is primarily internal/composable but may be public
for customization.

## Bell component

Example:

``` blade
<x-notifications::bell
    :limit="8"
    position="right"
/>
```

Possible props should remain minimal.

The component loads authoritative bootstrap state and then subscribes to
personal realtime events.

## Toast behavior

The package can provide lightweight toast rendering for incoming
notifications.

Example:

``` blade
<x-notifications::toast-container />
```

When `notification.created` arrives:

``` text
store updated
unread counter replaced
optional toast displayed
```

Toast visibility is presentation behavior and does not mark a
notification read.

## JavaScript client

The package should ship a small framework-neutral client.

Conceptual API:

``` javascript
const notifications = createNotificationClient({
    apiBaseUrl: '/api/notifications',
    echo: window.Echo,
});

await notifications.bootstrap();

notifications.on('created', notification => {
    // optional application hook
});
```

Responsibilities:

``` text
bootstrap inbox
maintain unreadCount
deduplicate by notification ID
mark read/unread
mark all read
handle realtime events
resynchronize after reconnect
execute/dispatch notification actions
```

## Blade integration strategy

Blade components can internally use the same client.

Possible implementation options:

### Option A --- Vanilla JS

Advantages:

-   no frontend dependency;
-   broad compatibility.

### Option B --- Alpine.js adapter

Advantages:

-   natural fit for Blade/Tailwind applications;
-   concise reactive UI.

Decision:

The core frontend client should be framework-neutral.

Blade components may use a tiny internal vanilla adapter or Alpine when
explicitly configured, but the package must not require an application's
existing Alpine installation.

Avoid forcing Vue/React.

## Assets

Possible package assets:

``` text
resources/js/notification-client.js
resources/views/components/
resources/css/notifications.css
```

For Laravel applications using Vite, documentation should support
importing the client.

For the lowest-friction Blade setup, the package may expose a Blade
directive/component that injects its compiled lightweight asset.

Exact bundling strategy should be selected during implementation after
checking Laravel 12 package asset conventions.

## View customization

Views should be publishable:

``` bash
php artisan vendor:publish --tag=notification-orchestrator-views
```

Applications can then customize markup/styles without forking the
package.

Configuration should allow disabling package styling.

## Blade route/API dependency

The Blade components rely on package HTTP endpoints, not direct database
access from JavaScript.

Typical flow:

``` text
Blade render
    ↓
GET /notifications/bootstrap
    ↓
initial items + unread_count
    ↓
subscribe personal realtime
```

## Bootstrap endpoint

For Blade integration, promote the previously proposed endpoint to
initial API:

``` text
GET /notifications/bootstrap
```

Response:

``` json
{
  "notifications": [],
  "meta": {
    "unread_count": 4
  }
}
```

This reduces initial requests and provides a consistent integration
point.

## Action handling

The frontend client should expose an action handler registry.

Built-in:

``` text
navigate
```

Example:

``` javascript
notifications.actions.register('navigate', action => {
    window.location.assign(action.url);
});
```

`command` actions cannot be executed generically without an
application-defined endpoint/handler.

Applications register handlers:

``` javascript
notifications.actions.register('approve_map', async action => {
    // call application's authorized API
});
```

Alternative design:

A `command` action may include a safe application-defined endpoint and
HTTP method in future schema versions, but this should not be introduced
casually because it affects security and API design.

For v0.1:

``` text
navigate -> built-in
command -> application handler by action.id
```

## Read behavior from UI

Recommended default:

``` text
click notification
    ↓
mark read
    ↓
execute primary navigate action
```

The client should tolerate mark-read failure and still allow the
application's target authorization flow.

Configurable UX may choose mark-read when opening the dropdown, but this
should not be package default.

## Multi-tab synchronization

Inbox items are keyed by the personal HTTP resource `id`. Read/unread calls
and personal state events use that same inbox identity. The semantic/context
payload `id` is a separate logical identifier and must not be used as the
personal inbox key. See [ADR-0032](adr/0032-notification-identity.md).

All tabs use the same personal channel and authoritative unread counts.

The frontend client must be idempotent.

If all tabs receive:

``` text
notification.read
unread_count = 3
```

all set:

``` text
unreadCount = 3
```

No tab-specific counter arithmetic is required.

## Reconnect

On Echo reconnect:

``` text
re-fetch bootstrap or unread count
```

The client should expose/implement automatic resynchronization.

## No Echo mode

If structural broadcast is disabled:

``` text
Blade components still work
```

They use HTTP bootstrap and user interactions.

Realtime updates are simply absent until
refresh/polling/application-triggered sync.

The package should not silently add polling by default.

## Accessibility

Blade components should:

-   use semantic buttons/links;
-   support keyboard interaction;
-   provide ARIA labels for the bell/unread count;
-   not rely solely on color;
-   provide accessible toast announcements where appropriate.

## Styling

Default package styling should be conservative and easily disabled.

Do not assume Bootstrap or Tailwind classes in the public markup
contract.

The package may ship minimal scoped CSS.

Adapters/themes for Bootstrap/Tailwind can be considered later.

## Mobile/SPAs

The Blade package layer is not required by native/mobile clients.

They consume the same:

``` text
HTTP API
payload schema
realtime event schema
actions
```

This is the core reason to keep the Blade implementation as an adapter.

## Optionality

Blade is a frontend adapter, not a backend dependency.

When `features.blade = false`, applications may use the package JS
client, their own JavaScript client, or operate fully headless through
the documented HTTP/realtime protocol.

See `HEADLESS-AND-CUSTOM-FRONTEND.md`.
