<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use PhpOffice\PhpWord\PhpWord;
use \PhpOffice\PhpWord\TemplateProcessor;
use App\LetterTemplate;
use Carbon\Carbon;

class LetterTemplatesController extends Controller{
	public function getFields($id){
    	$template = LetterTemplate::where('_id',$id)->first();
    	$file = new TemplateProcessor($this->path($template->name));
        $data['id'] = $id;
    	$fields = $file->getVariables();
    	unset($fields[array_keys($fields,'_now_date','strict')[0]]);
    	$data['vars'] = array_values($fields);
        $response = [
            "status" => "OK",
            "data" => $data
        ];
    	return response($response);
    }

    public function path($name){
    	return 'letter_template/'.$name.'/temp1.docx';
    }
}