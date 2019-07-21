<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\Hash;
use App\User;

class Auth
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        if ($request->header('user_id') == null){
            $response = [
                'status' => "ERROR",
                'msg' => "User not found."
            ];
            return response($response); 
        }
        $user = User::where('_id', $request->header('user_id'))->first();
        if($user == null){
           $response = [
                'status' => "ERROR",
                'msg' => "User not found."
            ];
            return response($response); 
        }
        if (Cookie::get("remember_token") != null && Hash::check(Cookie::get("remember_token"), $user->remember_token)){
            return $next($request);
        }
        if (!Hash::check($request->header('access-token'), $user->access_token)){
            $response = [
                'status' => "ERROR",
                'msg' => "Access token invalid."
            ];
            return response($response);
        }
        return $next($request);
    }
}
