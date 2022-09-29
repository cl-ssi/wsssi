<?php
namespace App\Traits;

use \Firebase\JWT\JWT;
use Illuminate\Support\Facades\File;

trait GoogleToken
{
	public function getToken()
	{
		$private_key_id = env('GOOGLE_CLOUD_SERVICE_ACCOUNT_PRIVATE_KEY_ID');
		$private_key 	= str_replace('\n',"\n",(env('GOOGLE_CLOUD_SERVICE_ACCOUNT_PRIVATE_KEY')));
		$client_email 	= env('GOOGLE_CLOUD_SERVICE_ACCOUNT_CLIENT_EMAIL');
		
		$now_seconds = time();
		$payload = array(
			"iss" => $client_email,
			"sub" => $client_email,
			"aud" => "https://healthcare.googleapis.com/",
			"iat" => $now_seconds,
			"exp" => $now_seconds+(60*60),  // Maximum expiration time is one hour
			"uid" => $private_key_id
		);
		// return $private_key;
		return JWT::encode($payload, $private_key, "RS256");
	}

	public function getUrlBase() {
		return env('FHIR_URL_BASE');
	}
}