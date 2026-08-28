# ADR-0018 --- MQTT supports recipient and contextual delivery roles

Status: **Accepted**

## Decision

MQTT is a reusable transport and is not classified exclusively as an
optional recipient channel.

It may be used for:

``` text
recipient delivery
-> personal MQTT topic

context delivery
-> shared domain topic such as incidents/347
```

Context MQTT delivery does not resolve individual recipients and does
not apply recipient notification preferences.

A successful contextual publish is transport-level `sent`, not proof of
delivery to every subscriber.

Default contextual MQTT semantics:

``` text
QoS = 1
retain = false
```
