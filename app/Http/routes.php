<?php

/*
|--------------------------------------------------------------------------
| Application Routes
|--------------------------------------------------------------------------
|
| Here is where you can register all of the routes for an application.
| It is a breeze. Simply tell Lumen the URIs it should respond to
| and give it the Closure to call when that URI is requested.
|
*/

$app->get('/', function () use ($app) {
    return $app->version();
});
$app->get(      '/test',                        'TestController@index');
$app->get(      '/test/odbc',                   'TestController@odbc');
$app->get(      '/test/{what}',                 'TestController@index');

$app->group(
    [
        'middleware' => 'CorsMiddleware',
        'namespace'  => 'App\Http\Controllers'
    ], function () use ($app) {

    $app->get('/firma', 'FirmaController@index');
    $app->get('/firma/set/{fa}/{fi}', 'FirmaController@set');
    $app->get('/firma/getLagers/{fa}/{fi}', 'FirmaController@getLagers');

    $app->get('/login/{fa}/{pnr}', 'UserController@pnr');
    $app->post('/login/{fa}', 'UserController@login');

    $app->get('/ewanapi/epc/{pc}', 'EwanapiController@epc');
    $app->get('/ewanapi/epc/{pc}/{fin}', 'EwanapiController@epc_fin');

    $app->get('/auftrag/get/{fa}/{fi}/{auf}/{fg}/{token}', 'AuftragController@get');
    $app->get('/fahrzeug/get/{fa}/{vin}/{token}', 'FahrzeugController@get');
    $app->get('/kunde/get/{fa}/{knr}/{token}', 'KundenController@get');

    $app->get('/teil/kundenpreis/{fa}/{kunden_nummer}/{fabrikat}/{teile_nummer}/{token}', 'TeileController@kundenpreis');
    $app->get('/teil/bestand/{fa}/{fi}/{fabrikat}/{teile_nummer}/{token}', 'TeileController@bestand');
    $app->get('/teil/read_list/{fa}/{fi}/{list}/{token}/{slash}', 'TeileController@read_list');
    $app->get('/teil/bezeichnung/{fa}/{fabrikat}/{teile_nummer}/{token}', 'TeileController@bezeichnung');
});
