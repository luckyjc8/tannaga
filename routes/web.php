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

//admin part
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
	

	$router->get('/save_letter/{id}',"LettersController@saveLetter");
	$router->get('/preview/{id}/{filename}','LettersController@preview');

	$router->post('/generate/{id}','LettersController@generate');
	$router->post('/finalize','LettersController@finalize');
	$router->post('/edit_letter/{id}', "LettersController@uploadLetter");
	$router->post('/email_letter','LettersController@emailLetter');
	$router->post('/download_letter','LettersController@downloadLetter');

	$router->get('/index_dir[/{dir:.*}]','LettersController@indexDirContent');
	$router->post('/move_letter/{letter_id}','LettersController@mvLetter');
	$router->post('/del_letter/{letter_id}','LettersController@delLetter');
//});

$router->post('/login', 'UsersController@login');
$router->post('/register','UsersController@register');


$router->get('/activate/{id}/{token}', 'UsersController@activateAccount');
$router->post('/forget', 'UsersController@forgetPassword');
$router->post('/change/{id}/{token}', 'UsersController@changePassword');
$router->get('/check_cp_token/{id}/{token}','UsersController@checkChangePasswordToken');



$router->get('/', function(){
   return redirect("https://www.tannaga.com");
});

$router->get('/restart_all_system', 'LettersController@reset');



//new function
$router->get('/getName', 'UsersController@getName');
$router->get('/getAllDirs', 'LettersController@getAllDirs');
$router->get('/getAllFiles', 'LettersController@getAllFiles');
$router->get('/getHistory', 'LettersController@getHistory');
$router->post('/makeDir', 'LettersController@makeDir');

/*// API route group
$router->group(['prefix' => 'api'], function () use ($router) {
     // Matches "/api/register
    $router->post('register', 'AuthController@register');

      // Matches "/api/login
     $router->post('login', 'AuthController@login');
});*/

// API route group
$router->group(['prefix' => 'api'], function () use ($router) {
    // Matches "/api/register
   $router->post('register', 'AuthController@register');
     // Matches "/api/login
    $router->post('login', 'AuthController@login');

    // Matches "/api/profile
    $router->get('profile', 'UserController@profile');

    // Matches "/api/users/1 
    //get one user by id
    $router->get('users/{id}', 'UserController@singleUser');

    // Matches "/api/users
    $router->get('users', 'UserController@allUsers');
});
