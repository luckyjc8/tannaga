<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\LetterTemplate;
use DB;
use PhpOffice\PhpWord\PhpWord;
use \PhpOffice\PhpWord\TemplateProcessor;
use Illuminate\Support\Facades\View;
use Carbon\Carbon;

class LetterTemplatesControllerold extends Controller
{
    public function list(){
    	$data['tls'] = DB::table('letter_templates')->paginate(9);
    	return view('template-list',$data);
    }

    public function form($id){
    	$template = LetterTemplate::where('id',$id)->first();
    	$file = new TemplateProcessor($template->path.'/temp1.docx');
    	$data['vars'] = $file->getVariables();
        $new_vars = array_diff($data['vars'],array("_now_date"));
        $data['id'] = $id;
        $data['vars'] = $new_vars;
    	return view('template-form',$data);
    }

    public function generate(Request $request,$id){
        $uid = 1;
        $vals = $request->request->all();
        unset($vals['_token']);
        $template = LetterTemplate::where('id',$id)->first();
        for($i=1 ; $i<=$template->count;$i++){
            $file = new TemplateProcessor($template->path.'/temp'.$i.'.docx');
            $vars = $file->getVariables();
            $vars = array_diff($vars,array("_now_date"));
            $form_data = [];
            $keys=array_keys($vals);
            $j=0;
            foreach($vars as $var){
                $s = str_replace(' ', '_', $var);
                if(strpos($s, "datetime_") !== FALSE){
                    $form_data[$keys[$j]] = $this->dateTimeToIndo(Carbon::createFromFormat('Y-m-d', $vals[$s]));
                }
                else{
                    $form_data[$keys[$j]] = $vals[$s];
                }
                $j++;
            }
            $path = "users/".$uid."/temp/exam".$i.".docx";
            $file->setValue($vars,$form_data);
            $now = $this->dateTimeToIndo(Carbon::now()->setTimezone('Asia/Jakarta'));
            dd($now);
            $file->setValue("_now_date",$now);
            $file->saveAs($path);
        }
        $data['template'] = $template;
        $data['user'] = 'user1';
        $data['id'] = $uid;
        return view('finalize',$data);
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

/*    public function kv_read_word($input_file){ 
        $kv_strip_texts = ''; 
        $kv_texts = '';    
        if(!$input_file || !file_exists($input_file)) return false;
            
        $zip = zip_open($input_file);
            
        if (!$zip || is_numeric($zip)) return false;
        
        
        while ($zip_entry = zip_read($zip)) {
                
            if (zip_entry_open($zip, $zip_entry) == FALSE) continue;
                
            if (zip_entry_name($zip_entry) != "word/document.xml") continue;

            $kv_texts .= zip_entry_read($zip_entry, zip_entry_filesize($zip_entry));
                
            zip_entry_close($zip_entry);
        }
        
        zip_close($zip);
            

        $kv_texts = str_replace('</w:r></w:p></w:tc><w:tc>', " ", $kv_texts);
        $kv_texts = str_replace('</w:r></w:p>', "\r\n", $kv_texts);
        $kv_strip_texts = nl2br(strip_tags($kv_texts,''));

        return $kv_strip_texts;
    }*/
}