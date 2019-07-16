<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\LetterHeader;
use Carbon\Carbon;

class LetterHeadersController extends Controller
{
	protected $fields = ['path'];
	//fields diisi semua field kecuali id & timestamps

	public function create(Request $request){
		$letterheader = new LetterHeader;
		foreach($fields as $field){
			$letterheader->$field = $request->$field;
		}
		$letterheader->created_at = Carbon::now();
		$letterheader->updated_at = Carbon::now();
		$letterheader->deleted_at = null;
		$letterheader->save();

		$response = [
			"status" => "OK",
			"msg" => "LetterHeader created."
		];
		return response($response);
	}

	public function retrieve($id){
		$letterheader = LetterHeader::where('_id',$id)->first();

		$response = $letterheader==null ?
		[
			"status" => "ERROR",
			"msg" => "LetterHeader not found."
		]:
		[
			"status" => "OK",
			"data" => $letterheader->getAttributes()
		];
		return response($response);
	}

	public function update($id, Request $request){
		$letterheader = LetterHeader::where('_id',$id)->first();
		foreach($fields as $field){
			$letterheader->$field = $request->$field;
		}
		$letterheader->updated_at = Carbon::now();
		$letterheader->save();

		$response = [
			"status" => "OK",
			"msg" => "LetterHeader updated."
		];
		return response($response);
	}

	public function delete($id, Request $request){
		$letterheader = LetterHeader::where('_id',$id)->first();
		$letterheader->deleted_at = Carbon::now();
		$letterheader->save();

		$response = [
			"status" => "OK",
			"msg" => "LetterHeader deleted."
		];
		return response($response);
	}
}