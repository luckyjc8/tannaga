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

//$router->group(['middleware'=>'admin'], function() use ($router){
	$router->post('/create_template','LetterTemplatesController@create');
	$router->post('/new_template/{lt_id}','LetterTemplatesController@initTemplate');
	$router->post('/finalize_template','LetterTemplatesController@finTemplate');
//});

//$router->group(['middleware'=>'auth'], function() use ($router){
	$router->get('/get_fields/{id}','LetterTemplatesController@getFields');
	$router->post('/generate/{id}','LettersController@generate');
	$router->post('/logout', 'UsersController@logout');
	$router->post('/edit_letter/{id}', "LettersController@uploadLetter");
	$router->get('/save_letter/{id}',"LettersController@saveLetter");
	$router->get('/index_letter', "LettersController@letterList");
//});

$router->post('/register','UsersController@register');
$router->post('/login', 'UsersController@login');
$router->get('/activate/{id}/{token}', 'UsersController@activateAccount');
$router->post('/forget', 'UsersController@forgetPassword');
$router->post('/change/{id}/{token}', 'UsersController@changePassword');
$router->get('/check_cp_token/{id}/{token}','UsersController@checkChangePasswordToken');