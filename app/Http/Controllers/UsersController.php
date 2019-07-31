<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Str;
use App\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use App\Mail\EmailConfirm;
use App\Mail\PasswordReset;

class UsersController extends Controller
{
	protected $fields = ['name','email'];
	//fields diisi semua field kecuali id & timestamps

	public function create(Request $request){
		$user = new User;
		foreach($this->fields as $field){
			$user->$field = $request->$field;
		}
		$user->password = Hash::make($request->password);
		$user->is_activated = true;
		$user->created_at = Carbon::now();
		$user->updated_at = Carbon::now();
		$user->deleted_at = null;
		$str = Str::random(60);
		$user->activate_link = Hash::make($str);

		$response = [
			"status" => "OK",
			"msg" => "User created."
		];
		//Mail::to($user->email)->send(new EmailConfirm("tannaga.com/activate/".$user->id."/".$str));
		$user->save();
		return response($response);
	}

	public function activate($id, $token){
		$user = User::where('_id',$id)->first();
		if ($user==null) {
			$response =[
				"status" => "ERROR",
				"msg" => "User not found."
			];
		}
		else if (!Hash::check($token, $user->activate_link)){
			$response =[
				"status" => "ERROR",
				"msg" => "Token invalid"
			];
		}
		else{
			$user->is_activated = true;
			$user->activate_link = null;
			$user->save();
			$response = [
				"status" => "OK",
				"msg" => "User activated."
			];
		}
		return response($response);
	}

	public function forget(Request $request){
		$user = User::where('email',$request->email)->first();
		if ($user==null) {
			$response =[
				"status" => "ERROR",
				"msg" => "User not found."
			];
		}
		else{
			$str = Str::random(60);
			$user->forgot_link = Hash::make($str);
			//Mail::to($user->email)->send(new PasswordReset("tannaga.com/change/".$user->id."/".$str));
			$response = [
				"status" => "OK",
				"msg" => "Password reset link sent to email."
			];
		}
		return response($response);
	}

	public function change($id, $token, Request $request){
		$user = User::where('_id',$id)->first();
		if ($user==null || !Hash::check($token, $user->forgot_link)) {
			$response =[
				"status" => "ERROR",
				"msg" => "User not found."
			];
		}
		else{
			$user->password = $request->new_password;
			$user->forgot_link = null;
			$user->save();
			$response = [
				"status" => "OK",
				"msg" => "Password changed."
			];
		}
		return response($response);
	}

	public function login(Request $request){
		$user = User::where('email',$request->email)->first();
		if ($user==null || !Hash::check($request->password, $user->password) || !$user->is_activated) {
			dd([$user, $request->all()]);
			$response =[
				"status" => "ERROR",
				"msg" => "Invalid login."
			];
		}
		else{
			$str = Str::random(60);
			if ($request->remember_me){
				Cookie::make("remember_token", $str, 60*24*30*3);
				$user->remember_token = Hash::make($str);
			}
			else{
				$user->access_token = Hash::make($str);
			}
			$user->save();
			$response = [
				"status" => "OK",
				"msg" => "Login success.",
				"data" => ["user_id"=>$user->_id, "access_token"=>$str]
			];
		}
		return response($response);
	}

	public function logout(Request $request){
		$user = User::where('_id',$request->id)->first();
		if ($user==null) {
			$response =[
				"status" => "ERROR",
				"msg" => "User not found."
			];
		}
		else{
			$user->access_token = null;
			$user->remember_token = null;
			$user->save();
			$response = [
				"status" => "OK",
				"msg" => "Logout success."
			];
		}
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
		foreach($this->fields as $field){
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