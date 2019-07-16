<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\LetterTemplate;
use Carbon\Carbon;

class LetterTemplatesController extends Controller
{
	protected $fields = ['path'];
	//fields diisi semua field kecuali id & timestamps

	public function create(Request $request){
		$lettertemplate = new LetterTemplate;
		foreach($fields as $field){
			$lettertemplate->$field = $request->$field;
		}
		$lettertemplate->created_at = Carbon::now();
		$lettertemplate->updated_at = Carbon::now();
		$lettertemplate->deleted_at = null;
		$lettertemplate->save();

		$response = [
			"status" => "OK",
			"msg" => "LetterTemplate created."
		];
		return response($response);
	}

	public function retrieve($id){
		$lettertemplate = LetterTemplate::where('_id',$id)->first();

		$response = $lettertemplate==null ?
		[
			"status" => "ERROR",
			"msg" => "LetterTemplate not found."
		]:
		[
			"status" => "OK",
			"data" => $lettertemplate->getAttributes()
		];
		return response($response);
	}

	public function update($id, Request $request){
		$lettertemplate = LetterTemplate::where('_id',$id)->first();
		foreach($fields as $field){
			$lettertemplate->$field = $request->$field;
		}
		$lettertemplate->updated_at = Carbon::now();
		$lettertemplate->save();

		$response = [
			"status" => "OK",
			"msg" => "LetterTemplate updated."
		];
		return response($response);
	}

	public function delete($id, Request $request){
		$lettertemplate = LetterTemplate::where('_id',$id)->first();
		$lettertemplate->deleted_at = Carbon::now();
		$lettertemplate->save();

		$response = [
			"status" => "OK",
			"msg" => "LetterTemplate deleted."
		];
		return response($response);
	}
}