# ADR-0009 --- Preference inheritance and precedence

Status: **Accepted**

## Decision

Users without explicit preferences inherit defaults.

Preference precedence:

``` text
1. user preference for notification type + channel
2. user global preference for channel
3. application default for notification type + channel
4. application global default for channel
5. package default
```

The first defined value wins.

A missing preference means `inherit`, not `disabled`.

## Channel resolution

Optional delivery channels are the intersection of:

``` text
system capabilities
event-requested channels
effective user preferences
```

User preferences can suppress an optional event channel but cannot add
one.
