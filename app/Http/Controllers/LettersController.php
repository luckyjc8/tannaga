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

    private function filepath($letter){
        return $letter->path.'/'.$letter->filename;
    }

    private function dateTimeToDayOfWeek($dt){
        $day = "";
        switch($dt->dayOfWeek){
            case 1: $day = "Senin"; break;
            case 2: $day = "Selasa"; break;
            case 3: $day = "Rabu"; break;
            case 4: $day = "Kamis"; break;
            case 5: $day = "Jumat"; break;
            case 6: $day = "Sabtu"; break;
            case 7: $day = "Minggu"; break;
        }
        return $day;
    }

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
        //get file from drive
        $contents = collect(Storage::cloud()->listContents('/', false));
        $file = $contents
            ->where('type', '=', 'file')
            ->where('filename', '=', pathinfo($filename, PATHINFO_FILENAME))
            ->first();
        return $file;
    }

    private function getFileDriveID($fileDrive){
        //get fileId from a file in drive
        $rawLink = Storage::cloud()->url($fileDrive['basename']);
        $rawLink = parse_url($rawLink, PHP_URL_QUERY);
        $fileId = substr(explode('&', $rawLink)[0],3);
        return $fileId;
    }

    private function convertToPdf($fileDrive){
        //convert file in drive to pdf file
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


    /*-------------------------------------------------------------------------*/
    /*-------------------------------------------------------------------------*/
    /*-------------------------------------------------------------------------*/
    /*-------------------------------------------------------------------------*/


    //main function
    public function generate(Request $request,$letter_id){
        $user_id = $request->header("user_id");
        $values = $request->request->all();
        $letter_template = LetterTemplate::where('_id',$letter_id)->first();
        if($letter_template==null){
            return ["status"=>"ERROR","msg"=>"Letter template does not exist."];
        }
        $files = [];
        Storage::disk('public')->deleteDirectory('temp_letters/'.$user_id);
        Storage::disk('public')->makeDirectory('temp_letters/'.$user_id);
        for($i=1 ; $i<=$letter_template->count;$i++){
            $file = new TemplateProcessor('letter_template/'.$letter_template->name.'/temp'.$i.'.docx');
            $vars = $file->getVariables();
            $vars = array_diff($vars,array("_now_date"));
            if (count($values) < count($vars)){
                return ["status"=>"ERROR","msg"=>"Incomplete parameters."];
            };
            $keys = array_keys($values); //reindexing $values
            $path = "temp_letters/".$user_id."/exam".$i.".docx";
            foreach ($vars as $var) {
                $nm = explode('_',$var)[1];
                $type = explode('_', $var)[0];
                try {
                    if ($type == 'datetime'){
                        $values[$nm] = $this->dateTimeToIndo(Carbon::createFromFormat('Y-m-d', $values[$nm]));
                    }
                    else if ($type == 'datetime1'){
                        $values[$nm] = $this->dateTimeToDayOfWeek(Carbon::createFromFormat('Y-m-d', $values[$nm])).', '.$this->dateTimeToIndo(Carbon::createFromFormat('Y-m-d', $values[$nm]));
                    }
                } catch (Exception $e) {
                    
                }
                
                $file->setValue($var, $values[$nm]);
            }
            $now = $this->dateTimeToIndo(Carbon::now()->setTimezone('Asia/Jakarta'));
            $file->setValue("_now_date",$now);
            $file->saveAs($path);
            $localfile = Storage::disk('public')->get('/'.$path);
            Storage::disk('public')->put($path, $localfile);
            $files[] = env("APP_URL").'/'.$path;
        }
        $data['id'] = $user_id;
        $data['template'] = $letter_template;
        $data['files'] = $files;
        $response = [
            "status" => "OK",
            "data" => $data
        ];
        return response($response);
    }

    public function finalize(Request $request){
        //letter from temp_letters go to storage/app/letters/$user_id
        $public_path = 'temp_letters/'.$request->header('user_id').'/exam'.$request->n.'.docx';
        $public_file = Storage::disk('public')->get($public_path);
        if($public_file==null){
            return response(["status" => "ERROR","msg" => "Letter does not exist"]);
        }

        //create new Letter in DB
        $letter = new Letter;
        $letter->user_id = $request->header('user_id');
        $letter->filename = $request->filename != null ? $request->filename : 'file';
        $letter->path = 'letters/'.$request->header('user_id');
        $letter->save();
        $filepath = $this->filepath($letter);

        Storage::disk('local')->put($filepath.'.docx', $public_file);
        /*Storage::cloud()->put($letter->filename.'.docx', $public_file, ['mimetype'=>'application/vnd.openxmlformats-officedocument.wordprocessingml.document']);
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
        $letter = Letter::where('_id',$request->letter_id)->first();
        $path = $this->filepath($letter).'.docx';
        if ($request->pdf){
            $path = $this->filepath($letter).'.pdf';
        }
        if($request->recipient != null){
            try {
                Mail::to($request->recipient)->send(new LetterSender($path));
            }
            catch (Exception $e) {
                return response(["status" => "ERROR","msg" => "Error send letter."]);
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
        //letter from storage/app/letters/$user_id go to public/download/letters/$user_id
        //download from local to your computer
        $letter = Letter::where('_id',$request->letter_id)->first();
        if($letter==null){
            return response(['status'=>'ERROR','msg'=>'Letter does not exist.']);
        }
        else if($request->header('user_id')!=$letter->user_id){
            return response(['status'=>'ERROR','msg'=>'Access forbidden.']);
        }
        else{
            Storage::disk('public')->deleteDirectory('downloads/'.$letter->path);
            Storage::disk('public')->makeDirectory('downloads/'.$letter->path);

            $filepath = $this->filepath($letter);

            $letter_docx = Storage::disk('local')->get($filepath.'.docx');
            Storage::disk('public')->put('downloads/'.$filepath.'.docx', $letter_docx);

            // $letter_pdf = Storage::disk('local')->get($filepath.'.pdf');
            // Storage::disk('public')->put('downloads/'.$filepath.'.pdf', $letter_pdf);

            $response = [
                "status" => "OK",
                "linkdocx" => env("APP_URL").'/downloads/'.$filepath.'.docx',
                // "linkpdf" => env("APP_URL").'/downloads/'.$filepath.'.pdf'
            ];
            return response($response);
        }
    }

    public function uploadLetter($letter_id){
        //upload from local to drive, change it to gdocs file
        $letter = Letter::where('_id', $letter_id)->first();
        if($letter==null){
            return ["status"=>"ERROR","msg"=>"Letter does not exist."];
        }
        $localfile = Storage::disk('local')->get($this->filepath($letter).'.docx');
        $filename = $letter_id;
        Storage::cloud()->put($filename, $localfile,['mimetype'=>'application/vnd.openxmlformats-officedocument.wordprocessingml.document']);
        
        $file = $this->getFileDrive($filename);
        $fileId = $this->getFileDriveID($file);
        // Change permissions of that file
        // - https://developers.google.com/drive/v3/web/about-permissions
        // - https://developers.google.com/drive/v3/reference/permissions
        $service = Storage::cloud()->getAdapter()->getService();
        $newFile = new \Google_Service_Drive_DriveFile(array(
            'name' => $letter->filename.'_'.$file["filename"],
            'mimeType' => 'application/vnd.google-apps.document'
        ));
        $gdocsId = $service->files->copy($fileId, $newFile)->id;
        $permission = new \Google_Service_Drive_Permission();
        $permission->setRole('writer');
        $permission->setType('anyone');
        $permission->setAllowFileDiscovery(false);
        $permissions = $service->permissions->create($gdocsId, $permission);
        return $gdocsId;
    }

    public function editLetter($letter_id){
        //edit file in drive
        $fileId = $this->uploadLetter($letter_id);
        $link = "https://docs.google.com/document/d/".$fileId."/edit";
        $response = [
            "status" => "OK",
            "msg" => "File uploaded.",
            "link" => $link
        ];
        return response($response);
    }

    public function saveLetter($letter_id){
        //save letter from drive to local
        $letter = Letter::where('_id', $letter_id)->first();
        if (!$letter) {
            return response(["status"=>"ERROR","msg"=>"Letter does not exist."]);
        }
        $filename = $letter_id;
        $file = $this->getFileDrive($filename);
        if (!$file) {
            return response(["status"=>"ERROR","msg"=>"File does not exist."]);
        }
        $rawData = Storage::cloud()->get($file['path']);
        Storage::disk('local')->put($this->filepath($letter).'.docx', $rawData);
        $response = [
            "status" => "OK",
            "msg" => "Letter saved."
        ];
        return response($response);
    }

    public function preview($user_id, $name){
        return Storage::disk('public')->get('/temp_letters/'.$user_id.'/'.$name);
    }

    public function indexDirContent(Request $request, $dir=null){
        $path = $request->header('user_id').'/'.$dir;
        $data = array_merge(Storage::files($path),Storage::directories($path));
        return response(['status'=>'OK','data'=>$data]);
    }

    public function mvLetter(Request $request, $letter_id){
        $letter = Letter::where('_id',$letter_id)->first();
        if($letter==null){
            return response(['status'=>'ERROR','msg'=>'Letter does not exist.']);
        }
        try{
            Storage::disk('local')->move($letter->path,$request->new_path);
        }
        catch(Exception $e){
            return response(['status'=>'ERROR','msg'=>'Invalid directory.']);
        }
        $letter->path = $request->new_path;
        $letter->save();
        return response(['status'=>'OK','msg'=>'Move success.']);
    }

    public function delLetter($letter_id){
        $letter = Letter::where('_id',$letter_id)->first();
        if($letter==null || $letter->deleted_at){
            return response(['status'=>'ERROR','msg'=>'Letter does not exist.']);
        }
        if($letter->starred){
            return response(['status'=>'ERROR','msg'=>'Letter is starred.']);
        }
        try{
            $this->moveToTrash($letter);
        }
        catch(Exception $e){
            return response(['status'=>'ERROR','msg'=>'Failed to delete letter.']);
        }
        return response(['status'=>'OK','msg'=>"Delete success"]);
    }

    public function restoreLetter($letter_id){
        $letter = Letter::where('_id',$letter_id)->first();
        if($letter==null || !$letter->deleted_at){
            return response(['status'=>'ERROR','msg'=>'Letter does not exist.']);
        }
        try{
            $this->restoreFromTrash($letter);
        }
        catch(Exception $e){
            return response(['status'=>'ERROR','msg'=>'Failed to restore letter.']);
        }
        return response(['status'=>'OK','msg'=>"Restore success"]);
    }

    public function starLetter(Request $request, $id){
        $letter = Letter::where('user_id',$request->header('user_id'))->where('_id',$id)->first();
        if(!$letter){
            return response(['status'=>'ERROR','msg'=>'Letter not exist']);
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
        Storage::disk('public')->deleteDirectory('downloads');
        Storage::disk('public')->deleteDirectory('temp_letters');
        Storage::disk('local')->deleteDirectory('letters');
        Storage::disk('public')->makeDirectory('downloads');
        Storage::disk('public')->makeDirectory('temp_letters');
        Storage::disk('local')->makeDirectory('letters');
        $response = [
            "status" => "OK",
            "msg" => "Cleaned"
        ];
        return response($response);
    }
}
