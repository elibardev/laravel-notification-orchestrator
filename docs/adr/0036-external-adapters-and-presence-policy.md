# ADR-0036 — External adapters and optional presence policy

Status: **Accepted**

## Context

Phase 3 was explicitly authorized on 2026-08-28. Its specifications leave push
projection, presence suppression and concrete client adapters provisional.

## Decisions

- Use Laravel Mail, the Google Auth PHP library for service-account OAuth and
  Laravel HTTP for FCM HTTP v1. Use php-mqtt/client behind an injectable factory;
  Mosquitto is a reference broker, not a Core dependency.
- PushDriver receives PushDestination plus an immutable PushMessage projection,
  not a modified semantic payload. Projection includes title/body/type/schema,
  logical ID and optional personal notification_id/actions, never arbitrary data,
  actor or subject by default. No personal ID without inbox persistence.
- Push destinations retain driver/device metadata in the frozen channel plan.
  Managed execution rechecks current owner, enabled state and token fingerprint;
  reassignment/rotation fails safely without sending or resolving new recipients.
- Managed registration atomically upserts driver/token_hash and optional random
  installation UUID. Rotations preserve installation identity; token ownership
  can transfer to the authenticated registering owner. Raw tokens are encrypted
  using Laravel encryption and never returned by device resources.
- PresencePolicy is application-owned and evaluated once during planning after
  preferences, before destination resolution. It can suppress OPTIONAL channels
  only. Add explicit SkipReason::PRESENCE ('presence'); do not mislabel suppression
  as user_preference. Structural channels and contexts are unaffected. No presence
  storage, auto-read or default domain assumptions; disabled by default. Enabling
  requires a configured policy class. Applications own ephemeral presence/TTL
  and authenticated active-context updates.
- Context broadcast uses PrivateChannel and event notification.context carrying
  the unchanged semantic payload. Application/broker policies own subscription
  authorization. Personal auth never authorizes a context channel.
- Device routes share the authenticated API prefix but can be registered with
  devices enabled while inbox API is disabled. No dependency on database inbox.
- Provider adapters perform no network requests during configuration validation.
  Status distinguishes configuration readiness from live-service verification.
  Automated verification uses HTTP/mail/MQTT clients under test control; actual
  providers require explicit test credentials/destinations and separate evidence.

## Consequences

The fourth table is devices. No additional tables, provider-specific Core branches,
new fluent terminal methods, or exactly-once claims are introduced. The new presence
skip reason and PushMessage contract are documented before the initial release.
