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
	public function generate($text){
		$fileName = 'letter_template/Surat Pengunduran Diri/temp1.docx';
		$phpWord = \PhpOffice\PhpWord\IOFactory::load($fileName);
		$sections = $phpWord->getSections();
		$section = $sections[0];
		//$arrays = $section->getElements();
		$header = $section->addHeader();
		$header->firstPage();
		$table = $header->addTable();
		$table->addRow();
		$cell = $table->addCell(4000);
		$textrun = $cell->addTextRun();
		$textrun->addText($text);
		//$textrun->addLink('https://github.com/PHPOffice/PHPWord', 'PHPWord on GitHub');
		$table->addCell(1000)->addImage(
			'../resources/kaguya.png', array('width' => 80, 'height' => 80, 'alignment' => Jc::END));
        //$path = 'test.docx';
		//$targetFile = Storage::disk('public')->get('/'.$path);
        $phpWord->save('test.docx');
    }
}