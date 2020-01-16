<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Str;
use App\Company;
use App\User;
use App\Mail\AddEmployee;
use Carbon\Carbon;
use Storage;


class CompaniesController extends Controller{
	public function create(Request $request){
		$company = new Company;
		$company->save();
		return response(['status'=>'OK','msg'=>'Password changed.']);
	}

	public function addDepartment(Request $request)
	{
		$company->departments[] = $request->departmentName;
		$company->save();
		return response(['status'=>'OK','msg'=>'Password changed.']);
	}

	public function addEmployee(Request $request)
	{
		$user = new User;
		$token = Str::random(10);
		$user->department = $request->department;
		$user->company_token = $token;
		$user->save();
		Mail::to($request->email)->send(new AddEmployee(env("APP_URL")."/auth/?token=".$token));
		return response(['status'=>'OK','msg'=>'Password changed.']);
	}

	public function changeDepartment(Request $request)
	{
		$user = User::where('_id',$request->user_id)->first();
		$user->department = $request->department;
		$user->save();
		return response(['status'=>'OK','msg'=>'Password changed.']);
	}
}