# Instalación desde GitHub privado

Esta guía se ejecuta en el **proyecto Laravel consumidor**, no en la carpeta
del paquete. Requiere Laravel 12, PHP 8.2, Composer 2, Git y una base de datos
de pruebas. Para probar la API personal se necesita autenticación y un usuario
Eloquent persistido, o un AuthenticatedNotifiableResolver/RecipientNormalizer propio.

El repositorio es privado:
https://github.com/elibardev/laravel-notification-orchestrator

## 1. Acceso de Composer

La cuenta que instala debe tener acceso al repositorio. Para HTTPS, configurar
un token fine-grained restringido a este repositorio, con Contents: Read-only.
Guardar la autenticación fuera del composer.json, en el auth.json global de
Composer o en el gestor de secretos del entorno de CI.

No subir auth.json, tokens, .env ni credenciales, aunque el repositorio sea privado.
No escribir tokens en comandos que queden en el historial del terminal.

Referencia: [autenticación privada de Composer](https://getcomposer.org/doc/articles/authentication-for-private-packages.md#github-oauth).

## 2. Instalar en la aplicación

Desde la raíz de la aplicación Laravel:

```powershell
herd php --version
herd php artisan --version
herd composer config repositories.notification-orchestrator vcs https://github.com/elibardev/laravel-notification-orchestrator.git
herd composer require elibardev/laravel-notification-orchestrator:dev-main
```

La rama main se consume como dev-main. No hace falta publicar en Packagist,
crear una release ni añadir una propiedad version al composer.json del paquete.
Tampoco es necesario cambiar globalmente minimum-stability a dev.

El paquete declara su ServiceProvider en extra.laravel.providers y su autoload
PSR-4. Laravel lo descubre sin registrarlo manualmente. Si la aplicación usa
extra.laravel.dont-discover, comprobar que no excluya este paquete ni todos con "*".
No modificar archivos dentro de vendor.

## 3. Primera prueba: inbox y API

```powershell
herd php artisan notifications:install
```

Editar el archivo publicado config/notification-orchestrator.php:

- Mantener features.database y features.api en true.
- Cambiar features.queue a false para la primera prueba.
- Mantener las demás features deshabilitadas.
- Usar una base de datos de pruebas; no ejecutar estas pruebas en producción.

Después:

```powershell
herd php artisan config:clear
herd php artisan route:clear
herd php artisan migrate
herd php artisan notifications:status
herd php artisan route:list --path=api/notifications
```

Install publica configuración y sólo las migraciones de las features activas;
no ejecuta migraciones ni sobrescribe archivos existentes. Con esta configuración
se publica una migración del paquete y se crea notify_notifications, salvo que
se haya configurado otro prefijo/nombre. Artisan migrate también ejecuta otras
migraciones pendientes de la aplicación.

El middleware predeterminado es web/auth. La aplicación aporta login y sesiones;
las mutaciones requieren CSRF. Sanctum no es obligatorio. Con un guard distinto,
configurar api.middleware y el resolvedor de identidad según la aplicación.

Desde un controlador autenticado de pruebas:

```php
use Elibardev\NotificationOrchestrator\Facades\Notify;

Notify::make('test.created')
    ->title('Primera prueba')
    ->message('El paquete está funcionando.')
    ->recipients(auth()->user())
    ->send();
```

Consultar con ese mismo usuario autenticado:

- GET /api/notifications
- GET /api/notifications/unread-count
- GET /api/notifications/bootstrap

La API debe mostrar la notificación y el contador autoritativo. Usar la API o
Contracts\NotificationRepository: la relación Laravel $user->notifications sigue
apuntando a su tabla nativa y no se adapta automáticamente a notify_notifications.

Después activar y verificar Blade, preferencias, tracking, cola y proveedores
por separado. Volver a ejecutar notifications:install al habilitar features con recursos
publicables. Consultar [PHASE-2.md](PHASE-2.md) y [PHASE-3.md](PHASE-3.md).

## 4. Actualizar durante las pruebas

Después de subir nuevos commits del paquete:

```powershell
herd composer update elibardev/laravel-notification-orchestrator
```

Guardar el composer.lock actualizado de la **aplicación** para fijar el commit
probado. Un composer install usa ese lock, no necesariamente el último main.
Los cambios publicados de configuración/vistas/assets se revisan y aplican
manualmente; install no sobrescribe personalizaciones. Limpiar/reconstruir cachés
y reiniciar workers cuando corresponda.

Esta instalación no sustituye las validaciones con el motor SQL y proveedores
reales del proyecto. Las limitaciones conocidas están en PHASE-3.md.
