# Phase 3 — external delivery and complete-package integration

## Scope and status

The third agreed phase implements Mail, Push/FCM, managed devices, MQTT,
context transports and optional application-owned presence. The package remains
on the experimental 0.x line; release target 0.1.0 has not been published.

PHP 8.2 is the current validation/CI target, as requested. Laravel ^12.0 and
Composer PHP >=8.2 constraints are retained. The local runtime is Herd PHP 8.2.31.
See [Phase 2](PHASE-2.md) for inbox, HTTP, queue, frontend and operations.
[ADR-0036](adr/0036-external-adapters-and-presence-policy.md) records the new
PushMessage and PresencePolicy contracts, fifth skip reason and adapter decisions.

## Install or activate later

Run these commands in the consuming Laravel application's root, after enabling
the desired features in config/notification-orchestrator.php:

```sh
herd php artisan notifications:install
herd php artisan migrate
herd php artisan notifications:status
```

Install never executes migrations or overwrites published files. Enabling a later
feature and rerunning install publishes only its missing migration. Compare newly
shipped config keys with your published file; maps receive defaults, but list
overrides replace defaults. Rebuild config cache after changes and restart workers.

There are exactly **four conditional package migrations**:

| Logical table | Feature |
| --- | --- |
| notifications | database |
| preferences | preferences |
| deliveries | delivery_tracking |
| devices | devices |

The default prefix is notify_; explicit database.tables overrides take precedence.
There are no dispatch, outbox, attempt-history, contextual-delivery or presence
tables. Laravel's jobs/failed_jobs tables belong to the application. Use its native
queue migration if needed and run a worker for the configured queue
(default notifications). Do not rename tables by changing config after migration;
deploy an explicit application migration/data move instead.

## Mail

Enable features.mail. mail.mailer is null by default, selecting Laravel's default
mailer; otherwise use the name of an existing mail.mailers entry in the host.
The package does not define SMTP credentials.

Recipients supply Laravel's routeNotificationFor('mail', $notification), normally
through the Notifiable trait and an application routeNotificationForMail method.
The resolver accepts an address or an address list/map, filters invalid addresses
and snapshots the destinations. It does not query an assumed users.email column.
RecipientIdentity alone has no mail route; provide a notifiable or custom
Contracts\ChannelDestinationResolver configured under channels.destinations.mail.

```php
use Elibardev\NotificationOrchestrator\Facades\Notify;

Notify::make('record.created')
    ->title('New record')
    ->message('A record is available.')
    ->recipients($account) // Application notifiable with a mail route.
    ->channels(['mail'])
    ->send();
```

Mail\OrchestratedMail escapes title, message and navigate links. Command actions
are not executed by mail. Provider acceptance is sent, not delivered/read.

## Push and FCM

Managed mode needs features.push=true AND features.devices=true. External mode
needs only push and a custom resolver; no device/inbox table is required when
their features are disabled. All settings below belong to notification-orchestrator.

```php
use Elibardev\NotificationOrchestrator\Devices\DatabasePushDestinationResolver;

return [
    'features' => ['push' => true, 'devices' => true],
    'push' => [
        'default_driver' => 'fcm',
        'destination_resolver' => DatabasePushDestinationResolver::class,
        'drivers' => ['fcm' => [
            'project_id' => env('NOTIFICATIONS_FCM_PROJECT'),
            'credentials' => env('NOTIFICATIONS_FCM_CREDENTIALS'),
        ]],
    ],
];
```

Merge these keys into the published config; do not paste a second return statement.
NOTIFICATIONS_FCM_CREDENTIALS is an absolute path to a readable service-account
JSON outside the public directory and source control. No key is embedded in docs.
The host must enable FCM HTTP v1 and grant its service account the required access.
iOS/APNs and web client setup remain application/Firebase responsibilities.

FCM uses google/auth ServiceAccountCredentials for OAuth and Laravel HTTP for
OAuth and delivery. Configuration checks validate the file/key/project without
contacting Google. OAuth tokens are cached until shortly before expiry; protect
the host cache and do not expose it to clients. Do not enable HTTP debug logging
of Authorization headers or request bodies. Requests have bounded timeouts.

PushMessage projects title/body and string-valued data: schema, id (logical),
type, actions (JSON), plus notification_id when a personal inbox ID exists.
Arbitrary payload data, actor and subject are excluded. The adapter conservatively
rejects an encoded message request above 4096 bytes. Do not put private action
data into a push notification. No notification_id is invented without inbox.
UNREGISTERED permanently invalidates the managed token; temporary failures do not.
Provider message name becomes a tracking reference, never proof of client display.

Official references:
[FCM HTTP v1](https://firebase.google.com/docs/cloud-messaging/send/v1-api) and
[Google Auth PHP](https://github.com/googleapis/google-auth-library-php).

### External destinations and drivers

```php
namespace App\Notifications;

use Elibardev\NotificationOrchestrator\Contracts\PushDestinationResolver;
use Elibardev\NotificationOrchestrator\NotificationContext;
use Elibardev\NotificationOrchestrator\Push\PushDestination;

final class ExternalPushDestinations implements PushDestinationResolver
{
    public function __construct(private ApplicationPushDirectory $directory) {}

    public function resolve(object $recipient, NotificationContext $context): iterable
    {
        foreach ($this->directory->tokensFor($recipient) as $token) {
            yield new PushDestination($token, 'fcm');
        }
    }
}
```

ApplicationPushDirectory and tokensFor are application-supplied, not package APIs.
Set push.destination_resolver to this named class. The directory must enforce
current owner association; external invalidation/reassignment belongs to that
integration. Leave deviceId null for external destinations. A non-null deviceId
selects a managed record when devices is enabled.

Custom drivers implement Contracts\PushDriver:
send(PushDestination, PushMessage): PushResult, validateConfiguration(): void and
health(): ChannelHealth. Register in an application provider before boot validation:

```php
use App\Notifications\OtherPushDriver;
use Elibardev\NotificationOrchestrator\Push\PushDriverRegistry;

app(PushDriverRegistry::class)->register('other', OtherPushDriver::class);
```

Then select push.default_driver and/or that destination's driver name. Registration
never replaces an existing name. FCM remains an adapter; no Core modification is
needed. Tests/Fixtures/FakePushDriver.php is a minimal executable implementation.

## Managed devices, identity and logout

Device resources are scoped to Contracts\AuthenticatedNotifiableResolver, also
used by inbox/preferences/private personal auth. Defaults derive the owner from
the authenticated Laravel notifiable, never a request user_id. No users table,
FK, session, Passport/Sanctum token or email column is created or required.

With features.devices, these routes exist under api.prefix (api/notifications):

| Method | Suffix | Operation |
| --- | --- | --- |
| GET | /devices | List this owner's safe projections |
| POST | /devices | Register/rotate/reassign |
| PATCH | /devices/{device} | Update name/enabled |
| DELETE | /devices/{device} | Disable, idempotently while owned |

These routes also work with features.api=false and database=false. Middleware
defaults to web/auth; session mutations require CSRF. A stateless host can
configure its own authenticated middleware. Add application throttling and abuse
controls before exposing registration publicly.

POST fields: driver, token, platform (ios/android/web/desktop/unknown),
optional random UUID device_identifier and name. Token max 4096 bytes, name max
255 characters. Responses never return token or token_hash. PATCH cannot change
owner/token/driver. DELETE does not destroy the account or authentication session.

Use a randomly generated installation UUID, never IMEI/serial. Register after
login and token refresh. Disable the returned device ID before logout, then clear
the client inbox/Echo listeners and authentication. Invalidation disables tokens;
the package does not perform an account-wide logout automatically.

Database uniqueness is driver+token_hash and driver+device_identifier. Registration
is atomic and retries uniqueness conflicts. Rotation preserves the matching
installation row; if two old rows converge, the other row is invalidated. An
authenticated registration may reassign an endpoint to the new owner.

Tokens are encrypted with Laravel's Encrypter; token_hash is SHA-256. A valid
APP_KEY/cipher is required even when push is disabled. Protect backups and plan
Laravel encryption-key rotation; deleting an old key can make stored tokens unreadable.
Destinations are frozen at planning, but managed sending locks/rechecks the current
owner, driver, token fingerprint and enabled/non-invalidated state. A stale job
fails without calling the provider. A short provider call occurs under that lock
to prevent reassignment between check and send. This cannot recall an already
accepted push, and it does not provide exactly-once delivery.

Invalidated-device pruning is off by default. Set
devices.prune_invalidated_after_days to a positive integer to enable it:

```sh
herd php artisan notifications:prune --only=devices --dry-run
herd php artisan notifications:prune --only=devices
```

Only disabled, invalidated records older than the cutoff qualify. Enabled devices
and ordinary logout-disabled devices without invalidated_at are preserved.

## MQTT and contextual delivery

Enable features.mqtt and configure mqtt.host, port, username/password, tls and
timeout (1–60 seconds). Host is a hostname/IP, not a URL. TLS verifies certificate
and hostname; use the host PHP trust store. No insecure bypass is provided.
Use TLS and least-privilege broker credentials for deployments beyond local tests.

The adapter uses [php-mqtt/client](https://github.com/php-mqtt/client) through
Mqtt\MqttClientFactory and Contracts\MqttDriver. Mosquitto is the reference broker.
It uses a fresh clean connection per publication, no payload logger and no broker
persistence table. Broker ACLs must protect personal/context topics.

Personal default topic: notifications/{notifiable}/{id}, using a portable morph
alias and encoded identity, never a raw PHP namespace. Override mqtt.personal_topic
or channels.destinations.mqtt for an application mapping. Personal MQTT follows
requested channels, optional preferences and presence.

```php
use Elibardev\NotificationOrchestrator\Context\ContextTarget;
use Elibardev\NotificationOrchestrator\Facades\Notify;

Notify::make('record.changed')->title('Changed')->message('Refresh this record.')
    ->recipients($accounts)
    ->channels(['mqtt'])
    ->broadcastTo('records.123')
    ->contextTo(ContextTarget::mqtt('records/123', qos: 1, retain: false))
    ->send();
```

broadcastTo additionally needs features.broadcast and a valid Laravel broadcast
connection. It publishes notification.context on PrivateChannel('records.123')
with the unchanged semantic payload/logical ID. The host owns context channel
authorization via its Laravel broadcasting routes; the package's personal auth
endpoint deliberately rejects arbitrary contexts. No wildcard grant is installed.

Context MQTT/broadcast ignore personal preferences and presence; they do not add
inbox rows, unread counts or per-subscriber tracking. They execute after commit.
QoS defaults to 1, retain=false. QoS 1 requires the client's pending publish queue
to clear after PUBACK; an elapsed wait with pending messages fails. QoS 0 reports
transport write acceptance without a broker acknowledgement. Neither proves a
subscriber rendered/read the message. MQTT is not a replacement for APNs/FCM.

## Optional presence

```php
namespace App\Notifications;

use Elibardev\NotificationOrchestrator\Contracts\PresencePolicy;
use Elibardev\NotificationOrchestrator\NotificationContext;
use Elibardev\NotificationOrchestrator\Recipients\RecipientIdentity;

final class ActiveContextPolicy implements PresencePolicy
{
    public function __construct(private ApplicationPresence $presence) {}

    public function suppress(RecipientIdentity $recipient, NotificationContext $context, string $channel): bool
    {
        return $channel === 'push' && $this->presence->isViewing($recipient, $context);
    }
}
```

ApplicationPresence/isViewing are host-defined. Configure features.presence=true
and presence.policy=ActiveContextPolicy::class. Host code authenticates presence
updates and implements ephemeral TTL expiry. No presence tables, polling or active
context endpoints are added by the package.

The policy runs once after preferences and before destination resolution; it can
only suppress optional channels, using SkipReason::PRESENCE ('presence').
Database/broadcast and context deliveries are unaffected. Presence never marks
read, and queued plans are not recalculated when presence changes.
Notify::fake() executes this policy and all normal planning, but suppresses sends.
Enabled providers still require valid configuration; bind fake drivers/clients
in tests instead of supplying real credentials.

## Verification and operational limits

The normal CI does not connect to SMTP, FCM, Mosquitto or Reverb. Automated coverage
includes real Google Auth JWT signing with a generated disposable test key and
HTTP fakes; FCM acceptance/permanent/transient failure; native MQTT client ACK,
timeout/QoS/TLS settings; Laravel Mail; all-channel commit/rollback; an actual
database queue worker rejecting a reassigned managed destination; CSRF/ownership;
all four migration publications/execution; and a 150-recipient batch.

Run from this package checkout:

```sh
herd composer check
herd composer check-platform-reqs
herd composer audit
npm test
```

Local closure evidence (2026-08-28): composer check passed on Herd PHP 8.2.31;
93 tests / 464 assertions, also passing in random order with seed 828; PHPStan
level 8 (168 files) and Pint passed. All six Node tests and Composer platform
requirements passed. All four migration stubs passed PHP lint, and 73 Markdown
files had no broken relative links. Remote CI had not run at this phase boundary;
the subsequent private GitHub upload and CI checks are recorded in TESTING.md.
Composer audit initially failed on Packagist DNS; a second online
`herd composer audit --locked` completed with no security vulnerability advisories.

The batch is regression coverage, not a throughput benchmark. Planning retains
recipient/destination snapshots in memory; use host application batches for large
audiences. Per-recipient/channel jobs bound worker work; multiple destinations
remain inside one job. Tracking retries reuse stable identities and skip already
successful destinations. Protect Laravel queue payloads: they contain destination
snapshots and may contain raw tokens. Encryption at rest for device rows does not
automatically encrypt jobs or failed_jobs.

Status reports configuration/storage readiness; HEALTHY is not a live reachability
probe. Provider failures use sanitized messages, structured dispatch/correlation
IDs and existing delivery lifecycle events. Applications may translate those
events into their monitoring system; no Prometheus/OTel dependency is added.

The verified database engine is SQLite. MySQL/MariaDB/PostgreSQL are not claimed
as verified. The SQL uses Laravel schema/query abstractions, but a target
deployment must validate its engine, index limits, permissions and queue worker.
The after-commit crash window and provider-accepted-before-tracking crash window
remain documented limitations. No outbox or exactly-once guarantee is introduced.

### Separate opt-in live profiles

tests/live/smoke.php is outside PHPUnit's default test discovery. It refuses to
run without NOTIFICATIONS_LIVE_ACK=approved-test-destination, a non-production
Laravel application root, an enabled feature and NOTIFICATIONS_LIVE_DESTINATION.
Set these variables in a private test environment, never source control or logs.
The destination is an address for mail, a test token for fcm, a topic for mqtt, or
a private channel name without private- for broadcast.

```sh
herd php tests/live/smoke.php /path/to/isolated-laravel-app fcm
```

Choose exactly one profile: mail, fcm, mqtt or broadcast. The host must already
include this package and configured test credentials. These profiles send one
message through the selected real adapter without inbox/tracking writes; they
do not prove subscriber rendering. Start an authorized test subscriber/client
separately and record its reception. Never use production recipients.

Only the refusal guard was executed locally. No live credentials were provided,
and no live SMTP/FCM/Mosquitto/Reverb delivery or deployment is claimed.
