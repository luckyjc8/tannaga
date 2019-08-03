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
    	$dir = '/';
	    $recursive = false;
	    $contents = collect(Storage::cloud()->listContents($dir, $recursive));
    	$letter = Letter::where('_id', $id)->first();
    	$path = 'letters/'.$letter->user_id.'/'.$letter->name.'.docx';
    	$dir = $contents->where('type', '=', 'dir')
	        ->where('filename', '=', "Test Dir")
	        ->first();
	    if (!$dir) {
	        return 'User does not exist!';
	    }
    	$filename = $id.'.docx';
    	$localfile = Storage::disk('local')->get($path);
    	Storage::cloud()->put($dir['path'].'/'.$filename, $localfile);
	    $recursive = false;
	    $contents = collect(Storage::cloud()->listContents($dir['path'], $recursive));
	    $file = $contents
	        ->where('type', '=', 'file')
	        ->where('filename', '=', pathinfo($filename, PATHINFO_FILENAME))
        	->where('extension', '=', pathinfo($filename, PATHINFO_EXTENSION))
	        ->first();
	    // Change permissions
	    // - https://developers.google.com/drive/v3/web/about-permissions
	    // - https://developers.google.com/drive/v3/reference/permissions
	    $service = Storage::cloud()->getAdapter()->getService();
	    $permission = new \Google_Service_Drive_Permission();
		$permission->setRole('writer');
	    $permission->setType('anyone');
	    $permission->setAllowFileDiscovery(false);
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
    	return Response($response);
    }

    public function saveLetter($id){
    	$letter = Letter::where('_id', $id)->first();
    	$filename = $letter->name;

	    $dir = '/';
	    $recursive = false;
	    $contents = collect(Storage::cloud()->listContents($dir, $recursive));

	    $file = $contents
	        ->where('type', '=', 'file')
	        ->where('filename', '=', pathinfo($filename, PATHINFO_FILENAME))
	        ->where('extension', '=', pathinfo($filename, PATHINFO_EXTENSION))
	        ->first();

	    //return $file; // array with file info

	    $rawData = Storage::cloud()->get($file['path']);

	    return response($rawData, 200)
	        ->header('ContentType', $file['mimetype'])
	        ->header('Content-Disposition', "attachment; filename='$filename'");
    }

    public function generate(Request $request,$id){
        $uid = 1;
        $vals = $request->request->all();
        $lt = LetterTemplate::where('_id',$id)->first();
        for($i=1 ; $i<=$lt->count;$i++){
            $file = new TemplateProcessor('letter_template/'.$lt->name.'/temp'.$i.'.docx');
            $vars = $file->getVariables();
            $vars = array_diff($vars,array("_now_date"));
            $form_data = [];
            $keys=array_keys($vals);
            $j=0;
            foreach($vars as $var){
                $s = str_replace(' ', '_', $var);
                try {
                	if(strpos($s, "datetime_") !== FALSE){
	                    $form_data[$keys[$j]] = $this->dateTimeToIndo(Carbon::createFromFormat('Y-m-d', $vals[$s]));
	                }
	                else{
	                    $form_data[$keys[$j]] = $vals[$s];
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
            //dd(Storage::disk('public')->getAdapter()->getPathPrefix());
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