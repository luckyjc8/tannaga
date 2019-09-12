<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use Storage;
use \Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Mail;
use App\User;
use App\Letter;
use App\LetterTemplate;
use App\Mail\LetterSender;
use PhpOffice\PhpWord\PhpWord;
use \PhpOffice\PhpWord\TemplateProcessor;

class LettersController extends Controller
{
    //protected $fields = ['letter-template-id', 'attrs', 'path'];
    //fields diisi semua field kecuali id & timestamps

    //internal function
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
    
    public function getFileDrive($filename){
        $contents = collect(Storage::cloud()->listContents('/', false));
        $file = $contents
            ->where('type', '=', 'file')
            ->where('filename', '=', pathinfo($filename, PATHINFO_FILENAME))
            ->where('extension', '=', pathinfo($filename, PATHINFO_EXTENSION))
            ->first();
        return $file;
    }

    public function uploadLetter($id){
        //get letter file, upload to drive
        $letter = Letter::where('_id', $id)->first();
        if($letter==null){
            return ["status"=>"ERROR","msg"=>"Letter does not exist."];
        }
        $localfile = Storage::disk('local')->get($letter->path);
        $filename = $id;   
        Storage::cloud()->put($filename, $localfile);
        $file = $this->getFileDrive($filename);        

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

        $file = $this->getFileDrive($filename);
        if (!$file) {
            return response(["status"=>"ERROR","msg"=>"File does not exist."]);
        }
        $rawData = Storage::cloud()->get($file['path']);
        Storage::disk('local')->put($letter->path,$rawData);

        $response = [
            "status" => "OK",
            "msg" => "Letter saved."
        ];
        return response($response);
    }

    public function preview($id,$name){
        return Storage::disk('public')->get('/temp_letters/'.$id.'/'.$name);
    }

    public function generate(Request $request,$id){
        $uid = $request->header("user_id");
        $values = $request->request->all();
        $lt = LetterTemplate::where('_id',$id)->first();
        if($lt==null){
            return ["status"=>"ERROR","msg"=>"Letter template does not exist."];
        }
        $files = [];
        Storage::disk('public')->deleteDirectory('temp_letters/'.$uid);
        Storage::disk('public')->makeDirectory('temp_letters/'.$uid);
        for($i=1 ; $i<=$lt->count;$i++){
            $file = new TemplateProcessor('letter_template/'.$lt->name.'/temp'.$i.'.docx');
            $vars = $file->getVariables();
            $vars = array_diff($vars,array("_now_date"));
            if (count($values) < count($vars)){
                return ["status"=>"ERROR","msg"=>"Incomplete parameters."];
            };
            $keys=array_keys($values);
            foreach($values as $k => $val){
                try{   
                    $values[$k] = $this->dateTimeToIndo(Carbon::createFromFormat('Y-m-d', $val));
                }
                catch(Exception $e){
                }
            }
            $path = "temp_letters/".$uid."/exam".$i.".docx";
            foreach ($vars as $var) {
                $nm = explode('_',$var)[1];
                $file->setValue($var, $values[$nm]);
            }
            $now = $this->dateTimeToIndo(Carbon::now()->setTimezone('Asia/Jakarta'));
            $file->setValue("_now_date",$now);
            $file->saveAs($path);
            $localfile = Storage::disk('public')->get('/'.$path);
            Storage::disk('public')->put($path, $localfile);
            $files[] = 'api.tannaga.com/preview/'.$uid.'/exam'.$i.'.docx';
        }
        $data['template'] = $lt;
        $data['id'] = $uid;
        $data['files'] = $files;
        $response = [
            "status" => "OK",
            "data" => $data
        ];
        return response($response);
    }

    public function finalize(Request $request){
        $public_path = 'temp_letters/'.$request->header('user_id').'/exam'.$request->n.'.docx';
        $storage_path = 'letters/'.$request->header('user_id').'/';
        $storage_path .= $request->dir!=null?$request->dir:null;
        $storage_path .= $request->filename.'.docx';

        $localfile = Storage::disk('public')->get($public_path);
        if($localfile==null){
            return response(["status"=>"ERROR","msg"=>"Letter does not exist"]);
        }
        Storage::disk('local')->put($storage_path,$localfile);

        $letter = new Letter;
        $letter->user_id = $request->header('user_id');
        $letter->filename = $request->filename == null ? 'file' : $request->filename;
        $letter->path = $storage_path;
        $letter->save();
        
        Storage::cloud()->put($letter->filename, $localfile);
        $file = $this->getFileDrive($filename);

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
        $copy = new \Google_Service_Drive_DriveFile(array(
            'name' => $letter->filename.'gdocs',
            'mimeType' => 'application/vnd.google-apps.document'
        ));
        $gdocsFile = $service->files->copy($fileId, $copy);
        $gdocsId = $gdocsFile->id;
        $realLink = "https://docs.google.com/document/d/".$fileId."/edit";
        $gdocsLink = "https://docs.google.com/document/d/".$gdocsId."/edit";
        $response = [
            "status" => "OK",
            "msg" => "Finalize success",
            "letter" => $letter->_id
        ];
        $permissions = $service->permissions->create($file['basename'], $permission);

        $export = $service->files->export($gdocsId, 'application/pdf', array('alt'=>'media'));
        Storage::disk('local')->put($letter->filename.'.pdf', $export->getBody());

        return response($response);
    }

    public function emailLetter(Request $request){
        $path = Letter::where('_id',$request->letter_id)->first()->path;
        if($request->myself != null){
            $user = User::where('_id', $request->header('user_id'))->first();
            Mail::to($user->email)->send(new LetterSender($path));
            $response = [
                "status" => "OK",
                "msg" => "Letter sent",
            ];
            return response($response);
        }
        else{
            Mail::to($request->recipient)->send(new LetterSender($path));
            $response = [
                "status" => "OK",
                "msg" => "Letter sent"
            ];
            return response($response);
        }
    }

    public function downloadLetter(Request $request){
        $letter = Letter::where('_id',$request->letter_id)->first();
        if($letter==null){
            return response(['status'=>'ERROR','msg'=>'Letter does not exist.']);
        }
        else if($request->header('user_id')!=$letter->user_id){
            return response(['status'=>'ERROR','msg'=>'Access forbidden.']);
        }
        else{
            $letter_file = Storage::disk('local')->get($letter->path);
            Storage::disk('public')->deleteDirectory('letters/'.$letter->user_id);
            Storage::disk('public')->makeDirectory('letters/'.$letter->user_id);
            Storage::disk('public')->put($letter->path, $letter_file);
            $response = [
                "status" => "OK",
                "link" => 'api.tannaga.com/'.$letter->path
            ];
            return response($response);
        }
    }

    public function indexDirContent(Request $request, $dir=null){
        $path = $request->header('user_id').'/'.$dir;
        $data = array_merge(Storage::files($path),Storage::directories($path));
        return response(['status'=>'OK','data'=>$data]);
    }

    public function mvLetter(Request $request, $letter_id){
        $l = Letter::where('_id',$letter_id)->first();
        if($l==null){
            return response(['status'=>'ERROR','msg'=>'Letter does not exist.']);
        }
        try{
            Storage::disk('local')->move($request->old_path,$request->new_path);
        }
        catch(Exception $e){
            return response(['status'=>'ERROR','msg'=>'Invalid directory.']);
        }
        $l->path = $request->new_path;
        $l->save();
        return response(['status'=>'OK','msg'=>'Move success.']);
    }

    public function delLetter(Request $request, $letter_id){
        $l = Letter::where('_id',$letter_id)->first();
        if($l==null){
            return response(['status'=>'ERROR','msg'=>'Letter does not exist.']);
        }
        try{
            Storage::disk('local')->delete($request->dir);
        }
        catch(Exception $e){
            return response(['status'=>'ERROR','msg'=>'Failed to delete letter.']);
        }
        $l->delete();
        return response(['status'=>'OK','msg'=>'Delete success.']);
    }

    public function reset(Request $request){
        if($request->header('user_id') != 'aing cupu'){
            return response(['status'=>'ERROR','msg'=>'Access forbidden.']);
        }
        $user = User::first();
        while($user != null){
            $user->delete();
            $user = User::first();
        }
        $letter = Letter::first();
        while($letter != null){
            $letter->delete();
            $letter = Letter::first();
        }
        Storage::disk('public')->deleteDirectory('letters');
        Storage::disk('public')->deleteDirectory('temp_letters');
        Storage::disk('local')->deleteDirectory('letters');
        $response = [
            "status" => "OK",
            "msg" => "Cleaned"
        ];
        return response($response);
    }
}