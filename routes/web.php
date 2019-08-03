<?php

use App\User;
use App\LetterTemplate;
use Illuminate\Support\Facades\Mail;
use App\Mail\EmailConfirm;

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
$router->group(['middleware' => 'auth'], function () use ($router) {
	$router->get('/user/{id}','UsersController@retrieve');
	$router->post('/user/update/{id}','UsersController@update');
	$router->post('/user/delete/{id}','UsersController@delete');

	$router->post('/letter/create','LettersController@create');
	$router->get('/letter/{id}','LettersController@retrieve');
	$router->post('/letter/update/{id}','LettersController@update');
	$router->post('/letter/delete/{id}','LettersController@delete');
});


$router->post('/register','UsersController@create');
$router->post('/activate/{id}/{token}', 'UsersController@activate');
$router->post('/forget', 'UsersController@forget');
$router->post('/change/{id}/{token}', 'UsersController@change');
$router->post('/login', 'UsersController@login');
$router->post('/logout', 'UsersController@logout');

$router->get('/get_fields/{id}','LetterTemplatesController@form');
$router->post('/nanikashira/{id}','LettersController@generate');
$router->post('/edit_letter/{id}', "LettersController@uploadLetter");