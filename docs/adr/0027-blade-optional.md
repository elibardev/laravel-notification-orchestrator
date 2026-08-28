# ADR-0027 --- Blade is an optional frontend adapter

Status: **Accepted**

## Decision

Blade integration is optional and never required by backend
orchestration.

The package supports three first-class integration modes:

``` text
Blade managed UI
custom Web/SPA
headless/native client
```

The HTTP/realtime protocol is canonical and independently documented.

The package JS client is optional convenience.

Disabling `features.blade` must not disable API, persistence, realtime,
push, MQTT or other backend capabilities.
