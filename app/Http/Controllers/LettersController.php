<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Str;
use App\Letter;
use Carbon\Carbon;

class LettersController extends Controller
{
	protected $fields = ['letter-template-id', 'attrs', 'path'];
	//fields diisi semua field kecuali id & timestamps

	public function create(Request $request){
		$letter = new Letter;
		foreach($this->fields as $field){
			$letter->$field = $request->$field;
		}
		$letter->created_at = Carbon::now();
		$letter->updated_at = Carbon::now();
		$letter->deleted_at = null;
		$letter->save();

		$response = [
			"status" => "OK",
			"msg" => "Letter created."
		];
		return response($response);
	}

	public function retrieve($id){
		$letter = Letter::where('_id',$id)->first();

		$response = $letter==null || $letter->deleted_at!=null ?
		[
			"status" => "ERROR",
			"msg" => "Letter not found."
		]:
		[
			"status" => "OK",
			"data" => $letter->getAttributes()
		];
		return response($response);
	}

	public function update($id, Request $request){
		$letter = Letter::where('_id',$id)->first();
		foreach($this->fields as $field){
			$letter->$field = $request->$field;
		}
		$letter->updated_at = Carbon::now();
		$letter->save();

		$response = [
			"status" => "OK",
			"msg" => "Letter updated."
		];
		return response($response);
	}

	public function delete($id, Request $request){
		$letter = Letter::where('_id',$id)->first();
		$letter->deleted_at = Carbon::now();
		$letter->save();

		$response = [
			"status" => "OK",
			"msg" => "Letter deleted."
		];
		return response($response);
	}

	public function getFields($id){
    	$template = LetterTemplate::where('id',$id)->first();
    	$file = new TemplateProcessor($template->path.'/temp1.docx');
    	$data['vars'] = $file->getVariables();
        $new_vars = array_diff($data['vars'],array("_now_date"));
        $data['id'] = $id;
        $data['vars'] = $new_vars;
        $response = [
            "status" => "OK",
            "data" => [
                "id" => $id,
                "vars" => $vars
            ]
        ];
    	return response($response);
    }

    public function uploadLetter($id, $index){
    	$filename = Carbon::now().Str::random(10).'.docx';
    	$letter = Letter::where('_id', $id)->first();
    	$path = 'letter_template/'.$letter->name.'/temp'.$index.'.docx';
    	$localfile = Storage::disk('local')->get($path);
    	Storage::cloud()->put($filename, $localfile);
    	$dir = '/';
	    $recursive = false; // Get subdirectories also?
	    $contents = collect(Storage::cloud()->listContents($dir, $recursive));
	    $file = $contents
	        ->where('type', '=', 'file')
	        ->where('filename', '=', pathinfo($filename, PATHINFO_FILENAME))
        	->where('extension', '=', pathinfo($filename, PATHINFO_EXTENSION))
	        ->first(); // there can be duplicate file names!

	    // Change permissions
	    // - https://developers.google.com/drive/v3/web/about-permissions
	    // - https://developers.google.com/drive/v3/reference/permissions
	    $service = Storage::cloud()->getAdapter()->getService();
	    $permission = new \Google_Service_Drive_Permission();
	    $permission->setRole('writer');
	    $permission->setType('anyone');
	    $permission->setAllowFileDiscovery(false);
	    $permissions = $service->permissions->create($file['basename'], $permission);
    	$response = [
			"status" => "OK",
			"msg" => "File uploaded.",
			"link" => Storage::cloud()->url($file['path'])
		];
    	return Response($response);
    }

    public function saveLetter($id){
    	$letter = Letter::where('_id', $id)->first();
    	$filename = $letter->name;

	    $dir = '/';
	    $recursive = false; // Get subdirectories also?
	    $contents = collect(Storage::cloud()->listContents($dir, $recursive));

	    $file = $contents
	        ->where('type', '=', 'file')
	        ->where('filename', '=', pathinfo($filename, PATHINFO_FILENAME))
	        ->where('extension', '=', pathinfo($filename, PATHINFO_EXTENSION))
	        ->first(); // there can be duplicate file names!

	    //return $file; // array with file info

	    $rawData = Storage::cloud()->get($file['path']);

	    return response($rawData, 200)
	        ->header('ContentType', $file['mimetype'])
	        ->header('Content-Disposition', "attachment; filename='$filename'");
    }
}