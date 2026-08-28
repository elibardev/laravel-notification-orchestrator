# Security policy

This package is under development; no stable release or supported production
version has been published by this repository's implementation work.

Do not report vulnerabilities with credentials, raw push tokens, personal
notification payloads or authentication/session tokens in public issues.
Use a private reporting channel provided by the repository maintainer. If no
private channel is listed, request one without disclosing exploit details.

Reports should identify the affected revision, configuration, impact and minimal
reproduction using synthetic data. Do not test against systems without permission.

See [the security specification](docs/SECURITY.md) and
[authorization requirements](docs/SECURITY-AUTHORIZATION.md) for implementation
requirements. The Phase 1 engine does not yet deliver or persist notifications;
its public fake is intended for tests, not production delivery.
