<?php

use Phoenix\Router;
use Phoenix\Auth;
use Phoenix\ModuleLoader;

// Module API proxy routes
ModuleLoader::discover();
$installedModules = ModuleLoader::installed();
foreach ($installedModules as $id => $module) {
    $routeFile = ModuleLoader::apiRoutes($id);
    if ($routeFile) {
        Router::moduleApiProxy($id, $routeFile);
    }
}

// Core API
Router::group(['prefix' => '/api/v1', 'middleware' => ['auth']], function () {
    Router::get('/me', function () {
        json_response(['ok' => true, 'user' => Auth::user()]);
    });

    Router::get('/modules', function () {
        json_response(['ok' => true, 'modules' => ModuleLoader::all()]);
    });

    Router::get('/modules/installed', function () {
        json_response(['ok' => true, 'modules' => ModuleLoader::installed()]);
    });
});
