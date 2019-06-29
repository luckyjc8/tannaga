<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use App\User as User;

class Letter extends Model
{
    public function user(){
        return $this->belongsTo('User');
    }

    public function file($name){
    	return $this->where('name',$name)->get('path');
    }
}