# ADR-0025 --- Promote notification bootstrap endpoint

Status: **Accepted**

## Decision

Include:

``` text
GET /notifications/bootstrap
```

in the initial HTTP API.

It returns the initial notification page plus authoritative unread
count.

The endpoint is the preferred bootstrap mechanism for Blade integration
and may also be used by SPA/mobile clients.
