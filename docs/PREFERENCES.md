# Notification Preferences Specification

## Status

Draft v0.1.

## Objective

The preferences module controls optional delivery channels on a per-user
basis while preserving a mandatory persistent notification record
whenever the database feature is enabled.

The model must support:

-   package defaults;
-   application defaults;
-   per-notification-type defaults;
-   explicit user preferences;
-   channel-level resolution;
-   inheritance when no explicit preference exists;
-   future extension without changing the core notification contract.

## Fundamental rule

The `database` persistence channel is not user-configurable.

If database persistence is enabled for the application, a notification
selected for delivery is persisted regardless of the recipient's
preferences.

User preferences only affect optional delivery channels such as:

``` text
push
mail
mqtt
future custom channels
```

## Resolution principle

A user preference does not add a channel that the event did not request.

It can only permit or suppress channels already enabled by the
application and event policy.

Conceptually:

``` text
OptionalDeliveryChannels =
SystemCapabilities
INTERSECT EventChannels
INTERSECT EffectiveUserPreferences
```

Database persistence is evaluated separately:

``` text
DatabasePersistence =
DatabaseFeatureEnabled
```

## Default inheritance

If a user has no explicit preference for a notification type/channel
pair, the effective value is inherited from configured defaults.

Example:

``` text
Default push preference = enabled

User has no preference for incident.created / push

Effective preference = enabled
```

If the user explicitly disables it:

``` text
Default = enabled
User = disabled

Effective = disabled
```

## Proposed preference hierarchy

The initial hierarchy should be:

``` text
1. Explicit user preference for notification type + channel
2. User global preference for channel
3. Application default for notification type + channel
4. Application global default for channel
5. Package default
```

The first defined value wins.

This hierarchy supports both simple and advanced installations.

## Example

Configuration:

``` php
'preferences' => [
    'defaults' => [
        'push' => true,
        'mail' => false,
        'mqtt' => true,
    ],

    'types' => [
        'incident.created' => [
            'push' => true,
            'mail' => true,
        ],

        'document.uploaded' => [
            'mail' => false,
        ],
    ],
],
```

User preferences:

``` text
push globally = false
incident.created/mail = true
```

Effective result for `incident.created`:

``` text
broadcast = true
push      = false
mail      = true
```

Effective result for `document.uploaded`:

``` text
broadcast = true
push      = false
mail      = false
```

## Proposed storage model

Table:

``` text
{prefix}preferences
```

Logical columns:

``` text
id
notifiable_type
notifiable_id
notification_type nullable
channel
enabled
created_at
updated_at
```

Interpretation:

``` text
notification_type = null
    -> global preference for the channel

notification_type = incident.created
    -> preference specific to that notification type
```

Recommended uniqueness:

``` text
notifiable_type
notifiable_id
notification_type
channel
```

The database channel must not be persisted in this table because users
cannot configure it.

## PreferenceResolver

Proposed contract:

``` php
interface PreferenceResolver
{
    public function enabled(
        object $recipient,
        string $notificationType,
        string $channel
    ): bool;
}
```

The resolver is responsible for hierarchy evaluation and caching.

## Preference repository

Proposed contract:

``` php
interface PreferenceRepository
{
    public function get(
        object $notifiable,
        ?string $notificationType,
        string $channel
    ): ?bool;

    public function set(
        object $notifiable,
        ?string $notificationType,
        string $channel,
        bool $enabled
    ): void;

    public function delete(
        object $notifiable,
        ?string $notificationType,
        string $channel
    ): void;
}
```

A `null` result means no explicit preference exists and inheritance
continues.

## Reset semantics

Deleting an explicit preference does not mean disabled.

It means:

``` text
inherit again
```

This distinction is essential.

## HTTP API behavior

Potential operations:

``` text
GET    /notification-preferences
PUT    /notification-preferences
DELETE /notification-preferences/{type}/{channel}
```

The API should return both:

``` text
configured value
effective value
source
```

Example:

``` json
{
  "type": "incident.created",
  "channel": "push",
  "configured": null,
  "effective": true,
  "source": "type_default"
}
```

This makes inheritance understandable in UI and debugging.

## Source taxonomy

Potential `source` values:

``` text
user_type
user_global
type_default
global_default
package_default
```

## UI implications

A preferences interface should be able to distinguish:

``` text
On
Off
Inherited
```

For an inherited value, the UI may display the current effective value
while preserving the ability to return to inheritance after an override
is removed.

## Performance

Preference lookup occurs per recipient and per optional channel.

For large recipient sets the resolver should support:

-   eager/bulk loading;
-   request-local memoization;
-   optional Laravel cache integration.

Caching is an optimization and must not be required for correctness.
