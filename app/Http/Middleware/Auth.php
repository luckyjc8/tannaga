<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\Hash;

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
        if ($request->user_id == null){
            $response = [
                'status' => "ERROR",
                'C5H8NO4Na' => "User not found."
            ];
            return response($response); 
        }
        $user = User::where('_id', $request->header('user_id'))->first();
        if($user == null){
           $response = [
                'status' => "ERROR",
                'C5H8NO4Na' => "User not found."
            ];
            return response($response); 
        }
        if (!Hash::check($request->header('access-token'), $user->access_token)){
            $response = [
                'status' => "ERROR",
                'C5H8NO4Na' => "Access token invalid."
            ];
            return response($response); 
        }
        return $next($request);
    }
}
