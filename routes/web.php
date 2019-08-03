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

$router->post('/register','UsersController@register');
$router->post('/login', 'UsersController@login');
$router->post('/logout', 'UsersController@logout');

$router->post('/activate/{id}/{token}', 'UsersController@activateAccount');
$router->post('/forget', 'UsersController@forgetPassword');
$router->post('/change/{id}/{token}', 'UsersController@changePassword');

$router->get('/get_fields/{id}','LetterTemplatesController@getFields');
$router->post('/generate/{id}','LettersController@generate');
$router->post('/edit_letter/{id}', "LettersController@uploadLetter");