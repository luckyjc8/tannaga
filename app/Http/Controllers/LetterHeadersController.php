<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\LetterHeader;
use Carbon\Carbon;
use PhpOffice\PhpWord\PhpWord;
use \PhpOffice\PhpWord\TemplateProcessor;
use \PhpOffice\PhpWord\SimpleType\Jc;
use Storage;


class LetterHeadersController extends Controller{
	public function generate(Request $request/*, $lh_id*/){
		/*$lh = LetterHeader::where('_id', $lh_id)->first();

		//move letter to public
		$letter = Letter::where('_id', $request->letter_id)->first();
		if (!$letter) {
            return response(["status"=>"ERROR","msg"=>"Letter does not exist."]);
        }
    	$path = 'letters/'.$letter->user_id.'/'.$letter->name.'.docx';
    	$localfile = Storage::disk('local')->get($path);
    	Storage::disk('public')->put($path, $localfile);*/
    	
    	//imagepath, do something about this
    	$text = $request->text;
    	$text2 = $request->text2;
    	$imagePath = '../resources/kaguya.png';
    	$path = 'letter_template/Surat Pengunduran Diri/temp1.docx';

		$phpWord = \PhpOffice\PhpWord\IOFactory::load($path);

		$fontStyle1 = 'Font1';
		$phpWord->addFontStyle($fontStyle1, array(
			'bold' => true,
			'name' => "Times New Roman",
			'size' => 22,
		));

		$fontStyle2 = 'Font2';
		$phpWord->addFontStyle($fontStyle2, array(
			'name' => "Times New Roman",
			'size' => 18,
		));

		$paragraphStyle = 'Paragraph';
		$phpWord->addParagraphStyle($paragraphStyle, array(
			'alignment' => Jc::CENTER,
		));

		$sections = $phpWord->getSections();
		$section = $sections[0];
		$header = $section->addHeader();
		$header->firstPage();
		$table = $header->addTable();
		$table->addRow();
		$table->addCell(2500)->addImage($imagePath, array('width'=>80, 'height'=>80, 'alignment'=>Jc::START));
		$textrun = $table->addCell(7500)->addTextRun($paragraphStyle);
		$textrun->addText($text, $fontStyle1);
		$textrun->addTextBreak(2);
		$lineStyle = array('weight' => 1, 'width' => 400, 'height' => 1, 'color' => 000000);
		$textrun->addLine($lineStyle);
		$textrun->addTextBreak(0);
		$textrun->addText($text2, $fontStyle2, $paragraphStyle);
		$phpWord->save('test.docx');

		/*//save file then move to storage again
        $phpWord->save($path);
        $localfile = Storage::disk('public')->get($path);
       	Storage::disk('local')->put($path, $localfile);
        Storage::disk('public')->delete($path);*/

       	$response = [
            "status" => "OK",
            "msg" => "Letter saved."
        ];

	    return response($response);
    }
}