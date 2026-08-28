# ADR-0017 --- Separate Devices from Push delivery

Status: **Accepted**

## Decision

The package does not own users, sessions or authentication tokens.

`devices` is an optional managed push-endpoint store.

`push` is an independent delivery capability that consumes a
`PushDestinationResolver`.

Managed push tokens are encrypted at rest and accompanied by a SHA-256
fingerprint for lookup/deduplication.

Applications may replace the managed device store with their own
destination resolver.
