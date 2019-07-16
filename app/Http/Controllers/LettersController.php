<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
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
}