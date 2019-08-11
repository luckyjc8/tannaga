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
    	if($template == null){
    		$response = [
    			'status' => "ERROR",
    			'msg' => 'Letter template does not exist.'
    		];
    	}
    	else{
    		$file = new TemplateProcessor($this->path($template->name));
	        $data['id'] = $id;
	    	$fields = $file->getVariables();
	    	unset($fields[array_keys($fields,'_now_date','strict')[0]]);
	    	$data['vars'] = array_values($fields);
	        $response = [
	            "status" => "OK",
	            "data" => $data
	        ];
    	}
    	return response($response);
    }

    public function create(Request $request){
        $lt = new LetterTemplate;
        if ($request->name == null){
            return ["status"=>"ERROR","msg"=>"No name."];
        }
        if (LetterTemplate::where("name", $request->name)->first() !== null){
            return ["status"=>"ERROR","msg"=>"Letter Template already exists."];
        }
        foreach ($request->all() as $req) {
            $lt->$req = $req;
        }
        $response = [
            "status" => "OK",
            "data" => $data
        ];
        return $response;
    }

    public function initTemplate(Request $request){
        $lt = LetterTemplate::where('_id', $request->lt_id)->first();
        $path = 'letter_template/Surat Kosong/empty.docx';
        $localfile = Storage::disk('local')->get($path);
        $filename = 'temp'.$lt->count+1.'.docx';

        //check dir user exist
        $dir = '/';
        $recursive = false;
        $contents = collect(Storage::cloud()->listContents($dir, $recursive));
        $dir = $contents->where('type', '=', 'dir')
            ->where('filename', '=', "Template dir")
            ->first();
        if (!$dir) {
            return ["status"=>"ERROR","msg"=>"Dir does not exist."];
        }

        //put to drive, then get the file in drive
        Storage::cloud()->put($dir['path'].'/'.$filename, $localfile);
        $contents = collect(Storage::cloud()->listContents($dir['path'], $recursive));
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
        return Response($response);
    }

    public function finTemplate(Request $request){
        $link = $request->link;
        $lt = LetterTemplate::where('_id', $request->lt_id)->first();
        //just do save letter
        $lt->count++;
    }
    public function path($name){
    	return 'letter_template/'.$name.'/temp1.docx';
    }
}