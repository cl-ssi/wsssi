<?php

/** @var \Laravel\Lumen\Routing\Router $router */

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

$router->get('/', function () {
	//app('log')->channel('slack')->error('Hitting first log to slack');
    return 'hello world';
});

$router->get('/fonasa', 'FonasaController@certificate');
$router->get('/certificate', 'ExampleController@certificate');
$router->get('/test-certificate', 'ExampleController@testCertificate');
$router->post('/store-patient-on-fhir', 'ExampleController@storePatientOnFhir');
$router->post('/store-patient-as-temp', 'ExampleController@storePatientAsTemp');

$router->get('/rayen-urgencia', 'RayenUrgenciaController@getStatus');

$router->group(['prefix' => 'api/'], function () use ($router) {
    $router->get('login/', 'UsersController@authenticate');
    $router->post('todo/', 'TodoController@store');
    $router->get('todo/', 'TodoController@index');
    $router->get('todo/{id}/', 'TodoController@show');
    $router->put('todo/{id}/', 'TodoController@update');
    $router->delete('todo/{id}/', 'TodoController@destroy');
});
