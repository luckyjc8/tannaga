<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Str;
use App\Letter;
use Carbon\Carbon;
use Illuminate\Support\Facades\File;
use Storage;
use \Exception;
use PhpOffice\PhpWord\PhpWord;
use \PhpOffice\PhpWord\TemplateProcessor;
use App\LetterTemplate;


class LettersController extends Controller
{
	//protected $fields = ['letter-template-id', 'attrs', 'path'];
	//fields diisi semua field kecuali id & timestamps

    public function uploadLetter($id){
    	//get letter file, upload to drive
        $letter = Letter::where('_id', $id)->first();
    	if($letter==null){
    		return ["status"=>"ERROR","msg"=>"Letter does not exist."];
    	}
    	$path = 'letters/'.$letter->user_id.'/'.$letter->name.'.docx';
    	$localfile = Storage::disk('local')->get($path);
    	$filename = $id.'.docx';   
    	Storage::cloud()->put($filename, $localfile);

        //get uploaded file from drive
	    $contents = collect(Storage::cloud()->listContents('/', false));
	    $file = $contents
	        ->where('type', '=', 'file')
	        ->where('filename', '=', pathinfo($filename, PATHINFO_FILENAME))
        	->where('extension', '=', pathinfo($filename, PATHINFO_EXTENSION))
	        ->first();

	    // Change permissions of that file
	    // - https://developers.google.com/drive/v3/web/about-permissions
	    // - https://developers.google.com/drive/v3/reference/permissions
	    $service = Storage::cloud()->getAdapter()->getService();
	    $permission = new \Google_Service_Drive_Permission();
		$permission->setRole('writer');
	    $permission->setType('anyone');
	    $permission->setAllowFileDiscovery(false);
        
        //get gdocs link
	    $rawLink = Storage::cloud()->url($file['basename']);
	    $rawLink = parse_url($rawLink, PHP_URL_QUERY);
	    $fileId = substr(explode('&',$rawLink)[0],3);
	    $realLink = "https://docs.google.com/document/d/".$fileId."/edit";
    	$response = [
			"status" => "OK",
			"msg" => "File uploaded.",
			"link" => $realLink
		];
    	$permissions = $service->permissions->create($file['basename'], $permission);
    	return response($response);
    }

    public function saveLetter($id){
    	$letter = Letter::where('_id', $id)->first();
        if (!$letter) {
            return response(["status"=>"ERROR","msg"=>"Letter does not exist."]);
        }
    	$filename = $id.'.docx';

	    $recursive = false;
        $contents = collect(Storage::cloud()->listContents('/', $recursive));
        $file = $contents
            ->where('type', '=', 'file')
            ->where('filename', '=', pathinfo($filename, PATHINFO_FILENAME))
            ->where('extension', '=', pathinfo($filename, PATHINFO_EXTENSION))
            ->first();
        if (!$file) {
            return response(["status"=>"ERROR","msg"=>"File does not exist."]);
        }
	    $rawData = Storage::cloud()->get($file['path']);
        Storage::disk('local')->put('/letters/'.$letter->user_id.'/'.$filename,$rawData);

        $response = [
            "status" => "OK",
            "msg" => "Letter saved."
        ];
	    return response($response);
    }

    public function letterList(Request $request){
        $letters = Letter::where('user_id', $request->header("user_id"))->get();
        $response = [
            "status" => "OK",
            "data" => $letters
        ];
        return $response;
    }

    public function generate(Request $request,$id){
        $uid = $request->header("user_id");
        $values = $request->request->all();
        $lt = LetterTemplate::where('_id',$id)->first();
        if($lt==null){
    		return ["status"=>"ERROR","msg"=>"Letter template does not exist."];
    	}
        for($i=1 ; $i<=$lt->count;$i++){
            $file = new TemplateProcessor('letter_template/'.$lt->name.'/temp'.$i.'.docx');
            $vars = $file->getVariables();
            $vars = array_diff($vars,array("_now_date"));
            if (count($values) !== count($vars)){
                return ["status"=>"ERROR","msg"=>"Incomplete parameters."];
            };
            $form_data = [];
            $keys=array_keys($values);
            $j=0;
            foreach($vars as $var){
                $s = str_replace(' ', '_', $var);
                try {
                	if(strpos($s, "datetime_") !== FALSE){
	                    $form_data[$keys[$j]] = $this->dateTimeToIndo(Carbon::createFromFormat('Y-m-d', $values[$s]));
	                }
	                else{
	                    $form_data[$keys[$j]] = $values[$s];
	                }	
                } catch (Exception $e) {
                	
                }
                $j++;
            }
            $path = "users/".$uid."/temp/exam".$i.".docx";
            $file->setValue($vars,$form_data);
            $now = $this->dateTimeToIndo(Carbon::now()->setTimezone('Asia/Jakarta'));
            $file->setValue("_now_date",$now);
            $file->saveAs($path);
            $localfile = Storage::disk('public')->get('/'.$path);
            Storage::disk('local')->put($path, $localfile);
            Storage::disk('public')->delete($path);
        }
        $data['template'] = $lt;
        $data['user'] = 'user1';
        $data['id'] = $uid;
        $response = [
            "status" => "OK",
            "data" => $data
        ];
        return response($response);
    }

    public function dateTimeToIndo($dt){
        $mon = "";
        $res = $dt->day." ";
        switch($dt->month){
            case 1: $mon = "Januari"; break;
            case 2: $mon = "Februari"; break;
            case 3: $mon = "Maret"; break;
            case 4: $mon = "April"; break;
            case 5: $mon = "Mei"; break;
            case 6: $mon = "Juni"; break;
            case 7: $mon = "Juli"; break;
            case 8: $mon = "Agustus"; break;
            case 9: $mon = "September"; break;
            case 10: $mon = "Oktober"; break;
            case 11: $mon = "November"; break;
            case 12: $mon = "Desember"; break;
        }
        $res .= $mon." ".$dt->year;
        return $res;
    }
}