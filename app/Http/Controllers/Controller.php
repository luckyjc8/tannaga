<?php

namespace App\Http\Controllers;

use Illuminate\Routing\Controller as BaseController;

class Controller extends BaseController
{
    public function index()
    {
        return view('home');
    }

    public function about()
    {
        return view('about');	
    }
    
}
