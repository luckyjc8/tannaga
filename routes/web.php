<?php

use App\User;
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

/*$router->get('/', function () use ($router) {
	$arr = ["hua"=>["hua2"=>"asdf"],"hau"=>"qwer"];
	$lt = new \App\LetterTemplate;	
	$lt->name = "hua";
	$lt->someRandomAttr = $arr;
	$lt->save();
	dd(\App\LetterTemplate::all());
    return $router->app->version();
});*/

$router->post('/user/create','UsersController@create');
$router->get('/user/{id}','UsersController@retrieve');
$router->post('/user/update/{id}','UsersController@update');
$router->post('/user/delete/{id}','UsersController@delete');

/*
$router->get('/test-email',function(){
	Mail::to('ftnfata@gmail.com')
		->send(new EmailConfirm("Mas... Anda cupu sekali..."));
});*/

$router->post('/letter/create','LettersController@create');
$router->get('/letter/{id}','LettersController@retrieve');
$router->post('/letter/update/{id}','LettersController@update');
$router->post('/letter/delete/{id}','LettersController@delete');


$router->group(['middleware' => 'auth'], function () use ($router) {
	
});