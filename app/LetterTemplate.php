<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class LetterTemplate extends Model
{
    public function file($name){
    	return ($this->where('name',$name)->get('path'));
    }
}
