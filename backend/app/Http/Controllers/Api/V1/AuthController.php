<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    public function register(Request $request) { abort(501); }
    public function verifyOtp(Request $request) { abort(501); }
    public function resendOtp(Request $request) { abort(501); }
    public function login(Request $request) { abort(501); }
    public function forgotPassword(Request $request) { abort(501); }
    public function resetPassword(Request $request) { abort(501); }
    public function user(Request $request) { abort(501); }
    public function logout(Request $request) { abort(501); }
}
