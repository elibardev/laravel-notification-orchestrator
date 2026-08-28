# Changelog

## Unreleased

### Fixed

- Corrected seven PHP style issues found by the first GitHub Actions run and
  reproduced locally with a fresh Pint cache. No behavior or contract changes.

### Removed

- Removed the unused Phase 1 `UnavailableExecutor` placeholder and its
  `ExecutionUnavailableException`. The active `DeliveryExecutor` binding remains
  `NotificationExecutor`; delivery behavior, contracts and migrations are unchanged.

### Added

- Spanish Codex consumer guide covering package capabilities, package-versus-host
  change boundaries, table naming, integration, realtime centralization,
  reconnection, diagnostics and application design recommendations.
- Phase 3: Laravel Mail, provider-neutral Push with FCM HTTP v1/Google Auth,
  encrypted managed devices and authenticated device endpoints.
- MQTT personal/context transport, private Laravel broadcast contexts, optional
  application-owned presence policy and explicit `presence` skip reason.
- Fourth conditional migration, device invalidation/pruning, stale-owner/token
  safeguards, opt-in live profiles and complete-package integration tests.
- ADR-0036 records external adapter contracts and presence decisions.
- Current validation target: Herd PHP 8.2; CI remains on PHP 8.2/Laravel 12.

- Phase 2: conditional inbox/preferences/deliveries migrations, repositories,
  idempotent read state, native database queue jobs and sanitized tracking.
- Ownership-scoped HTTP API, personal broadcasting/authentication, optional Blade
  components and framework-neutral JS/Echo client.
- Idempotent install, safe scoped prune, runtime health and lifecycle events.
- ADR-0035 records storage keys, middleware and Phase 2 runtime refinements.

- Phase 1, increment 1.1: Laravel 12 package skeleton for PHP 8.2+.
- Composer auto-discovery, service provider and publishable declarative config.
- PHPUnit/Orchestra Testbench, PHPStan, Pint and a GitHub Actions PHP matrix.
- MIT license, contribution instructions and security reporting guidance.
- Phase 1, increments 1.2–1.4: centralized configuration/capability/table resolution,
  immutable payloads/references/identities and safe normalization.
- Recipient composition, exclusions/filters, extensible channel/context registries,
  transient preferences and immutable plans with recipient/channel job counts.
- Injectable orchestrator/dispatcher, fluent `Notify`, all accepted public fake
  assertions and native after-commit execution with scoped rollback cleanup.
- Registry-based `notifications:status` with redacted diagnostics and explicit
  unimplemented-module reporting.
- Phase 1 regression suite: 55 tests / 183 assertions; PHP 8.2/8.3 verification.

No release has been tagged. The initial release target remains `0.1.0`.
All three implementation phases are implemented. Automated verification uses
controlled external clients; no live-provider or non-SQLite deployment is claimed.
Current usage and limitations are in [PHASE-3.md](docs/PHASE-3.md).
Architectural documentation history is in [CHANGELOG-DOCS](docs/CHANGELOG-DOCS.md).

### Phase 3 closure — 2026-08-28

- Herd PHP 8.2.31: 93 PHPUnit tests / 464 assertions, including random seed 828.
- Composer validation/platform requirements, PHPStan level 8, Pint, four migration
  syntax checks and six Node client tests passed. No release/tag was created.
- Security checks cover encrypted tokens, authenticated ownership/CSRF, stale
  queued destinations, sanitized failures and disabled-provider configuration.
- Live-provider and non-SQLite acceptance are separate deployment checks; see
  [PHASE-3.md](docs/PHASE-3.md). The after-commit crash window remains unchanged.

### Skeleton checkpoint decisions — 2026-08-27

- Preserve the mandatory 1.1 review checkpoint; do not interpret its completion
  as completion of Phase 1 or authorization for Phase 2.
- Use PHPUnit 11 / Testbench 10, PHPStan level 8 and Laravel Pint. Keep tooling in
  development dependencies; runtime requires only PHP and Laravel components.
- Keep a development lock file and resolve it against PHP 8.2.0 to preserve the
  minimum version. Consumers are not constrained by this development lock.
- Use native Laravel loading/publishing for the skeleton. The full configuration
  combination and validation contract was deferred to 1.2 under ADR-0033; do not
  silently approximate recursive map/list semantics or activate unfinished modules.
- Configure CI without claiming a remote run. Do not initialize Git, tag a
  release, create migrations or contact notification providers at this checkpoint.

These are implementation choices within the accepted plan; no architecture ADR
was added or changed. See [TESTING.md](docs/TESTING.md) for verification evidence.

### Phase 1 completion decisions — 2026-08-27

- Continue after explicit user approval of increments 1.2–1.4; stop before Phase 2.
- Record exact configuration names, safe normalization behavior, extension
  contracts, transient preferences and fake limitations in [PHASE-1.md](docs/PHASE-1.md).
- Preserve the default feature values without pretending unimplemented modules
  are healthy. Production execution remains unavailable; no automatic real delivery.
- Attach callbacks to the default connection's exact Laravel transaction record
  so other active connections cannot capture the work. No distributed guarantee.
- Compute one immutable result during planning and reuse it when recording after
  commit. Do not resolve recipients or preferences again on commit/retry.
- No tables, migrations, provider packages, Git initialization or releases added.
  Accepted ADRs 0032–0034 remain authoritative and unchanged.
