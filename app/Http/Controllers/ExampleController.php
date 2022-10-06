<?php

namespace App\Http\Controllers;

use App\Traits\GoogleToken;
use Illuminate\Http\Request;
use App\Services\FhirService;
use Illuminate\Http\Response;

use App\Services\FonasaService;
use GuzzleHttp\Client as Client;
use Illuminate\Support\Facades\Log;

class ExampleController extends Controller
{
    use GoogleToken;

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }

    /**
     * Nuevo certificate para FonasaController.
     * Busca el run en fonasa y lo agrega como nombre "temp" en Fhir.
     */
    public function certificate(Request $request)
    {
        if ($request->has('run') && $request->has('dv')) {
            $fonasa = new FonasaService($request->input('run'), $request->input('dv'));
            $responseFonasa = $fonasa->getPerson();

            if ($responseFonasa['error'] == false) {
                $fhir = new FhirService;
                $responseFhir = $fhir->find($request->input('run'), $request->input('dv'));

                if ($responseFhir['find'] == true)
                    $fhir = $responseFhir['fhir'];
                else {
                    $new = $fhir->save($responseFonasa['user']);
                    $fhir = $new['fhir'];
                }
            }

            return ($responseFonasa['error'] == true)
                ? response()->json($responseFonasa['message'], Response::HTTP_BAD_REQUEST)
                : response()->json([
                    'user' => $responseFonasa['user'],
                    'fhir' => $fhir,
                    'find' => $responseFhir['find'],
                ], Response::HTTP_OK);
        } else
            return response()->json("No se especificó el run y el dv como parámetro", Response::HTTP_BAD_REQUEST);
    }

    /**
     * Debe llamarse al loguearse con Clave Unica.
     * Busca el paciente en Fhir si existe, le actualiza el nombre como "official".
     * Sino lo agrega en Fhir y agrega el nombre como "official".
     */
    public function storePatientOnFhir(Request $request)
    {
        $run = $request->RolUnico['numero'];
        $dv = $request->RolUnico['DV'];

        if (isset($run) && isset($dv)) {
            $fonasa = new FonasaService($run, $dv);
            $responseFonasa = $fonasa->getPerson();

            $fhir = new FhirService;
            $responseFhir = $fhir->find($run, $dv);

            if ($responseFonasa['error'] == false) {
                if ($responseFhir['find'] == true) {
                    $qtyNames = count($responseFhir['fhir']->entry[0]->resource->name);
                    if ($qtyNames == 1)
                    {
                        $error = $fhir->updateName($request->name, $responseFhir['idFhir']);
                        Log::channel('slack')->notice("El paciente $run-$dv fue actualizado en Fhir", $request->name);
                    }
                } else {
                    $newFhir = $fhir->save($responseFonasa['user']);
                    $error = $fhir->updateName($request->name, $newFhir['fhir']->id);
                    Log::channel('slack')->notice("El paciente $run-$dv fue creado en Fhir", $request->name);
                }

                $find = $fhir->find($run, $dv);

                return response()->json($find['fhir'], Response::HTTP_OK);
            }

            return response()->json($responseFonasa['message'], Response::HTTP_BAD_REQUEST);
        }
        return response()->json("No se especificó el run y el dv como parámetro", Response::HTTP_BAD_REQUEST);
    }

    /**
     * Para guardar los pacientes en Fhir con nombre "temp".
     * Este endpoint lo llama el command de Esmeralda
     */
    public function storePatientAsTemp(Request $request)
    {
        try {
            if(isset($request->run) && isset($request->dv))
            {
                $fhir = new FhirService;
                $responseFhir = $fhir->find($request->run, $request->dv);
                if($responseFhir['find'] == false)
                {
                    $newFhir = $fhir->save($request);
                    return response()->json($newFhir['fhir'], Response::HTTP_OK);
                }

                return response()->json([
                    'error' => "El paciente $request->run-$request->dv ya existe en Fhir",
                    'find' => $responseFhir['find']
                ], Response::HTTP_BAD_REQUEST);
            }

            return response()->json([
                'error' => 'No se especificó el run y el dv como parámetros'
            ], Response::HTTP_BAD_REQUEST);
        } catch (\Throwable $th) {
            $error = [
                'message' => $th->getMessage(),
                'code' => $th->getCode(),
                'line' => $th->getLine()
            ];
            Log::channel('slack')->error("La función storePatientAsTemp produjo una excepción", $error);
            return response()->json($error, Response::HTTP_BAD_REQUEST);
        }
    }

    /**
     * Busca un paciente en Fhir dado un Run y un DV
     */
    public function findFhir(Request $request)
    {
        try {
            $fhir = new FhirService;
            $responseFhir = $fhir->find($request->input('run'), $request->input('dv'));

            if ($responseFhir['find'] == true)
                return response()->json($responseFhir['fhir'], Response::HTTP_OK);
            else
            {
                return response()->json([
                    'error' => "El paciente $request->input('run')-$request->input('dv') no fue encontrado en Fhir"
                ], Response::HTTP_BAD_REQUEST);
            }
        } catch (\Throwable $th) {
            $error = [
                'message' => $th->getMessage(),
                'code' => $th->getCode(),
                'line' => $th->getLine()
            ];
            Log::channel('slack')->error("La función findFhir produjo una excepción", $error);
            return response()->json($error, Response::HTTP_BAD_REQUEST);
        }
    }

    /**
     * Get Token y Fhir URL
     *
     * use GoogleToken;
     *
     * return $this->getToken();
     * return $this->getUrlBase();
     *
     */
}
