<?php
namespace App\Traits;

use \Firebase\JWT\JWT;
use Illuminate\Support\Facades\File;

trait GoogleToken
{
	public function getToken()
	{
		$private_key_id = env('GOOGLE_CLOUD_SERVICE_ACCOUNT_PRIVATE_KEY_ID');
		$private_key 	= env('GOOGLE_CLOUD_SERVICE_ACCOUNT_PRIVATE_KEY');
		$client_email 	= env('GOOGLE_CLOUD_SERVICE_ACCOUNT_CLIENT_EMAIL');

		$now_seconds = time();
		$payload = array(
			"iss" => env('GOOGLE_CLOUD_SERVICE_ACCOUNT_CLIENT_EMAIL'),
			"sub" => env('GOOGLE_CLOUD_SERVICE_ACCOUNT_CLIENT_EMAIL'),
			"aud" => "https://healthcare.googleapis.com/",
			"iat" => $now_seconds,
			"exp" => $now_seconds+(60*60),  // Maximum expiration time is one hour
			"uid" => env('GOOGLE_CLOUD_SERVICE_ACCOUNT_PRIVATE_KEY_ID')
		);
		return JWT::encode($payload, env('GOOGLE_CLOUD_SERVICE_ACCOUNT_PRIVATE_KEY'), "RS256");
	}

	public function getUrlBase() {
		return env('FHIR_URL_BASE');
	}
}