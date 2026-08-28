# ADR-0035 — Phase 2 storage and runtime refinements

Status: **Accepted**

## Context

Phase 2 is authorized. Its dedicated specifications leave physical keys,
provisional repository signatures and frontend integration details open.

## Decisions

- Keep nullable preference notification_type (null means global). Use a SHA-256
  primary ID over the JSON tuple of owner type, owner ID, notification type and
  channel. Atomic upsert therefore prevents duplicate global preferences on
  SQLite, MySQL/MariaDB and PostgreSQL without NULL uniqueness assumptions.
- Delivery IDs likewise hash the logical notification, owner, channel and
  destination fingerprint. Retry updates the same row; successful destinations
  are not repeated within a retried job. Exactly-once external delivery is not
  promised. Success clears active error fields. Provider exceptions are replaced
  with fixed safe error codes/messages, including exceptions exposed to Queue.
- Repository owner parameters accept objects, normalized through the existing
  RecipientNormalizer; frozen RecipientIdentity values are supported. Inbox
  resources explicitly project personal IDs without changing semantic JSON.
- Validate implemented enabled context transports at boot. A reserved but absent
  context implementation is rejected when explicitly requested for execution;
  the public fake can still plan it. Personal broadcast does not require the
  Phase 3 context broadcast implementation. No new feature switch is introduced.
- Default HTTP middleware becomes web + auth, usable with native session auth
  and CSRF protection without Sanctum. Applications may replace the entire list.
- Personal events without inbox persistence carry semantic notification data,
  no invented personal ID/read state, and no authoritative inbox count. The
  inbox JS adapter uses the database-backed API and refreshes on realtime state
  changes to recover from duplicates, stale events and concurrent requests.
- Publish native ES modules and scoped CSS (no build tool required). Blade uses
  a vanilla adapter. Command actions require application handlers by action ID.
- Run local verification and CI on PHP 8.2; the declared PHP >=8.2 requirement
  is unchanged. Herd is the local PHP entry point.

## Consequences

Three conditional, publish-only package migrations in Phase 2; no automatic
migrations or added dispatch/outbox/attempt/context tables. Consumers must
protect queue payloads and failed_jobs, which can contain execution destinations.
The public PHP and HTTP shapes are documented in PHASE-2.md before release.
