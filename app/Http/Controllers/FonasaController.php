<?php

namespace App\Http\Controllers;

use App\Services\FhirService;
use App\Services\FonasaService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class FonasaController extends Controller
{
    public function __construct()
    {
        //$this->middleware('auth');
    }

    /**
     * Nueva función certificate para FonasaController.
     * Busca el run en fonasa y lo agrega como nombre "temp" en Fhir.
     *
     * @param  \Illuminate\Http\Request  $request
     */
    public function certificate(Request $request)
    {
        try {
            if ($request->has('run') && $request->has('dv')) {
                $fonasa = new FonasaService($request->run, $request->dv);
                $responseFonasa = $fonasa->getPerson();

                if ($responseFonasa['error'] == false) {
                    $fhir = new FhirService;
                    $responseFhir = $fhir->find($request->run, $request->dv);
                    $objectFhir = $responseFhir['fhir'];

                    if ($responseFhir['find'] == false) {
                        $new = $fhir->save($responseFonasa['user']);
                        $objectFhir = $new['fhir'];
                    }

                    // Log::channel('slack')->notice("La nueva función certificate se ejecutó correctamente: $request->run-$request->dv");
                    return response()->json($responseFonasa['user'], Response::HTTP_OK);
                }

                if ($responseFonasa['error'] == true) {
                    return response()->json([
                        'message' => $responseFonasa['message']
                    ], Response::HTTP_BAD_REQUEST);
                }
            }

            return response()->json([
                'message' => 'No se especificó el run y el dv como parámetro'
            ], Response::HTTP_BAD_REQUEST);
        } catch (\Throwable $th) {
            $error = [
                'message' => $th->getMessage(),
                'code' => $th->getCode(),
                'line' => $th->getLine()
            ];
            if($th->getMessage() != 'Request Timeout')
                Log::channel('slack')->error("La función certificate de FonasaController produjo una excepción", $error);

            return response()->json($error, Response::HTTP_BAD_REQUEST);
        }
    }
}
