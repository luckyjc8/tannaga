<?php

use App\User;

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

$router->get('/', function () use ($router) {
	$arr = ["hua"=>["hua2"=>"asdf"],"hau"=>"qwer"];
	$lt = new \App\LetterTemplate;
	$lt->name = "hua";
	$lt->someRandomAttr = $arr;
	$lt->save();
	dd(\App\LetterTemplate::all());

    return $router->app->version();
});
