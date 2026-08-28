<?php

declare(strict_types=1);

use Elibardev\NotificationOrchestrator\Contracts\NotificationRepository;
use Elibardev\NotificationOrchestrator\Facades\Notify;
use Elibardev\NotificationOrchestrator\NotificationAction;
use Elibardev\NotificationOrchestrator\NotificationOrchestratorServiceProvider;
use Elibardev\NotificationOrchestrator\Persistence\NotificationQuery;
use Elibardev\NotificationOrchestrator\Persistence\PackageSchema;
use Elibardev\NotificationOrchestrator\Persistence\Storage;
use Elibardev\NotificationOrchestrator\Tests\Fixtures\AuthenticatedAccount;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Contracts\View\Factory;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Application;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Route;

require dirname(__DIR__, 2).'/vendor/autoload.php';

if (PHP_SAPI !== 'cli' && ! in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true)) {
    http_response_code(403);
    exit;
}
$root = dirname(__DIR__, 2);
$cache = $root.'/.cache/browser';
$files = new Filesystem;
foreach (['views', 'sessions', 'logs', 'bootstrap/cache'] as $dir) {
    $files->ensureDirectoryExists($cache.'/'.$dir);
}
if (! $files->exists($cache.'/database.sqlite')) {
    $files->put($cache.'/database.sqlite', '');
}
if (! $files->exists($cache.'/key')) {
    $files->put($cache.'/key', 'base64:'.base64_encode(random_bytes(32)));
}

$factory = new class extends Orchestra\Testbench\Foundation\Application
{
    /** @param Application $app */
    protected function resolveApplicationConfiguration($app)
    {
        parent::resolveApplicationConfiguration($app);
        $cache = dirname(__DIR__, 2).'/.cache/browser';
        $app['config']->set([
            'app.key' => file_get_contents($cache.'/key'), 'app.env' => 'testing', 'app.debug' => true,
            'app.url' => 'http://127.0.0.1:8792', 'database.default' => 'sqlite',
            'database.connections.sqlite' => ['driver' => 'sqlite', 'database' => $cache.'/database.sqlite', 'foreign_key_constraints' => true],
            'queue.default' => 'sync', 'session.driver' => 'file', 'session.files' => $cache.'/sessions',
            'view.compiled' => $cache.'/views', 'logging.default' => 'single', 'logging.channels.single.path' => $cache.'/logs/laravel.log',
            'auth.defaults.guard' => 'fixture', 'auth.guards.fixture' => ['driver' => 'fixture'],
            'notification-orchestrator.features.broadcast' => true, 'broadcasting.default' => 'log',
            'notification-orchestrator.features.blade' => ! str_starts_with($_SERVER['REQUEST_URI'] ?? '', '/headless'),
            'notification-orchestrator.api.prefix' => str_starts_with($_SERVER['REQUEST_URI'] ?? '', '/headless') ? 'headless/notifications' : 'api/notifications',
            'notification-orchestrator.api.middleware' => ['web', 'auth:fixture'],
        ]);
    }
};
$app = $factory->configure(['extra' => ['providers' => [NotificationOrchestratorServiceProvider::class], 'env' => ['APP_RUNNING_IN_CONSOLE' => true]]])->createApplication();
Auth::viaRequest('fixture', fn () => new AuthenticatedAccount(['id' => 'browser-demo']));
if (! app(Storage::class)->available('notifications')) {
    app(PackageSchema::class)->create('notifications');
    Notify::make('example.created')->title('Notificación de prueba')->message('Inbox persistente en PHP 8.2.')->action(NotificationAction::navigate('open', 'Abrir destino', '/target'))->recipients(new AuthenticatedAccount(['id' => 'browser-demo']))->send();
}
if (PHP_SAPI === 'cli') {
    echo "Browser fixture ready.\n";
    exit;
}

Route::get('/vendor/notification-orchestrator/{kind}/{file}', function (string $kind, string $file) use ($root) {
    abort_unless(in_array($kind, ['js', 'css'], true) && preg_match('/^[a-z-]+\.(js|css)$/D', $file), 404);

    return response()->file($root.'/resources/'.$kind.'/'.$file, ['Content-Type' => $kind === 'js' ? 'text/javascript' : 'text/css']);
});
Route::middleware(['web', 'auth:fixture'])->group(function () {
    Route::get('/realtime', function () {
        return Blade::render('<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="csrf-token" content="{{ csrf_token() }}"><title>Dos clientes — Fase 2</title><script type="module" src="/fixture/echo.js"></script></head><body style="font-family:system-ui;margin:2rem"><h1>Prueba de sincronización</h1><p>API real · Transporte Echo simulado</p><button id="create">Crear notificación</button><button id="reconnect">Simular reconexión</button><button id="stale">Evento duplicado y antiguo</button><p id="fixture-status" role="status"></p><x-notifications::bell /><x-notifications::inbox /><x-notifications::toast-container /></body></html>');
    });
    Route::post('/fixture/create', function () {
        $owner = new AuthenticatedAccount(['id' => 'browser-demo']);
        Notify::make('example.live')->title('Notificación nueva')->message('El toast no marca lectura.')->action(NotificationAction::command('demo', 'Acción de aplicación'))->recipients($owner)->send();
        $item = app(NotificationRepository::class)->paginateFor($owner, new NotificationQuery)->items[0];

        return response()->json(['schema' => '1.0', 'event' => 'notification.created', 'notification' => $item->toArray()]);
    });
    Route::get('/', function () {
        return Blade::render('<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="csrf-token" content="{{ csrf_token() }}"><title>Fase 2 — prueba local</title></head><body style="font-family:system-ui;margin:2rem"><h1>Inbox de prueba</h1><p>Laravel · Herd PHP 8.2 · Sin Echo</p><x-notifications::bell /><x-notifications::inbox /><x-notifications::toast-container /><p><a href="/headless">Comprobar modo headless</a></p></body></html>');
    });
    Route::get('/headless', function () {
        $loaded = app(Factory::class)->exists('notifications::components.bell');

        return response('<!doctype html><html lang="es"><title>Headless</title><body><h1>Blade deshabilitado</h1><p>Vistas del paquete: '.($loaded ? 'cargadas' : 'no cargadas').'</p><a href="/headless/notifications/bootstrap">Ver API independiente</a></body></html>');
    });
    Route::get('/target', fn () => response('<h1>Destino de prueba autorizado</h1>'));
});
Route::get('/fixture/echo.js', fn () => response()->file(__DIR__.'/echo.js', ['Content-Type' => 'text/javascript']));
$kernel = $app->make(Kernel::class);
$request = Request::capture();
$response = $kernel->handle($request);
$response->send();
$kernel->terminate($request, $response);
