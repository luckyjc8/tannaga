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
	$router->get('/check_docs','LetterTemplatesController@checkDocsValidity');
	$router->get('/check_docs_v','LetterTemplatesController@checkDocsValidityVerbose');
//});

//$router->group(['middleware'=>'auth'], function() use ($router){
	$router->post('/logout', 'UsersController@logout');
	$router->get('/get_fields/{id}','LetterTemplatesController@getFields');
	$router->post('/generate/{id}','LettersController@generate');
	$router->post('/finalize','LettersController@finalize');
	$router->post('/edit_letter/{id}', "LettersController@uploadLetter");
	$router->get('/save_letter/{id}',"LettersController@saveLetter");
	$router->get('/preview/{id}/{filename}','LettersController@preview');
	$router->post('/email_letter','LettersController@emailLetter');
	$router->post('/download_letter','LettersController@downloadLetter');

	$router->get('/index_dir[/{dir:.*}]','LettersController@indexDirContent');
	$router->post('/move_letter/{letter_id}','LettersController@mvLetter');
	$router->post('/del_letter/{letter_id}','LettersController@delLetter');
//});

$router->post('/register','UsersController@register');
$router->post('/login', 'UsersController@login');
$router->get('/activate/{id}/{token}', 'UsersController@activateAccount');
$router->post('/forget', 'UsersController@forgetPassword');
$router->post('/change/{id}/{token}', 'UsersController@changePassword');
$router->get('/check_cp_token/{id}/{token}','UsersController@checkChangePasswordToken');



$router->get('/', function(){
   return '<html><title>Tannaga API</title><body><h1> You have reached tannaga API. There is nothing to do here.</h1></body></html>' ;
});

$router->get('/restart_all_system', 'LettersController@reset');

$router->get('/test',function(){
	
});