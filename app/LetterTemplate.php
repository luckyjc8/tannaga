<?php

namespace App;

use Jenssegers\Mongodb\Eloquent\Model as Eloquent;

class LetterTemplate extends Eloquent
{
    public function file($name){
    	return ($this->where('name',$name)->get('path'));
    }
}
