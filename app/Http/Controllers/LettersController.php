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

class LettersController extends Controller{

    //internal function
    private function dateTimeToIndo($dt){
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
    
    private function getFileDrive($filename){
        $contents = collect(Storage::cloud()->listContents('/', false));
        $file = $contents
            ->where('type', '=', 'file')
            ->where('filename', '=', pathinfo($filename, PATHINFO_FILENAME))
            ->first();
        return "yes";
    }

    private function getFileDriveID($fileDrive){
        $rawLink = Storage::cloud()->url($fileDrive['basename']);
        $rawLink = parse_url($rawLink, PHP_URL_QUERY);
        $fileId = substr(explode('&', $rawLink)[0],3);
        return $fileId;
    }

    private function convertToPdf($fileDrive){
        $service = Storage::cloud()->getAdapter()->getService();
        $newFile = new \Google_Service_Drive_DriveFile(array(
            'name' => $fileDrive["filename"].'gdocs',
            'mimeType' => 'application/vnd.google-apps.document'
        ));
        $fileId = $this->getFileDriveID($fileDrive);
        $gdocsId = $service->files->copy($fileId, $newFile)->id;
        $pdfFile = $service->files->export($gdocsId, 'application/pdf', array('alt'=>'media'));
        return $pdfFile;
    }

    private function filepath($letter){
        return $letter->path.'/'.$letter->filename;
    }

    private function restoreFromTrash($l){
        Storage::disk('local')->move(
            $l->path.'/'.$l->filename.'.docx',
            $l->old_path.'/'.$l->filename.'.docx',
        );
        $l->path = $l->old_path;
        $l->deleted_at = null;
        $l->old_path=null;
        $l->save();
    }

    private function moveToTrash($l){
        $l->old_path = $l->path;
        Storage::disk('local')->move(
            $l->path.'/'.$l->filename.'.docx',
            'letters/'.$l->user_id.'/trash/'.$l->filename.'.docx',
        );
        $l->path = 'letters/'.$l->user_id.'/trash';
        $l->deleted_at = Carbon::now();
        $l->save();
    }

    //main function
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
            $keys = array_keys($values); //reindexing $values
            foreach($values as $key => $val){
                try{   
                    $values[$key] = $this->dateTimeToIndo(Carbon::createFromFormat('Y-m-d', $val));
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
            $files[] = env("APP_URL").'/preview/'.$uid.'/exam'.$i.'.docx';
        }
        $data['id'] = $uid;
        $data['template'] = $lt;
        $data['files'] = $files;
        $response = [
            "status" => "OK",
            "data" => $data
        ];
        return response($response);
    }

    public function finalize(Request $request){
        $public_path = 'temp_letters/'.$request->header('user_id').'/exam'.$request->n.'.docx';
        $storage_path = 'letters/'.$request->header('user_id');
        $storage_path .= $request->dir!=null?'/'.$request->dir:null;

        $localfile = Storage::disk('public')->get($public_path);
        if($localfile==null){
            return response(["status"=>"ERROR","msg"=>"Letter does not exist"]);
        }

        //create new Letter
        $letter = new Letter;
        $letter->user_id = $request->header('user_id');
        $letter->filename = $request->filename != null ? $request->filename : 'file';
        $letter->path = $storage_path;
        $letter->save();

        $filepath = $this->filepath($letter);

        Storage::disk('local')->put($filepath.'.docx', $localfile);
        /*Storage::cloud()->put($letter->filename.'.docx', $localfile);
        $fileDrive = $this->getFileDrive($letter->filename);
        $pdfFile = $this->convertToPdf($fileDrive);
        Storage::disk('local')->put($filepath.'.pdf', $pdfFile->getBody());*/
        
        $response = [
            "status" => "OK",
            "msg" => "Finalize success",
            "letter" => $letter->_id
        ];

        return response($response);
    }

    public function emailLetter(Request $request){
        //email from local to email address
        $path = Letter::where('_id',$request->letter_id)->first()->path;
        if($request->recipient != null){
            try {
                Mail::to($request->recipient)->send(new LetterSender($path));
            }
            catch (Exception $e) {
                return response(["status"=>"ERROR","msg"=>"Maneh.coopoo"]);
            }
        }
        else{
            $user = User::where('_id', $request->header('user_id'))->first();
            Mail::to($user->email)->send(new LetterSender($path));
        }
        $response = [
            "status" => "OK",
            "msg" => "Letter sent",
        ];
        return response($response);
    }

    public function downloadLetter(Request $request){
        //download from local to your computer
        $letter = Letter::where('_id',$request->letter_id)->first();
        if($letter==null){
            return response(['status'=>'ERROR','msg'=>'Letter does not exist.']);
        }
        else if($request->header('user_id')!=$letter->user_id){
            return response(['status'=>'ERROR','msg'=>'Access forbidden.']);
        }
        else{
            Storage::disk('public')->deleteDirectory($letter->path);
            Storage::disk('public')->makeDirectory($letter->path);

            $filepath = $this->filepath($letter);

            $letter_docx = Storage::disk('local')->get($filepath.'.docx');
            Storage::disk('public')->put($filepath.'.docx', $letter_docx);

            //$letter_pdf = Storage::disk('local')->get($filepath.'.pdf');
            //Storage::disk('public')->put($filepath.'.pdf', $letter_pdf);

            $response = [
                "status" => "OK",
                "linkdocx" => env("APP_URL").'/'.$filepath.'.docx',
            //    "linkpdf" => env("APP_URL").$filepath.'.pdf'
            ];
            return response($response);
        }
    }

    public function uploadLetter($id){
        //upload from local to drive
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
        $permissions = $service->permissions->create($file['basename'], $permission);
        return $file;
    }

    public function editLetter($id){
        //edit file in drive
        $file = $this->uploadLetter($id);
        $fileId = $this->getFileDriveID($file);
        $link = "https://docs.google.com/document/d/".$fileId."/edit";
        $response = [
            "status" => "OK",
            "msg" => "File uploaded.",
            "link" => $link
        ];
        return response($response);
    }

    public function saveLetter($id){
        //save letter from drive to local
        $letter = Letter::where('_id', $id)->first();
        if (!$letter) {
            return response(["status"=>"ERROR","msg"=>"Letter does not exist."]);
        }
        $filename = $id;
        $file = $this->getFileDrive($filename);
        if (!$file) {
            return response(["status"=>"ERROR","msg"=>"File does not exist."]);
        }
        $rawData = Storage::cloud()->get($file['path']);
        Storage::disk('local')->put($letter->path, $rawData);
        $response = [
            "status" => "OK",
            "msg" => "Letter saved."
        ];
        return response($response);
    }

    public function preview($id,$name){
        return Storage::disk('public')->get('/temp_letters/'.$id.'/'.$name);
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
            Storage::disk('local')->move($l->path,$request->new_path);
        }
        catch(Exception $e){
            return response(['status'=>'ERROR','msg'=>'Invalid directory.']);
        }
        $l->path = $request->new_path;
        $l->save();
        return response(['status'=>'OK','msg'=>'Move success.']);
    }

    public function delLetter($letter_id){
        $l = Letter::where('_id',$letter_id)->first();
        if($l==null || $l->deleted_at){
            return response(['status'=>'ERROR','msg'=>'Letter does not exist.']);
        }
        if($l->starred){
            return response(['status'=>'ERROR','msg'=>'Letter is starred.']);
        }
        try{
            $this->moveToTrash($l);
        }
        catch(Exception $e){
            return response(['status'=>'ERROR','msg'=>'Failed to delete letter.']);
        }
        return response(['status'=>'OK','msg'=>"Delete success"]);
    }

    public function restoreLetter($letter_id){
        $l = Letter::where('_id',$letter_id)->first();
        if($l==null || !$l->deleted_at){
            return response(['status'=>'ERROR','msg'=>'Letter does not exist.']);
        }
        try{
            $this->restoreFromTrash($l);
        }
        catch(Exception $e){
            dd($e);
            return response(['status'=>'ERROR','msg'=>'Failed to restore letter.']);
        }
        return response(['status'=>'OK','msg'=>"Restore success"]);
    }

    public function starLetter(Request $request, $id){
        $letter = Letter::where('user_id',$request->header('user_id'))->where('_id',$id)->first();
        if(!$letter){
            //return response(['status'=>'ERROR','msg'=>'No such letter']);
            return response(['id'=>$id,'user_id'=>$request->header('user_id')]);
        }
        $letter->starred = !$letter->starred;
        $letter->save();
        return response(['status'=>'OK','msg'=>'Star changed']);
    }

    public function getAllFiles(Request $request){
        $path = isset($request->path) ? str_replace('+',' ',$request->path) : "";
        $all_paths = Storage::disk('local')->allFiles('letters/'.$request->header('user_id'));
        $paths = Storage::disk('local')->files('letters/'.$request->header('user_id').'/'.$path);
        $trash_paths = Storage::disk('local')->files('letters/'.$request->header('user_id').'/trash');
        $starred = isset($request->starred) ? $request->starred : false;
        $trash = isset($request->trash) ? $request->trash : false;;
        if($starred && $trash){return response(["status"=>"ERROR","msg"=>"Bad request"]);}
        $allLetters = Letter::where('user_id',$request->header('user_id'))->get();
        $letters = [];
        foreach ($allLetters as $letter) {
            if(!$trash){
                if($starred){
                    if(in_array($this->filepath($letter).'.docx', $all_paths) && $letter->starred){
                        $letters[] = $letter;
                    }
                }
                else{
                    if(in_array($this->filepath($letter).'.docx', $paths)){
                        $letters[] = $letter;
                    }
                }
            }
            if(in_array($this->filepath($letter).'.docx', $trash_paths) && $trash){
                $letters[] = $letter;
            }
        }
        $response = [
            "status" => "OK",
            "letters" => $letters
        ];
        return response($response);
    }

    public function getHistory(Request $request){
        $letters = Letter::where('user_id', $request->header('user_id'))->get();
        $response = [
            "status" => "OK",
            "letters" => $letters
        ];
        return response($response);
    }

    public function getAllDirs(Request $request){
        $paths = Storage::disk('local')->directories('letters/'.$request->header('user_id'));
        $dirs = [];
        foreach ($paths as $path) {
            $temp = explode('/', $path);
            if(end($temp) != 'trash'){$dirs[] = end($temp);}
        }
        $response = [
            "status" => "OK",
            "dirs" => $dirs
        ];
        return response($response);
    }

    public function makeDir(Request $request){
        $dirs = Storage::disk('local')->directories('letters/'.$request->header('user_id'));
        foreach($dirs as $dir){
            $exploded = explode('/',$dir);
            if(end($exploded) == $request->dirname){
                return response(['status'=>'ERROR','msg' => 'Directory already exists']);
            }
        }
        Storage::makeDirectory('letters/'.$request->header('user_id').'/'.$request->dirname);
        return response(['status'=>'OK','msg'=>'Directory created']);
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