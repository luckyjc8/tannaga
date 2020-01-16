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
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Auth;
use Tymon\JWTAuth\Exceptions\JWTException;

class UsersController extends Controller
{

	/**
     * Instantiate a new UserController instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * Get the authenticated User.
     *
     * @return Response
     */
    public function profile()
    {
        return response()->json(['user' => Auth::user()], 200);
    }

    /**
     * Get all User.
     *
     * @return Response
     */
    public function allUsers()
    {
         return response()->json(['users' =>  User::all()], 200);
    }

    /**
     * Get one user.
     *
     * @return Response
     */
    public function singleUser($id)
    {
        try {
            $user = User::findOrFail($id);

            return response()->json(['user' => $user], 200);

        } catch (\Exception $e) {

            return response()->json(['message' => 'user not found!'], 404);
        }

    }

	protected $fields = ['name','email'];
	//fields diisi semua field kecuali id & timestamps

	public function valid_email($str) {
		return (!preg_match("/^([a-z0-9\+_\-]+)(\.[a-z0-9\+_\-]+)*@([a-z0-9\-]+\.)+[a-z]{2,6}$/ix", $str)) ? FALSE : TRUE;
	}

	public function register(Request $request){
		if (!$this->valid_email($request->email)){
			return response(["status"=> "ERROR", "msg" => "Email invalid"]);
		}
		else if (User::where('email', $request->email)->first() != null){
			return response(["status"=> "ERROR", "msg" => "Email already exists"]);
		}
		else if (strlen($request->password) < 8){
			return response(["status"=> "ERROR", "msg" => "Password invalid"]);
		}
		try{
			if ($request->company_token != null){
				$user = User::where('company_token', $request->company_token)->first();
				if($user == null){
					return response(['status'=>"ERROR",'msg'=>'User not found.']);
				}
				$user->company_token = null;
			}
			else{
				$user = new User;
			}
			foreach($this->fields as $field){
				$user->$field = $request->$field;
			}
			$user->password = app('hash')->make($request->password);
			$user->is_activated = false;
			$user->created_at = Carbon::now();
			$user->updated_at = Carbon::now();
			$user->deleted_at = null;
			$user->forgot_link = null;
			$str = Str::random(60);
			$user->activate_link = Hash::make($str);
			$response = [
				"status" => "OK",
				"msg" => "User created."
			];
			$user->save();
			Mail::to($user->email)->send(new EmailConfirm(env("APP_URL")."/activate/".$user->_id."/".$str));
			return response($response);
		} catch (\Exception $e) {
            //return error message
            return response()->json(['message' => 'User Registration Failed!'], 409);
        }
	}
	public function getName(Request $request){
		$user = User::where('_id', $request->header('user_id'))->first();
		if ($user==null) {
			$response =[
				"status" => "ERROR",
				"msg" => "User not found."
			];
		}
		else {
			$response = [
				"status" => "OK",
				"name" => $user->name
			];
		}
		return response($response);
	}

	public function activateAccount($id, $token){
		$user = User::where('_id',$id)->first();
		if ($user==null) {
			return redirect('http://tannaga-dev.com/auth/activate/404');
		}
		else if ($user->is_activated){
			return redirect('http://tannaga-dev.com/auth/activate/201');
		}
		else if (!Hash::check($token, $user->activate_link)){
			return redirect('http://tannaga-dev.com/auth/activate/403');
		}
		else{
			$user->is_activated = true;
			$user->activate_link = null;
			$user->save();
			return redirect('http://tannaga-dev.com/auth/activate/200');
		}
	}

	public function forgetPassword(Request $request){
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
			$user->save();
			Mail::to($user->email)->send(new PasswordReset(env("APP_URL")."/change/".$user->_id."/".$str));
			$response = [
				"status" => "OK",
				"msg" => "Password reset link sent to email."
			];
		}
		return response($response);
	}

	public function checkChangePasswordToken($id,$token){
		$user = User::where('_id',$id)->first();
		if ($user==null) {
			$response =[
				"status" => "ERROR",
				"msg" => "User not found."
			];
		}
		else if (!Hash::check($token, $user->forgot_link)){
			$response =[
				"status" => "ERROR",
				"msg" => "Token invalid."
			];
		}
		else{
			$response=[
				"status" => "OK",
				"msg" => "Token valid."
			];
		}
		return response($response);
	}

	public function changePassword($id, $token, Request $request){
		$user = User::where('_id',$id)->first();
		if ($user==null || !Hash::check($token, $user->forgot_link)) {
			$response =[
				"status" => "ERROR",
				"msg" => "User not found."
			];
		}
		else if (strlen($request->password) < 8){
			return response(["status"=> "ERROR", "msg" => "Password invalid"]);
		}
		else{
			$user->password = Hash::make($request->password);
			$user->forgot_link = null;
			$user->save();
			$response = [
				"status" => "OK",
				"msg" => "Password changed."
			];
		}
		return response($response);
	}

	public function renewPassword(Request $request){
		$user = User::where('_id',$request->header('user_id'))->first();
		if (!Hash::check($request->old_password, $user->password)) {
			return response(['status'=>"ERROR",'msg'=>'Invalid password.']);
		}
		else{
			$user->password = app('hash')->make($request->new_password);
			$user->save();
			return response(['status'=>'OK','msg'=>'Password changed.']);
		}
	}

	public function login(Request $request){
		$user = User::where('email',$request->email)->first();
		if ($user==null || !Hash::check($request->password, $user->password) || !$user->is_activated) {
			$response =[
				"status" => "ERROR",
				"msg" => "Invalid login."
			];
		}
		else{
			$credentials = $request->only(['email', 'password']);

	        if (! $token = Auth::attempt($credentials)) {
	            return response()->json(['message' => 'Unauthorized'], 401);
	        }

	        return response(["token" => $token, "user_id" => $user->id]);
		}
	}

	public function logout(Request $request){
		try{
			Auth::logout();
			return response(['status'=>'OK','msg'=>'Logout success.']);
		}
		catch(JWTException $e){
			return response(['status'=>"ERROR",'msg'=>'Logout failed']);
		}
	}
}