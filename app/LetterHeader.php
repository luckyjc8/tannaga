<?php

namespace App;

use Jenssegers\Mongodb\Eloquent\Model as Eloquent;

class LetterHeader extends Eloquent
{
    public function file($name){
    	return ($this->where('name',$name)->get('path'));
    }
}
