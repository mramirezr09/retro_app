<?php

/** @var App\Core\Router $router */

$router->get('/', 'DashboardController@index');

$router->get('/prompts', 'PromptController@index');
$router->get('/prompts/create', 'PromptController@create');
$router->post('/prompts', 'PromptController@store');
$router->get('/prompts/{id}/edit', 'PromptController@edit');
$router->post('/prompts/{id}', 'PromptController@update');
$router->post('/prompts/{id}/delete', 'PromptController@destroy');

$router->get('/upload', 'UploadController@index');
$router->post('/upload/preview', 'UploadController@preview');
$router->post('/upload/save', 'UploadController@save');

$router->get('/files', 'FileController@index');
$router->get('/files/{id}', 'FileController@show');
$router->post('/files/{id}/delete', 'FileController@destroy');
$router->get('/files/{id}/export', 'FileController@export');

$router->post('/api/records/send', 'RecordController@send');
$router->post('/api/records/reset', 'RecordController@reset');

$router->get('/settings', 'SettingsController@index');
$router->post('/settings', 'SettingsController@store');

