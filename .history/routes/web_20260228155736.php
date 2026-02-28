<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// Serve in-app Swagger UI that loads the OpenAPI spec from /openapi.yaml
Route::get('/docs', function () {
    return view('swagger');
});
