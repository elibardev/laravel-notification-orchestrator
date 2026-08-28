# Roadmap

## Current status

Phases 1–3 are implemented, including four conditional package migrations and
external adapters tested with controlled clients. No release has been published.
See [PHASE-2.md](PHASE-2.md) and [PHASE-3.md](PHASE-3.md) for usage, evidence and
live-provider/database validation limits. The stages below retain the agreed scope.
The implementation is organized into **three phases**, agreed on 2026-08-27.
This replaces the earlier eight-phase roadmap and the ten-phase AGENTS order.

This roadmap summarizes [IMPLEMENTATION-PLAN.md](IMPLEMENTATION-PLAN.md), which
defines increments, dependencies, tests and phase closure evidence. Accepted
ADRs still govern architecture. Reorganizing work adds no tables or features.

## Phase 1 — Foundation and orchestration engine

**Outcome:** a clean Laravel package skeleton, then the validated orchestration
engine and public testing API without real provider delivery.

Includes Composer, auto-discovery, provider/configuration, Testbench, CI/style/
static analysis, centralized configuration/table resolution, semantic objects,
recipient resolution, registries, preferences/planning contracts, context plans,
Notify/send/result/fake, initial diagnostics and after-commit behavior.

**Mandatory first checkpoint:** finish and review the tested skeleton before
implementing the engine. Package migrations in this phase: **none**.

**Gate:** deterministic planning/fake tests and quality checks pass; report
results and obtain authorization before Phase 2.

## Phase 2 — Persistent inbox, clients and operations

**Outcome:** a working persistent inbox and personal client protocol with
operational controls, using fake external channels until their implementations
arrive in Phase 3.

Includes database persistence, preferences storage/API, actual database queue
execution, delivery tracking, read/unread/bootstrap HTTP API, personal broadcast,
optional Blade/JS/Echo adapters, headless support, install/status/prune,
authorization, synchronization, logging and browser verification.

Package tables introduced: **notifications, preferences, deliveries**, each
conditional on its feature.

**Gate:** storage/queue/transaction/security/protocol/browser tests and quality
checks pass; report results and obtain authorization before Phase 3.

## Phase 3 — External delivery and full integration

**Outcome:** the complete approved package, with optional providers and integrated
verification over the earlier phases.

Includes Mail, Push/FCM, managed or external device destinations, managed device
API/token lifecycle, personal/context MQTT, Laravel Broadcasting context
transport, custom extension conformance, optional presence integration and
provider/device diagnostics and retention.

Package table introduced: **devices**, only for managed storage. Total initial
package tables: **four**. No dispatches, outbox, attempt-history or context-delivery
tables are added.

Finish feature-combination tests, compatibility/security/performance checks,
documentation and release preparation. Keep presence application-owned and
ephemeral; any unresolved suppression contract requires an ADR before coding.

**Gate:** all approved modules and automated checks are complete, with separate
evidence for live integrations. Do not claim a live provider or production
deployment was verified solely because fake-based CI passed.

## Safety and scope

- Three phases do not mean three large patches; use the ordered increments in
  the implementation plan and test each one.
- Stop at the initial skeleton checkpoint and at every phase boundary.
- Contracts/fakes precede provider implementations.
- Security and transaction tests accompany each affected feature.
- Default CI does not require live FCM, SMTP, Mosquitto or Reverb.
- Deferred ideas remain deferred: extra tables, context-only builder mode,
  advanced public conveniences, exactly-once delivery and distributed transactions
  are not authorized by this reorganization.

## Releases and version 1.0

Phase numbers do not map to release versions. The initial release target remains
`0.1.0`; intermediate development releases require an explicit release decision.

Version 1.0 still requires stable documented PHP/config/extension contracts,
payload and protocol compatibility, safe migrations, verified support matrices,
complete installation/upgrade guidance and tested deployment combinations.
Completing three implementation phases does not automatically declare 1.0,
publish to Packagist or create a Git tag.
