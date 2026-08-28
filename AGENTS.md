# AGENTS.md
## Elibardev Laravel Notification Orchestrator

Estas instrucciones aplican a todo el repositorio.

## Proyecto
- Vendor: `elibardev`
- Package: `laravel-notification-orchestrator`
- Composer: `elibardev/laravel-notification-orchestrator`
- Namespace: `Elibardev\NotificationOrchestrator`
- Licencia: MIT
- Release inicial: `0.1.0`
- Laravel: `^12.0`
- PHP: `>=8.2`
- Queue inicial: database
- Realtime recomendado: Laravel Reverb
- Broadcast abstraction: Laravel Broadcasting
- Tests: Orchestra Testbench
- Arquitectura: Single package / modular internals

La arquitectura ya fue diseñada. Implementarla fielmente, incrementalmente y con tests. La documentación en `/docs` y los ADR aceptados son la fuente de verdad.

## 1. Antes de modificar código
1. Analizar estructura, `composer.json`, código y tests.
2. Leer toda la documentación relevante de `/docs`.
3. Leer todos los ADR con estado `Accepted`.
4. Revisar README, CHANGELOG, ROADMAP e IMPLEMENTATION-PLAN si existen.
5. Determinar la fase implementada y el menor incremento coherente siguiente.
6. Ejecutar tests existentes antes de modificar código cuando sea posible.

Precedencia: ADR Accepted más reciente > especificación dedicada > ARCHITECTURE.md > README/ejemplos.

No cambiar silenciosamente API pública, payload, tablas, contratos o features. Si existe un bloqueo arquitectónico real, explicar, proponer la corrección mínima, documentarla mediante ADR y sólo después implementarla.

## 2. Principios
- Laravel-native first: Notifications, Queue, Broadcasting, Events, Container, Config, Console, Blade, HTTP Resources, Database y Encryption.
- Core no debe depender directamente de Reverb, Redis/Valkey, Mosquitto, Firebase, Pusher/Ably, SMTP específico ni motor SQL específico.
- Usar por defecto la misma BD de la aplicación; no requerir BD separada.
- Mantener un solo paquete Composer con módulos internos opcionales.
- No introducir conceptos de negocio como propiedad, incidencia, técnico, auditor, empleado, factura o jefatura en Core.

## 3. API pública
Objetivo:

```php
Notify::make('incident.created')
    ->title('Nueva incidencia')
    ->message('Se reportó una incidencia.')
    ->recipients($users)
    ->send();
```

Métodos previstos: `title`, `message`, `severity`, `occurredAt`, `actor`, `subject`, `data`, `mergeData`, `action`, `actions`, `recipients`, `except`, `channels`, `broadcastTo`, `contextTo`, `send`.

`send()` es el terminal fluido. No crear alias fluido `dispatch()`. La Facade es comodidad; toda funcionalidad debe ser inyectable. `send()` retorna `NotificationDispatchResult`, que representa orquestación/aceptación, no entrega final.

ADR-0034: el resultado es una instantánea inmutable del plan con `notificationId`, `correlationId`, `recipientCount`, `plannedQueueJobCount` y `contextDeliveryCount`. No usar el nombre anterior `queuedDeliveryCount`. El conteo de jobs corresponde a entregas personales previstas para cola asíncrona, no a destinos individuales ni trabajo contextual; excluye skips y ejecución síncrona.

## 4. Payload
Notification types pertenecen a la aplicación y aceptan `string|BackedEnum`, normalizados a string.

Schema inicial: `1.0`.

Requeridos por desarrollador: `type`, `title`, `message`, `recipients`.

Defaults: `severity=info`, `occurred_at=now()`, `data={}`, `actions=[]`.

Payload semántico: `schema`, `id`, `type`, `title`, `message`, `severity`, `occurred_at`, `actor`, `subject`, `data`, `actions`.

No incluir `read_at` en payload semántico.

ADR-0032: `payload.id` y `NotificationDispatchResult.notificationId` identifican la notificación lógica. Cada recipient tiene un `storedNotificationId` distinto cuando se planifica persistencia; será la PK del inbox y el `id` del recurso HTTP. `notification_id` en read/unread y push personal refiere al ID personal; en tracking/logs refiere al lógico. No mezclar las representaciones ni sobrescribir el ID semántico. Sin persistencia no inventar ID almacenado ni lectura.

Severity: `info`, `success`, `warning`, `error`. No equivale a prioridad.

Actions usan `actions[]` con `id`, `type`, `label`, `url?`, `data={}`. Tipos iniciales: `navigate`, `command`. Una action nunca concede autorización.

## 5. Recipients
Soportar usuario, colección, clase/instancia de resolver y closure. Múltiples llamadas componen.

Pipeline: normalize -> deduplicate -> exclusions -> filters -> final recipients.

Soportar `except()`. No asumir columnas `active`, `enabled`, `tenant_id`, `deleted_at` ni roles; eso pertenece a la aplicación.

## 6. Channels y Preferences
Estructurales: `database`, `broadcast`; el usuario no puede desactivarlos si están habilitados.

Opcionales: `push`, `mail`, `mqtt`, custom.

Entrega efectiva = system capability ∩ event/default requested channels ∩ effective preferences. Preferencias sólo pueden suprimir opcionales, no agregar canales no solicitados.

Precedencia: user type+channel > user global channel > app type default > app global default > package default. Ausencia = inherit.

## 7. Channel Registry
Toda resolución usa `ChannelRegistry`; no crear un switch central.

Built-ins: database, broadcast, mail, push, mqtt.

Definición incluye name, kind, driver, preferenceAware, requiresDestination, queueable, healthCheckable.

Registro duplicado lanza `ChannelAlreadyRegisteredException`. Nunca reemplazar silenciosamente. Canales externos deben integrarse sin modificar Core.

## 8. Configuración y health
Feature habilitada pero inválida = FAIL FAST. Feature deshabilitada no requiere configuración del proveedor.

Estados: HEALTHY, DEGRADED, INVALID.

Implementar `php artisan notifications:status`. Debe diagnosticar configuración inválida sin revelar secretos.

ADR-0033: archivo único `config/notification-orchestrator.php`, namespace `notification-orchestrator.*`. `features.<name>` es la única activación; eliminar/rechazar switches duplicados como `push.enabled`, sin aliases. Las secciones de módulos configuran comportamiento. `enabled` en registros/preferencias o políticas de retención conserva su significado propio. No activar dependencias silenciosamente. Combinar mapas recursivamente y reemplazar listas completas según esquema; conservar `false`, `null` y listas vacías válidas. Mantener compatibilidad con `config:cache`.

## 9. DeliveryPlan y Queue
Crear `DeliveryPlan` inmutable por recipient.

Skip reasons iniciales: `not_requested`, `disabled`, `user_preference`, `no_destination`.
ADR-0036 agrega `presence` para supresión opcional mediante PresencePolicy; no aplica a canales estructurales ni contextos.

Canal explícitamente desconocido = excepción. Caída runtime del proveedor = delivery failure.

Queue: un job por recipient + channel para opcionales/externos. Debe funcionar con `QUEUE_CONNECTION=database`. Redis/Valkey son opcionales.

## 10. Delivery Tracking
Feature opcional. Estados: planned, queued, processing, sent, delivered, failed, skipped.

Nunca asumir sent==delivered ni delivered==read. `delivered` sólo si proveedor lo confirma.

Defaults con tracking: database false, broadcast false, push true, mail true, mqtt true. No almacenar destinos sensibles en claro.

Tracking usa `notification_id` lógico, no FK a la PK del inbox. Debe funcionar sin persistencia del inbox; no agregar tabla de dispatches por esta decisión. Pruning relacionado con una fila personal se limita por ID lógico y recipient, nunca por ID lógico solamente.

## 11. Persistence/read state
Database es estructural cuando está habilitado y no puede desactivarse por preferencia.

BD es source of truth. `read_at NULL` = unread; no-null = read. Estado por recipient, no dispositivo.

`markRead`, `markUnread`, `markAllRead` idempotentes.

Push mostrado, broadcast recibido, toast, MQTT publicado, email enviado o provider accepted NO implican read.

## 12. Multi-device/realtime
Eventos personales: `notification.created`, `notification.read`, `notification.unread`, `notification.read_all`.

Eventos/respuestas relevantes incluyen `meta.unread_count` autoritativo. Clientes asignan el valor del servidor. Realtime acelera sincronización; DB/API permiten recuperación tras desconexión.

## 13. Context Delivery
Separar Recipient Delivery de Context Delivery.

Convenience: `broadcastTo('incident.347')`.

General: `contextTo(ContextTarget::mqtt('incidents/347', qos:1, retain:false))`.

Preferencias de usuario no aplican a Context Delivery.

## 14. MQTT
Roles personal y contextual. Ejemplos: `notifications/users/{id}`, `incidents/{id}`.

Broker de referencia: Eclipse Mosquitto, pero implementación broker-neutral.

Context defaults: QoS 1, retain false. MQTT no sustituye FCM/APNs. Publish exitoso no prueba renderizado por subscribers.

## 15. Push/Devices
El paquete no administra users, sessions, Sanctum/Passport/auth tokens. Push destination != auth token.

`push` y `devices` son features separadas. Soportar managed devices y external `PushDestinationResolver`.

Managed token: cifrado; `token_hash` SHA-256. Usar `device_identifier` aleatorio de instalación, no IMEI/serial. Invalidar tokens permanentemente inválidos.

## 16. Blade/frontend
Blade es opcional y backend debe funcionar con `'blade' => false`.

Modos: Blade package UI, custom Web/SPA, headless/native.

Componentes opcionales: `<x-notifications::bell />`, `<x-notifications::inbox />`, `<x-notifications::toast-container />`.

Son adapters; no business logic. Cliente JS también opcional.

## 17. HTTP API
Inicial:
- GET `/notifications/bootstrap`
- GET `/notifications`
- GET `/notifications/unread-count`
- PATCH `/notifications/{notification}/read`
- PATCH `/notifications/{notification}/unread`
- POST `/notifications/read-all`

Preferences/Devices agregan endpoints sólo si feature activa. Middleware configurable; Sanctum no obligatorio. Owner se deriva de identidad autenticada, nunca de user_id arbitrario.

## 18. Security
Notification action != authorization. Recursos destino pasan por policies/reglas de aplicación.

Operaciones personales ownership-scoped. Managed device registration scoped al authenticated owner. Topic MQTT/canal Reverb no es autorización.

Nunca loggear raw push tokens, MQTT/SMTP passwords, Authorization headers, session/OAuth tokens ni provider secrets.

## 19. Install/lifecycle/retention
Comandos:
- `php artisan notifications:install`
- `php artisan notifications:status`
- `php artisan notifications:prune`

Install idempotente; no sobrescribir silenciosamente config/views/migrations. Composer nunca ejecuta migrations automáticamente.

Inbox: auto-prune deshabilitado por defecto. Deliveries: 90 días por defecto. Unread no se borra por política default.

Prune soporta `--dry-run`, `--only=deliveries`, `--only=notifications` y procesa en chunks.

## 20. Observability
Cada logical dispatch: `notification_id`, `correlation_id`. Delivery puede sumar `delivery_id`, `channel`, `provider`.

Usar structured Laravel logging; no payload completo por defecto. No requerir Prometheus/OpenTelemetry. Evitar labels de alta cardinalidad.

## 21. Versioning
Composer SemVer; línea actual 0.x.

Superficies independientes: PHP API, payload schema, realtime protocol, HTTP API, DB schema, frontend, Blade.

Payload 1.x sólo evolución backward-compatible. Migrations forward-only. Tras 1.0, breaking changes requieren major salvo correcciones de seguridad justificadas.

## 22. Public testing API
Implementar `Notify::fake()`.

Debe ejecutar validation, normalization, recipient resolution, exclusions, filters, preferences, DeliveryPlanning y ContextDeliveryPlanning; suprimir providers reales y persistencia normal por defecto.

El fake planifica en `send()` y devuelve el resultado inmediatamente; registra el envío para assertions sólo cuando correspondería ejecutar, después del commit si hay transacción. Antes del commit y tras rollback no hay envíos registrados. No repetir resolvers al hacer commit.

Assertions iniciales:
- `assertSent`
- `assertNotSent`
- `assertNothingSent`
- `assertSentTimes`
- `assertSentTo`
- `assertNotSentTo`
- `assertChannelPlanned`
- `assertChannelSkipped`
- `assertBroadcastTo`
- `assertContextSent`

Usar Orchestra Testbench. CI normal no requiere FCM, Mosquitto ni Reverb reales.

## 23. Transactions
ADR-0034: validar, normalizar, resolver recipients/preferencias/destinos y construir planes sin efectos de entrega durante `send()`. Congelar valores e identidades; cambios posteriores no reconstruyen el plan. Si hay transacción activa en la conexión de la aplicación, ejecutar persistencia, tracking, encolado y publicaciones después del commit exterior exitoso. Rollback descarta el trabajo de su ámbito. Sin transacción, ejecutar tras planificar. Aplica también con cola deshabilitada o síncrona, sin cambiar la configuración global de cola. Tests commit/rollback y transacciones anidadas obligatorios.

Los errores de planificación aparecen durante `send()`. Un error posterior al commit no revierte los datos de negocio. After-commit no garantiza durabilidad ante caída del proceso entre commit y encolado; outbox y transacciones distribuidas quedan fuera del alcance inicial.

## 24. Tables
Resolver nombres centralmente mediante `TableNameResolver` o equivalente.

Soportar global prefix + explicit per-table override. No concatenar prefijos independientemente en Models/Repositories/Queries.

## 25. Calidad y documentación
Usar PHP 8.2+ y Laravel 12. Preferir readonly value objects, clases pequeñas, constructor injection, interfaces en extension boundaries, enums para estados cerrados y excepciones claras.

Evitar God services, mutable global state, service locator abuse, provider-specific Core code, business-specific package code y abstracciones prematuras.

Cada incremento requiere tests. Ejecutar scripts equivalentes a `composer test`, `composer analyse`, `composer format` cuando estén configurados.

Código y documentación evolucionan juntos. Cambios arquitectónicos materiales requieren ADR.

## 26. Orden de implementación

Estado al 2026-08-28: fases 1–3 implementadas. `docs/PHASE-2.md`,
`docs/PHASE-3.md` y `docs/TESTING.md` registran pruebas y límites de despliegue.
ADR-0036 concreta PresencePolicy y el skip reason `presence` antes de su implementación.

Organización aprobada el 2026-08-27: **tres fases**, con incrementos internos pequeños y verificación por incremento. La secuencia detallada, dependencias y criterios de cierre están en `docs/IMPLEMENTATION-PLAN.md`; `docs/ROADMAP.md` es su resumen. Esta organización reemplaza las diez fases anteriores sin cambiar contratos, features ni tablas aprobadas.

**FASE 1 — Base y motor de orquestación:** skeleton, Composer, auto-discovery, ServiceProvider, config, Testbench y calidad; después objetos semánticos, identidad, recipients, registries, preferencias en memoria, planificación personal/contextual, API pública, `Notify::fake()`, diagnósticos iniciales y transacciones. Sin migraciones propias ni providers reales. El skeleton sigue siendo el checkpoint obligatorio 1.1 de la sección 28.

**FASE 2 — Inbox persistente, clientes y operación:** persistencia y preferencias, cola database real con canales de prueba, tracking, HTTP API, broadcast personal, Blade/JS/Echo opcionales y headless, instalación, status, prune y observabilidad. Tablas incorporadas: `notifications`, `preferences`, `deliveries`, según feature activa. Incluir ownership, concurrencia, recuperación y verificación en navegador.

**FASE 3 — Entregas externas e integración completa:** Mail, Push/FCM, Devices, MQTT personal/contextual, transporte contextual de Laravel Broadcasting, extensiones custom, presencia opcional ya contemplada y verificación final de combinaciones, seguridad, rendimiento y compatibilidad. Tabla incorporada: `devices`, sólo si se administra en el paquete. Los contratos de supresión por presencia aún no concretados requieren ADR antes de implementarse; no inventar nuevos skip reasons.

Implementar contratos/fakes antes de providers reales.

No interpretar tres fases como tres cambios masivos. Ejecutar tests relevantes en cada incremento y la suite completa, formato/análisis estático al cerrar. Conservar el checkpoint del skeleton y detenerse al terminar cada fase para presentar evidencia y esperar autorización explícita de la siguiente.

Seguridad, documentación y after-commit se prueban desde el incremento afectado. CI normal no depende de proveedores externos reales; su validación live se reporta por separado y no se presume a partir de fakes. No marcar una fase completa con verificaciones requeridas pendientes.

Total inicial: cuatro tablas propias. No agregar `dispatches`, `outbox`, `delivery_attempts` ni `context_deliveries`; `jobs`/`failed_jobs` siguen siendo infraestructura Laravel de la aplicación. Las fases no equivalen a versiones ni autorizan publicar releases o tags.

## 27. Definition of Done
Una tarea sólo está terminada si:
- cumple arquitectura aprobada;
- tiene tests;
- tests relevantes pasan;
- format/static analysis pasan cuando existan;
- no contiene cambios no relacionados;
- docs están actualizados cuando aplica;
- seguridad fue considerada;
- no se agregaron secretos.

Al finalizar reportar: cambios, archivos/clases, tests y resultados, docs/ADRs, riesgos/follow-ups.

## 28. Primera tarea
Si el repositorio está nuevo, NO implementar todo el Orchestrator.

Esta tarea es el incremento **1.1 de la Fase 1**, no toda la fase. La reorganización en tres fases no elimina este checkpoint.

Realizar únicamente:
1. analizar repositorio/documentación;
2. configurar `composer.json`;
3. package auto-discovery;
4. Orchestra Testbench;
5. PHPUnit o Pest;
6. code style;
7. static analysis;
8. ServiceProvider skeleton;
9. carga de configuración;
10. smoke tests Laravel 12;
11. estructura inicial;
12. README/CHANGELOG.

Después detenerse, presentar resultados y esperar autorización para continuar con los siguientes incrementos de la Fase 1.

Primer milestone: `clean + tested Laravel package skeleton`.
