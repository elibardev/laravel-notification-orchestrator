# Contributing

Read [AGENTS.md](AGENTS.md), the [implementation plan](docs/IMPLEMENTATION-PLAN.md)
and relevant accepted [ADRs](docs/adr/README.md) before changing contracts.

## Development

Use PHP 8.2+ and Composer 2. Install the extensions required by the locked
development dependencies; `composer check-platform-reqs` reports missing ones.
PDO SQLite must be enabled for the native transaction tests; external providers
are unnecessary. On a compatible PHP installation, the extension may be enabled
for a single run using `php -d extension=pdo_sqlite vendor/bin/phpunit`.

```sh
composer install
composer check-platform-reqs
composer check
```

`composer format` fixes PHP style. `composer format:check` checks without changes.
`composer test` runs PHPUnit/Testbench; `composer analyse` runs PHPStan at level 8.
The lock file pins the development environment; consumers resolve the package's
runtime constraints independently. Composer's PHP platform is set to 8.2.0 so
dependency updates from newer runtimes retain the minimum supported version.

On Windows, make sure the intended `php.exe` is available to Composer; an explicit
PHP executable plus `composer.phar` can be used when PHP is not on `PATH`.
Without Git metadata, Composer may report a fallback root version of `1.0.0`;
that diagnostic does not publish a release or change the `0.1.0` target.

Keep changes scoped, add tests for changed behavior and update documentation.
Do not commit credentials, generated caches or vendor files. Material architecture
changes need an accepted ADR before implementation. Each increment must pass
the available checks; the skeleton checkpoint and phase boundaries require review.

The default suite must not require FCM, Reverb, Mosquitto or network access.
Live provider verification belongs to separate opt-in integration tests.
