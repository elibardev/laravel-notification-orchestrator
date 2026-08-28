# Guía para Codex: capacidades e integración en aplicaciones consumidoras

## Propósito

Esta guía ayuda a Codex y a los equipos de desarrollo a:

- comprender qué resuelve `elibardev/laravel-notification-orchestrator`;
- decidir si un cambio pertenece al paquete o a la aplicación consumidora;
- instalar y configurar el paquete sin modificar `vendor`;
- integrar destinatarios, acciones, canales, preferencias, API y realtime;
- centralizar canales y suscripciones sin crear clases innecesarias;
- diagnosticar problemas con evidencia antes de cambiar configuración;
- diseñar el uso de notificaciones para una aplicación concreta.

Esta guía no reemplaza los contratos públicos ni los ADR. Si existe conflicto,
aplicar esta precedencia:

```text
ADR Accepted más reciente
    > especificación dedicada
    > ARCHITECTURE.md
    > esta guía
    > README y ejemplos
```

Fuentes principales:

- [Arquitectura](ARCHITECTURE.md)
- [API pública](PUBLIC-API.md)
- [Configuración](CONFIGURATION.md)
- [Fase 2: inbox, API y frontend](PHASE-2.md)
- [Fase 3: proveedores y contextos](PHASE-3.md)
- [Instalación privada](PRIVATE-INSTALLATION.md)
- [Seguridad y autorización](SECURITY-AUTHORIZATION.md)
- [Índice de ADR](adr/README.md)

## Estado y límites actuales

- Compatibilidad declarada: Laravel `^12.0` y PHP `>=8.2`.
- Las fases 1–3 están implementadas.
- La línea inicial sigue siendo `0.x`; la API PHP todavía puede evolucionar.
- La versión `0.1.0` no está publicada. El consumo actual se hace desde el
  repositorio GitHub privado mediante `dev-main`.
- SQLite es el motor validado por la suite. MySQL, MariaDB y PostgreSQL requieren
  validación en la aplicación de destino.
- La suite automatizada usa clientes controlados. SMTP, FCM, Mosquitto y Reverb
  reales requieren una prueba de despliegue separada.
- `send()` confirma planificación y aceptación de la orquestación; no demuestra
  que un proveedor entregó o que una persona leyó la notificación.
- No hay garantía exactly-once ni outbox transaccional.

No presentar una configuración válida o un fake aprobado como evidencia de
entrega real.

## Modelo mental del paquete

La aplicación declara un evento semántico, sus destinatarios y su contenido. El
paquete normaliza esa intención y construye un plan inmutable de entrega.

```text
Evento de negocio confirmado
        |
        v
Notify::make(type)
        |
        +-- contenido y acciones
        +-- destinatarios y exclusiones
        +-- canales opcionales solicitados
        +-- contextos realtime
        |
        v
Validación + preferencias + destinos + DeliveryPlan
        |
        v
Después del commit exterior exitoso
        |
        +-- inbox persistente
        +-- broadcast personal
        +-- mail / push / MQTT
        +-- tracking opcional
        +-- broadcast / MQTT contextual
```

El paquete no sustituye Notifications, Queue ni Broadcasting de Laravel. Los
orquesta y agrega un contrato común de payload, inbox, preferencias, tracking,
realtime, dispositivos y proveedores.

## Responsabilidades: paquete frente a aplicación

### El paquete resuelve

- API fluida `Notify::make(...)->send()` y servicio inyectable;
- validación y payload semántico versionado;
- normalización, composición, deduplicación y exclusión de destinatarios;
- registro y planificación de canales;
- preferencias para canales opcionales;
- inbox persistente y estado leído/no leído por destinatario;
- contador no leído autoritativo;
- HTTP API con ownership sobre la identidad autenticada;
- eventos personales y cliente JavaScript opcional;
- ejecución después del commit;
- cola por destinatario/canal;
- Mail, FCM, dispositivos administrados y MQTT;
- broadcast y MQTT contextuales;
- tracking, status, instalación y pruning;
- `Notify::fake()` y assertions de aplicación.

### La aplicación consumidora resuelve

- cuándo existe un evento de negocio que merece notificación;
- los tipos semánticos, por ejemplo `incident.created`;
- quiénes son los destinatarios según roles, asignaciones o tenant;
- la autorización para abrir acciones y sus recursos;
- guards, sesiones, Sanctum/Passport o autenticación personalizada;
- políticas para canales contextuales;
- configuración de Laravel Queue, Broadcasting/Reverb y proveedores;
- contenido sensible permitido por cada canal;
- UI propia, si no utiliza Blade del paquete;
- estado autoritativo del dominio y recuperación tras reconexión;
- pruebas live y observabilidad del despliegue.

## Árbol de decisión para ubicar un cambio

Antes de editar, clasificar la necesidad:

| Necesidad | Lugar normal del cambio |
| --- | --- |
| Cambiar prefijo o nombre de tablas | Configuración del consumidor |
| Habilitar inbox, API, Blade, mail, push o MQTT | Configuración del consumidor |
| Definir quién recibe una incidencia | Resolver/filtro del consumidor |
| Cambiar una URL o comando de una notificación | Código de negocio del consumidor |
| Autorizar `incidents.{id}` | Broadcasting/policies del consumidor |
| Configurar Reverb, cola, SMTP, FCM o Mosquitto | Infraestructura/configuración del consumidor |
| Usar otro modelo de usuario o guard | Binding/configuración del consumidor |
| Agregar un transporte como SMS o Teams | Extensión registrada por el consumidor o paquete independiente |
| Corregir un defecto general del planner, payload o executor | Repositorio del paquete |
| Cambiar una API pública, tabla o protocolo para todos | Paquete + tests + documentación y, si aplica, ADR |

Reglas para Codex:

1. No modificar archivos dentro de `vendor`.
2. No agregar lógica de negocio específica al Core del paquete.
3. No duplicar en el consumidor una capacidad pública ya ofrecida por el paquete.
4. Si la necesidad es específica de una aplicación, preferir configuración,
   bindings, resolvers, policies y adapters de esa aplicación.
5. Solo proponer cambios al paquete cuando la limitación sea reusable y no pueda
   resolverse limpiamente mediante sus contratos de extensión.
6. No cambiar tablas, payload, rutas públicas o protocolos silenciosamente.

## Inspección obligatoria de una aplicación consumidora

Antes de implementar o recomendar cambios, reunir evidencia de:

1. Versiones de PHP, Laravel y Composer.
2. `composer.json`, `composer.lock` y forma de instalar el paquete.
3. `config/notification-orchestrator.php` publicado y configuración efectiva.
4. Estado de cachés de configuración y rutas.
5. Features activadas y salida de `notifications:status`.
6. Migraciones publicadas, tablas reales y motor SQL.
7. Modelo autenticado, morph alias, llave y guard utilizado.
8. Middleware del API y comportamiento CSRF/token.
9. Conexión de cola, tablas `jobs`/`failed_jobs`, worker y cola escuchada.
10. Conexión de broadcasting, Echo/Reverb y endpoint de autorización.
11. Modo de frontend: Blade, SPA, headless o móvil.
12. Tipos de notificación, resolvers y policies existentes.
13. Pruebas actuales y cambios no relacionados del worktree.

Comandos iniciales útiles, ejecutados desde la aplicación consumidora:

```powershell
herd php --version
herd php artisan --version
herd composer show elibardev/laravel-notification-orchestrator
herd php artisan notifications:status
herd php artisan route:list --path=api/notifications
herd php artisan migrate:status
```

No afirmar que un problema es de Reverb, cola, prefijo o permisos únicamente por
la configuración esperada. Confirmar la configuración efectiva, rutas, tablas,
logs sanitizados y ejecución correspondiente.

## Instalación privada en un consumidor

La entrada `repositories` pertenece al `composer.json` de la aplicación
consumidora, nunca al `composer.json` del paquete:

```powershell
herd composer config repositories.notification-orchestrator vcs https://github.com/elibardev/laravel-notification-orchestrator.git
herd composer require elibardev/laravel-notification-orchestrator:dev-main
```

El paquete declara auto-discovery; no registrar manualmente el Service Provider,
salvo que la aplicación haya deshabilitado el discovery. La autenticación de
Composer se guarda fuera del repositorio y nunca se incluyen tokens en comandos,
documentación, `composer.json` o `.env` versionado.

Después:

```powershell
herd php artisan notifications:install
herd php artisan migrate
herd php artisan notifications:status
```

`notifications:install` no ejecuta migraciones ni sobrescribe recursos existentes.
Al activar posteriormente una feature con recursos publicables, volver a ejecutar
el comando, revisar lo publicado y migrar conscientemente.

## Configuración canónica

El único archivo y namespace son:

```text
config/notification-orchestrator.php
notification-orchestrator.*
```

`features.<name>` es la única activación de capacidades:

```php
'features' => [
    'database' => true,
    'queue' => true,
    'broadcast' => false,
    'preferences' => false,
    'devices' => false,
    'push' => false,
    'mail' => false,
    'mqtt' => false,
    'delivery_tracking' => false,
    'presence' => false,
    'api' => true,
    'blade' => false,
],
```

No inventar activaciones duplicadas como `push.enabled`. Una feature deshabilitada
no necesita credenciales. Una feature habilitada pero inválida debe fallar de
forma explícita.

Los mapas se combinan recursivamente; las listas se reemplazan completas. Por
ejemplo, cambiar `api.middleware` reemplaza toda la lista y no agrega elementos a
la predeterminada.

## Prefijos y nombres de tablas

Los nombres se configuran en el consumidor:

```php
'database' => [
    'connection' => null,
    'table_prefix' => env('NOTIFICATIONS_TABLE_PREFIX', 'notify_'),
    'tables' => [
        'notifications' => null,
        'preferences' => null,
        'devices' => null,
        'deliveries' => null,
    ],
],
```

Con:

```env
NOTIFICATIONS_TABLE_PREFIX=sirhi_notify_
```

se obtienen, si las features correspondientes están activadas:

```text
sirhi_notify_notifications
sirhi_notify_preferences
sirhi_notify_devices
sirhi_notify_deliveries
```

Un override explícito reemplaza por completo el nombre generado:

```php
'tables' => [
    'notifications' => 'system_notifications',
    'preferences' => null,
    'devices' => null,
    'deliveries' => null,
],
```

No concatenar nombres manualmente en modelos, queries o repositorios. Dentro del
paquete todo se resuelve mediante `TableNameResolver`. En una integración,
preferir el repositorio/API pública del paquete en lugar de asumir nombres.

La relación nativa `$user->notifications` de Laravel continúa apuntando a la
tabla estándar de Laravel; no se redirige automáticamente al inbox del paquete.

Definir prefijos antes de publicar/migrar. Cambiar el prefijo con datos existentes
requiere una migración explícita de renombre o traslado; no basta con cambiar `.env`.
Las tablas `jobs` y `failed_jobs` pertenecen a Laravel y no reciben este prefijo.

## Uso básico y acciones

```php
use Elibardev\NotificationOrchestrator\Facades\Notify;
use Elibardev\NotificationOrchestrator\NotificationAction;

Notify::make('incident.created')
    ->title('Nueva incidencia')
    ->message('Se registró una nueva incidencia.')
    ->recipients($users)
    ->except($actor)
    ->action(NotificationAction::navigate(
        id: 'view_incident',
        label: 'Ver incidencia',
        url: route('incidents.show', $incident, absolute: false),
        data: ['incident_id' => $incident->getKey()],
    ))
    ->send();
```

Campos requeridos: `type`, `title`, `message` y `recipients`. Los tipos pertenecen
a la aplicación y aceptan `string|BackedEnum`.

Una acción `navigate` contiene una ruta relativa o HTTP(S). Una acción `command`
contiene un `id` y datos, y solo se ejecuta si el frontend registra un handler para
ese ID. Ninguna acción concede autorización: el destino debe aplicar middleware,
policies y reglas normales de la aplicación.

No colocar secretos o datos sensibles innecesarios en acciones, payloads externos
o mensajes push.

## Destinatarios sin clases innecesarias

Para casos sencillos, pasar modelos o colecciones directamente:

```php
->recipients($user)
->recipients($users)
->recipients($participants)
->except($actor)
```

Crear un `RecipientResolver` cuando la selección sea reusable, consultable o
compleja, por ejemplo roles + asignación + tenant. No crear un resolver para una
colección ya disponible en el servicio actual.

La aplicación no debe asumir columnas como `active`, `tenant_id`, roles o soft
deletes dentro del paquete. Esas reglas se implementan en resolvers/filtros del
consumidor y deben probarse con `Notify::fake()`.

## Canales personales y contextuales

### Canales de entrega registrados

El paquete registra los built-ins `database`, `broadcast`, `mail`, `push` y
`mqtt`. La aplicación los habilita/configura; no crea una clase por evento y
canal. Un transporte nuevo, como SMS o Teams, se registra una vez en el
`ChannelRegistry` desde un provider de aplicación o paquete de extensión.

### Canal personal

Existe un canal estable por identidad, no por notificación:

```text
notifications.{notifiable}.{id}
```

Transporta:

```text
notification.created
notification.read
notification.unread
notification.read_all
```

`GET /api/notifications/bootstrap` entrega el nombre personal y el endpoint de
autorización. El cliente del paquete se suscribe con `Echo.private(...)`. La
aplicación configura una sola instancia de Echo/Reverb y su autenticación.

### Canal contextual

Representa una pantalla o agregado de negocio:

```text
incidents.347
projects.15
tickets.900
```

La aplicación define la convención y autorización. El paquete solo publica:

```php
->broadcastTo("incidents.{$incident->getKey()}")
```

La regla de Laravel se define una vez por familia:

```php
Broadcast::channel(
    'incidents.{incident}',
    fn (User $user, Incident $incident): bool => $user->can('view', $incident),
);
```

El endpoint personal del paquete no autoriza contextos arbitrarios. Un nombre de
canal o topic nunca sustituye autenticación ni autorización.

## Arquitectura frontend recomendada

Evitar una instancia de Echo o una suscripción personal por componente. Usar:

```text
Una instancia de Echo
    |
    +-- un NotificationClient global
    |      `-- canal personal del usuario
    |
    `-- un ContextSubscriptionManager
           +-- incidents.347
           +-- project.15
           `-- referencias y cleanup
```

El cliente del paquete puede inicializarse una vez:

```javascript
const notifications = createNotificationClient({
    apiBaseUrl: '/api/notifications',
    echo: window.Echo,
});

await notifications.bootstrap();
```

El administrador contextual de la aplicación debería:

- mantener un mapa de suscripciones por nombre;
- evitar duplicados y usar conteo de referencias;
- asociar handlers por `payload.type`;
- abandonar el canal cuando ninguna pantalla lo utiliza;
- destruir listeners al cerrar sesión o cambiar de identidad;
- resincronizar el recurso desde HTTP al reconectar.

Centralizar nombres mediante una fábrica de backend o, preferiblemente, entregar
el canal autorizado en la respuesta del recurso. No dispersar concatenaciones
como `incident.*`, `incidents.*` e `incidencias.*`.

## Reconexión y fuente de verdad

Realtime acelera sincronización; no conserva un historial confiable. Los eventos
pueden perderse, duplicarse o llegar desordenados.

Para el inbox:

```text
Echo connected/subscribed
    -> volver a GET /bootstrap
    -> reemplazar items y meta.unread_count con el servidor
```

El cliente incluido ya sincroniza en señales de conexión/suscripción. No mantener
el contador únicamente con `++` o `--`.

Para un contexto:

```text
reconectar
    -> volver a suscribir
    -> GET del recurso autoritativo
    -> reconciliar UI
```

Para MQTT, reconectar, autenticar, volver a suscribir y recargar por API. QoS 1
puede duplicar mensajes; `retain=false` no recupera eventos perdidos. Diseñar
handlers idempotentes y deduplicar usando las identidades correctas.

## Identidades que no deben mezclarse

| Identidad | Uso |
| --- | --- |
| `notificationId` / `payload.id` | Notificación lógica compartida |
| `storedNotificationId` | Fila personal de inbox por destinatario |
| `correlationId` | Trazabilidad operacional |
| `deliveryId` | Entrega rastreada y estable entre reintentos |

La API personal, read/unread y deduplicación del inbox usan el ID personal. Los
contextos y tracking usan el ID lógico. No sobrescribir `payload.id` con la PK del
inbox ni asumir que leer una fila personal afecta a los demás destinatarios.

## Transacciones, cola y entrega

Durante `send()` se resuelven y congelan destinatarios, preferencias y destinos.
Si existe una transacción activa, la persistencia y entrega esperan el commit
exterior. Un rollback descarta el trabajo.

Llamar `send()` después de realizar los cambios de negocio relevantes dentro de
la transacción. Los resolvers no se ejecutan otra vez al hacer commit ni al
reintentar un job.

Un job se planifica por destinatario/canal; varios dispositivos del mismo canal
pueden permanecer dentro de ese job. Verificar que los workers escuchen la cola
configurada, cuyo nombre predeterminado es `notifications`.

## Estrategia de pruebas en la aplicación

Para reglas de negocio:

```php
Notify::fake();

$service->createIncident($data);

Notify::assertSentTo($supervisor, 'incident.created');
Notify::assertChannelPlanned($supervisor, 'mail');
Notify::assertBroadcastTo("incidents.{$incident->getKey()}");
```

El fake ejecuta validación, resolvers, exclusiones, preferencias y planning, pero
suprime proveedores y persistencia normal. Dentro de una transacción, las
assertions solo se vuelven verdaderas después del commit exterior; un rollback no
registra envío.

Separar niveles:

1. Tests de negocio con `Notify::fake()`.
2. Tests de repositorio/API con base de datos real de pruebas.
3. Tests de envelopes/Echo sin Reverb real.
4. Tests controlados de adapters con HTTP/Mail/MQTT fakes.
5. Smoke tests opt-in contra proveedores y destinos reales de prueba.

## Diagnóstico orientado a evidencia

| Síntoma | Verificar antes de cambiar |
| --- | --- |
| No aparece la tabla esperada | Config efectivo, prefijo, override, feature, migración publicada/ejecutada |
| Cambió `.env` pero sigue el nombre anterior | `config:clear`, config cache y necesidad de migrar/renombrar datos existentes |
| `$user->notifications` está vacío | Usa relación nativa; consultar API/repositorio del paquete |
| API devuelve 401/403 | Guard, middleware, identidad autenticada y ownership |
| Mutación devuelve 419 | Sesión, middleware `web` y token CSRF |
| Broadcast auth devuelve 403 | Canal solicitado, identidad/morph alias y endpoint de autorización |
| No llega realtime | Feature, conexión Laravel, Echo, auth, Reverb, worker y suscripción efectiva |
| Tras reconectar falta información | Recargar API; realtime no es historial |
| Correo/push/MQTT no sale | Feature, canal solicitado, preferencia, destino, status, queue y tracking |
| Hay jobs pero no avanzan | Worker, connection, nombre de queue, retries y `failed_jobs` |
| Acción no abre | `type`, URL segura, handler de command y policy del destino |
| Contador deriva entre pestañas | Usar `meta.unread_count` autoritativo y bootstrap tras reconexión |
| `send()` retornó pero no hay entrega | El resultado es planning; revisar commit, queue, tracking y proveedor |

`notifications:status` diagnostica configuración y almacenamiento. Un estado
`HEALTHY` no prueba conectividad live ni renderizado por el cliente.

## Recomendaciones al diseñar una aplicación concreta

### 1. Crear un catálogo semántico

Definir nombres estables, sin nombres de controladores o proveedores:

```text
incident.created
incident.assigned
incident.followup.created
incident.closed
```

Documentar por tipo: disparador, destinatarios, exclusiones, severidad, acción,
canales solicitados, contexto y datos permitidos.

### 2. Separar atención personal de sincronización contextual

- Personal: una persona debe encontrar la notificación más tarde.
- Contextual: una pantalla abierta necesita actualizarse ahora.

Una operación puede producir ambas. No convertir el canal contextual en inbox ni
usar el inbox como bus general de eventos de dominio.

### 3. Comenzar con el mínimo funcional

Orden recomendado:

1. Database + API, sin cola para la primera prueba controlada.
2. Acciones y tests de negocio.
3. Cola database y worker.
4. Broadcast personal y reconexión.
5. Blade o frontend propio.
6. Preferencias/tracking.
7. Mail, push, dispositivos o MQTT uno por uno.
8. Contextos y presencia solo si existe un caso real.

Ejecutar `notifications:install`, migraciones y `notifications:status` en cada
incremento que agregue recursos o proveedores.

### 4. Centralizar destinatarios por complejidad

- Directos: modelo/colección en el servicio.
- Reusables: `RecipientResolver` con nombre de negocio.
- Transversales: filtros de aplicación para tenant/estado/elegibilidad.

Probar deduplicación, exclusión del actor y tenant. No confiar en roles o columnas
que el paquete no conoce.

### 5. Diseñar acciones seguras y portables

Usar rutas relativas para Web cuando sea posible y datos mínimos para clientes
nativos. Cada destino vuelve a autorizar. Los comandos requieren handlers
registrados; no transportar callbacks, controladores ni métodos HTTP arbitrarios.

### 6. Elegir canales por intención

- `database`: inbox durable y leído/no leído.
- `broadcast`: sincronización personal inmediata.
- `mail`: atención fuera de la aplicación.
- `push`: atención del sistema operativo en dispositivos.
- `mqtt`: consumidores MQTT personales o contextuales.
- contexto broadcast/MQTT: refrescar un agregado compartido.

Las preferencias solo suprimen canales opcionales solicitados; no agregan canales
ni deshabilitan `database`/`broadcast` estructurales.

### 7. Diseñar reconexión desde el inicio

Toda pantalla realtime debe tener una operación HTTP que reconstruya su estado.
Los eventos son señales para refrescar o aplicar cambios idempotentes, no la única
fuente del estado.

### 8. Minimizar datos y respetar fronteras de seguridad

No enviar por push/MQTT información que no debería aparecer fuera de la sesión
Web. Nunca incluir tokens, credenciales o secretos. Proteger channels/topics con
policies/ACL; sus nombres no son permisos.

### 9. Diseñar operación y fallos

Definir queue workers, retries, `failed_jobs`, tracking, logs con correlation ID,
alertas y pruning. Distinguir `sent`, `delivered` y `read`. Probar qué ocurre si
el proveedor falla después del commit.

### 10. Validar el despliegue real

Antes de producción, validar el motor SQL objetivo, migraciones, índices, auth,
CSRF, queue worker, Reverb/Echo, TLS, ACL MQTT, FCM/SMTP, cache y reinicio de
workers. Usar cuentas, topics, tokens y destinatarios exclusivos de prueba.

## Formato recomendado para una especificación de integración

Antes de implementar una notificación concreta, completar:

```text
Aplicación:
Evento de negocio:
Tipo semántico:
Momento exacto de send():
Destinatarios:
Exclusiones:
Tenant/filtros:
Título y mensaje:
Severidad:
Acciones:
Canales estructurales habilitados:
Canales opcionales solicitados:
Preferencias aplicables:
Contexto realtime:
Autorización del contexto:
Estado autoritativo para reconexión:
Datos sensibles/proyección por canal:
Comportamiento dentro de transacción:
Pruebas con Notify::fake():
Pruebas de persistencia/API:
Prueba live requerida:
Observabilidad y recuperación:
```

Este formato permite que Codex identifique primero contratos, supuestos de schema,
límites de autorización y evidencia necesaria, y solo después proponga cambios.
