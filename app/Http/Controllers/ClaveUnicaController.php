<?php

namespace App\Http\Controllers;

use Symfony\Component\HttpKernel\Exception\HttpException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Hash;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class ClaveUnicaController extends Controller
{
    public function __construct()
    {
        //  $this->middleware('auth:api');
    }
    /**
    * Display a listing of the resource.
    *
    * @return \Illuminate\Http\Response
    */
    public function login($token)
    {
        if($token) {
            $url_base = "https://accounts.claveunica.gob.cl/openid/userinfo";
            $response = Http::withToken($token)->post($url_base);
            return $response;
        }
    }

}
?>
