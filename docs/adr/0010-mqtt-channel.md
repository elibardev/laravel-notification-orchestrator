# ADR-0010 --- MQTT as an optional recipient delivery channel

Status: **Accepted**

## Context

The orchestrator should support MQTT-capable consumers without
conflating MQTT with Laravel contextual broadcasting or mobile
operating-system push.

## Decision

Add `mqtt` as an optional, preference-aware recipient delivery channel.

Eclipse Mosquitto is the reference self-hosted MQTT broker.

MQTT remains broker-agnostic at the package core and must be implemented
behind an internal contract so compatible brokers can be supported
later.

## Boundaries

``` text
database   -> structural persistence
broadcast  -> structural personal realtime
push       -> optional mobile/system push
mail       -> optional email delivery
mqtt       -> optional MQTT recipient delivery
broadcastTo() -> contextual Laravel Broadcasting
```

MQTT does not replace FCM/APNs for mobile push.

`broadcastTo()` does not select MQTT and remains based on Laravel
Broadcasting.
