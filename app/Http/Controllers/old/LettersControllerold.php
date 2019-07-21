<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Letter;
use App\User;
use Storage;
use Illuminate\Support\Facades\File;

class LettersControllerold extends Controller
{
    public function create(Request $request,$id){
    	$letter = new Letter;
    	$old_path = 'users/'.$id.'/temp/'.$request->chosen_temp.'.docx';
    	$new_path = 'users/'.$id.'/'.$request->fname.'.docx';
    	$file = Storage::disk('public')->get($old_path);
    	Storage::disk('local')->put($new_path,$file);
    	$letter->name = $request->fname;
    	$letter->path = $new_path;
    	$letter->user_id = 1;
    	$letter->save();
    	return redirect('/user/'.$id.'/letter/'.$letter->id);
    }

    public function show($uid,$lid){
    	$letter = Letter::where('id',$lid)->first();
    	$data['uid'] = $uid;
    	$data['letter'] = $letter;
        return Storage::disk('local')->download($letter->path);
        //return view('show-letter',$data);
    }
}
