# ADR-0028 --- Application-owned authentication and domain authorization

Status: **Accepted**

The package does not own authentication or domain authorization.

Notification ownership operations are scoped to the authenticated
notifiable.

Notification actions and context subscriptions never bypass application
policies.

Managed device registration is always bound to the authenticated
identity.

MQTT/Broadcast topic/channel names are not authorization mechanisms.
