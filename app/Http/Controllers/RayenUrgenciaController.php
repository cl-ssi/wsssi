<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Http\Client\RequestException;

class RayenUrgenciaController extends Controller
{
    public function __construct()
    {
        //$this->middleware('auth');
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function getStatus(Request $request)
    {
		date_default_timezone_set('America/Santiago');

		$date=date('Ymd');
		$url_base = 'https://api.saludenred.cl/api/healthCareCenter/';
		$url_query = '/emergencyAdmissions?fromDate=';

		/**
		 * En archivo .env 
		 * ESTABLECIMIENTOS='{"SAPU Aguirre":{"id":4198,"token":"SDX-TICKET ..."},"SAR Sur":{"id":4162,"token":"SDX-TICKET ..."}}'
		 */

		$establecimientos = json_decode(env('ESTABLECIMIENTOS'), true);

		if ($establecimientos === null) {
			// deal with error...
			die('No se encuentra la variable ESTABLECIMIENTOS en el .env');
		}

		foreach ($establecimientos as $nombre => $valores) {
			$client = new Client(['headers' => [ 'Authorization' => $valores['token']]]);
			try {

				// $response = $client->get('https://i.saludiquique.cl/dev/get-ip',['http_errors' => false]);
				// die('listoco');
				$response = $client->get($url_base . $valores['id'] . $url_query . $date,['http_errors' => false]);

				if($response->getStatusCode() == 200) {
					$array = array_count_values(array_column(json_decode($response->getBody(),true),'AdmissionStatus'));

					$count['data'][$nombre]['En espera'] = 0;
					$count['data'][$nombre]['En box'] = 0; //12, 99, 100

					if(isset($array[1])) {
							$count['data'][$nombre]['En espera'] = $array[1];
					}

					if(isset($array[12])) {
							$count['data'][$nombre]['En box'] += $array[12];
					}
					if(isset($array[99])) {
							$count['data'][$nombre]['En box'] += $array[99];
					}
					if(isset($array[100])) {
							$count['data'][$nombre]['En box'] += $array[100];
					}
				}
				else {
					$count['data'][$nombre]['En espera'] = 'Error';
					$count['data'][$nombre]['En box'] = 'Error';
				}
			} catch(RequestException $e) {
				die('Tiempo de espera agotado');
			}
			
		}
		$count['updated'] = date('Y-m-d H:i');
		
		return isset($count) ? response()->json($count) : null;
    }
}
