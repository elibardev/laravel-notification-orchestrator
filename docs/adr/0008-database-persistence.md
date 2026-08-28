# ADR-0008 --- Database persistence is not user-configurable

Status: **Accepted**

## Context

Persistent notifications provide the authoritative notification inbox,
read/unread state and multi-device synchronization.

Allowing a user to disable persistence could produce a push or realtime
notification that later has no corresponding persistent record.

## Decision

If the database notification feature is enabled by the application,
users cannot disable database persistence through notification
preferences.

Preferences apply only to optional delivery channels.

## Consequence

`database` is treated as structural persistence rather than a user
preference.
