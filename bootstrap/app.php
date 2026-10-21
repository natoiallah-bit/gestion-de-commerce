<?php

use App\Http\Middleware\EnsureCompteActif;
use App\Http\Middleware\EnsureGerant;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

$app = Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'gerant' => EnsureGerant::class,
            'actif' => EnsureCompteActif::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();

// Version « tout compris » pour Windows : le programme est installé en lecture seule,
// les données (base, journaux, fichiers temporaires) vont dans le dossier de l'utilisateur.
if ($donnees = getenv('ALGO_DONNEES')) {
    $app->useStoragePath($donnees.DIRECTORY_SEPARATOR.'storage');
    // Chemins Windows (C:\...) : Laravel ne les reconnaît pas comme absolus sans cela
    if (preg_match('/^[A-Za-z]:/', $donnees, $lecteur)) {
        $app->addAbsoluteCachePathPrefix($lecteur[0]);
    }
}

return $app;
