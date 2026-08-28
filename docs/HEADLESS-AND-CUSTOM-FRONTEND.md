# Headless, SPA and Custom Frontend Integration

## Status

Draft v0.1 --- accepted architectural direction.

## Principle

Blade is optional.

The package must remain fully functional in:

``` text
Blade applications
Vue/React/Inertia applications
custom JavaScript applications
native iOS/Android applications
Flutter/React Native applications
API-only Laravel backends
server-to-server consumers
```

No backend notification capability depends on Blade.

## Feature switch

``` php
'features' => [
    'blade' => false,
]
```

When disabled:

``` text
no Blade components loaded
no package UI assets required
no package views required
```

The backend API, persistence, broadcast, push, MQTT, preferences,
tracking and context delivery remain available.

## Three frontend integration modes

### 1. Blade managed UI

``` text
Package Blade components
+
package JS client
+
HTTP API
+
Echo adapter
```

Example:

``` blade
<x-notifications::bell />
```

### 2. Custom Web / SPA

Application builds its own UI.

It may use:

``` text
package JS client only
```

or directly consume:

``` text
HTTP API
+
personal realtime protocol
```

Example:

``` javascript
const client = createNotificationClient({
    apiBaseUrl: '/api/notifications',
    echo: window.Echo,
});
```

No package Blade markup is involved.

### 3. Headless / native mobile

No package frontend assets.

Client consumes:

``` text
GET /notifications/bootstrap
GET /notifications
PATCH /notifications/{id}/read
...
```

plus:

``` text
push payloads
personal realtime protocol where supported
MQTT where configured
```

## HTTP API independence

Personal HTTP resource IDs and read/unread route IDs are recipient-specific
inbox IDs. Semantic/context payload IDs identify the shared logical occurrence.
Do not use one in place of the other. Personal push includes an inbox ID only
when persistence is enabled. See [ADR-0032](adr/0032-notification-identity.md).

The HTTP notification API is not a Blade API.

It is the canonical client-facing interface for any frontend.

Initial endpoints:

``` text
GET   /notifications/bootstrap
GET   /notifications
GET   /notifications/unread-count

PATCH /notifications/{id}/read
PATCH /notifications/{id}/unread
POST  /notifications/read-all
```

Device endpoints appear only when managed devices are enabled.

Preference endpoints appear only when preferences are enabled.

## Authentication

The package does not mandate:

``` text
Sanctum
Passport
session authentication
JWT
custom guards
```

Routes use configurable middleware.

Default published configuration may suggest a Laravel-native middleware
stack, but consumers can replace it.

Example:

``` php
'api' => [
    'middleware' => ['api', 'auth:sanctum'],
]
```

Applications using session auth may configure:

``` php
['web', 'auth']
```

## Custom UI without package JavaScript

The application may ignore both Blade components and the package JS
client.

Example:

``` javascript
const response = await fetch('/notifications/bootstrap');
```

and its own Echo handlers.

The public protocol documentation must therefore be sufficient to
implement a client from scratch.

## Package JS client independence

The JS client is convenience, not requirement.

It must not hide undocumented server behavior.

Every operation performed by the JS client must correspond to a
documented HTTP/realtime protocol action.

## UI styling independence

Disabling Blade does not affect payload/action semantics.

An application can render notifications as:

``` text
Bootstrap dropdown
Tailwind popover
AdminLTE navbar menu
custom React component
native mobile screen
```

The orchestrator does not care.

## Inertia

Inertia applications can choose:

``` text
Blade shell + package JS client
```

or:

``` text
custom Vue/React notification component
```

No dedicated Inertia integration is required for v0.1.

## Livewire

A future Livewire adapter may be useful, but should not be required
initially.

A Blade component can coexist with Livewire applications because the
notification client operates independently.

Dedicated Livewire components can be added later if demand justifies
them.

## Progressive adoption

A project may begin:

``` text
API/headless only
```

then enable:

``` text
blade = true
```

later without changing notification business rules.

Likewise a Blade project can replace the provided UI with a custom
frontend while preserving backend APIs and protocols.
