# ADR-0020 --- Fluent facade plus explicit service API

Status: **Accepted**

## Decision

Expose two public usage styles:

``` text
Notify::make(...) fluent builder
NotificationDispatcher / service API
```

The fluent API is an ergonomic layer over typed immutable core objects.

The facade is optional convenience; dependency injection remains fully
supported.

Only `send()` is used as the fluent terminal operation in the initial
API.

`dispatch()` remains service-layer terminology.
