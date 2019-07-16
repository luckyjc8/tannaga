<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\User;
use Carbon\Carbon;

class UsersController extends Controller
{
	protected $fields = ['name','email','password','is_activated','pricing'];
	//fields diisi semua field kecuali id & timestamps

	public function create(Request $request){
		$user = new User;
		foreach($fields as $field){
			$user->$field = $request->$field;
		}
		$user->created_at = Carbon::now();
		$user->updated_at = Carbon::now();
		$user->deleted_at = null;
		$user->save();

		$response = [
			"status" => "OK",
			"msg" => "User created."
		];
		return response($response);
	}

	public function retrieve($id){
		$user = User::where('_id',$id)->first();

		$response = $user==null ?
		[
			"status" => "ERROR",
			"msg" => "User not found."
		]:
		[
			"status" => "OK",
			"data" => $user->getAttributes()
		];
		return response($response);
	}

	public function update($id, Request $request){
		$user = User::where('_id',$id)->first();
		foreach($fields as $field){
			$user->$field = $request->$field;
		}
		$user->updated_at = Carbon::now();
		$user->save();

		$response = [
			"status" => "OK",
			"msg" => "User updated."
		];
		return response($response);
	}

	public function delete($id, Request $request){
		$user = User::where('_id',$id)->first();
		$user->deleted_at = Carbon::now();
		$user->save();

		$response = [
			"status" => "OK",
			"msg" => "User deleted."
		];
		return response($response);
	}
}