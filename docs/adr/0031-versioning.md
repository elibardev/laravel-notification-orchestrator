# ADR-0031 --- Independent versioning surfaces with SemVer package contract

Status: **Accepted**

Composer package follows Semantic Versioning.

Payload schema, realtime protocol and database schema are separately
documented compatibility surfaces.

Payload/realtime 1.x permit additive compatible evolution.

Documented PHP/config/Blade extension contracts freeze at package 1.0.

Forward-only migrations and explicit deprecation are required.
