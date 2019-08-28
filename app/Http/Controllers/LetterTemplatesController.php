<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use PhpOffice\PhpWord\PhpWord;
use \PhpOffice\PhpWord\TemplateProcessor;
use App\LetterTemplate;
use Carbon\Carbon;
use Storage;

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
            $vars = [];
	    	foreach(array_values($fields) as $field){
                $temp = explode('_',$field);
                if(count($temp) == 1){
                    $vars[] = [
                        "name" => $temp[0]
                    ];
                }
                else if(count($temp)==2 && $temp[0]=='datetime'){
                    $vars[] = [
                        "datetime" => true,
                        "name" => $temp[0]
                    ];
                }
                else if(count($temp)==2){
                    $vars[] = [
                        "name" => $temp[0],
                        "desc" => $temp[1]
                    ];
                }
                else if(count($temp)==3){
                    $vars[] = [
                        "datetime" => true,
                        "name" => $temp[1],
                        "desc" => $temp[2]
                    ];
                }
                else{
                    return response(["status"=>"ERROR","msg"=>"Invalid document."]);
                }
            }
            $data['vars'] = $vars;
	        $response = [
	            "data" => $data,
                "status" => "OK",
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

    public function initTemplate($lt_id){
        $lt = LetterTemplate::where('_id', $lt_id)->first();
        $path = 'letter_template/Surat Kosong/empty.docx';
        $localfile = Storage::disk('public')->get($path);
        $filename = 'temp'.($lt->count+1).'.docx';
        $filepath = env('GOOGLE_DRIVE_TEMPLATE_FOLDER_ID').'/'.$filename;

        //put to drive, then get the file in drive
        Storage::cloud()->put($filepath, $localfile);
        $contents = collect(Storage::cloud()->listContents(env('GOOGLE_DRIVE_TEMPLATE_FOLDER_ID'), false));
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
            "link" => $realLink,
            "drive_link" => $file['path']
        ];
        $permissions = $service->permissions->create($file['basename'], $permission);
        return response($response);
    }

    public function finTemplate(Request $request){
        $link = $request->drive_link;
        $lt = LetterTemplate::where('_id', $request->lt_id)->first();
        $lt->count++;
        $lt->save();
        $file = Storage::cloud()->get($link);
        Storage::disk('public')->put('/letter_template/'.$lt->name.'/temp'.$lt->count.'.docx',$file);
        return response(["status"=>"OK","msg"=>"Letter template added."]);
    }
    public function path($name){
    	return 'letter_template/'.$name.'/temp1.docx';
    }
}