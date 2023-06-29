<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Hash;
use Illuminate\Http\Request;
use App\Users;
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
    public function login($access_token = null)
    {
        if($access_token) {
            $url_base = "https://www.claveunica.gob.cl/openid/userinfo";
            $response = Http::withToken($access_token)->post($url_base);
            
            dd(json_decode($response));
        }
        else {
            echo "No se ha proporcionado un access_token";
        }
    }

}
?>
