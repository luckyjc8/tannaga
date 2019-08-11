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

$router->group(['middleware'=>'auth'], function() use ($router){
	$router->get('/get_fields/{id}','LetterTemplatesController@getFields');
	$router->get('/test', function(){
		dd('test');
	});
	$router->post('/generate/{id}','LettersController@generate');
	$router->post('/logout', 'UsersController@logout');
	$router->post('/edit_letter/{id}', "LettersController@uploadLetter");
	$router->get('/index_letter', "LettersController@letterList");
});

$router->get('/nani',function(){
//	return "<h1>You've reached Tannaga api... There's nothing to do here...</h1>";
});

$router->post('/register','UsersController@register');
$router->post('/login', 'UsersController@login');
$router->get('/activate/{id}/{token}', 'UsersController@activateAccount');
$router->post('/forget', 'UsersController@forgetPassword');
$router->post('/change/{id}/{token}', 'UsersController@changePassword');
