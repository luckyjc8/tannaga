<?php

namespace App;

use Jenssegers\Mongodb\Eloquent\Model as Eloquent;

class Letter extends Eloquent
{
   /* public function user(){
        return $this->belongsTo('User');
    }

    public function file($name){
    	return $this->where('name',$name)->get('path');
    }*/
}