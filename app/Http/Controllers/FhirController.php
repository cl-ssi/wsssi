<?php

namespace App\Http\Controllers;

use App\Traits\GoogleToken;
use Illuminate\Http\Request;
use App\Services\FhirService;
use Illuminate\Http\Response;

use App\Services\FonasaService;
use Illuminate\Support\Facades\Log;

class FhirController extends Controller
{
    use GoogleToken;

    /**
     * Debe llamarse al loguearse con Clave Unica.
     * Busca el paciente en Fhir si existe, le actualiza el nombre como "official".
     * Sino lo consulta en Fonasa, lo agrega en Fhir y actualiza el nombre como "official".
     * 
     * @param  \Illuminate\Http\Request  $request
     */
    public function storePatientOnFhir(Request $request)
    {
        try {
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
                        if ($qtyNames == 1 && $responseFhir['fhir']->entry[0]->resource->name[0]->use != "official") {
                            $error = $fhir->updateName($request->name, $responseFhir['idFhir']);
                            // Log::channel('slack')->notice("El paciente $run-$dv fue actualizado con nombre oficial");
                        }
                    } else {
                        $newFhir = $fhir->save($responseFonasa['user']);
                        $error = $fhir->updateName($request->name, $newFhir['fhir']->id);
                        // Log::channel('slack')->notice("El paciente $run-$dv fue agregado con nombre oficial");
                    }

                    return response()->json($fhir->find($run, $dv), Response::HTTP_OK);
                }

                return response()->json([
                    'message' => $responseFonasa['message']
                ], Response::HTTP_BAD_REQUEST);
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
            Log::channel('slack')->error('La función storePatientOnFhir produjo una excepción', $error);
            return response()->json($error, Response::HTTP_BAD_REQUEST);
        }
    }

    /**
     * Para guardar los pacientes en Fhir con nombre "temp".
     * Este endpoint lo consume el command del proyecto Esmeralda
     *
     * @param  \Illuminate\Http\Request  $request
     */
    public function storePatientAsTemp(Request $request)
    {
        try {
            if (isset($request->run) && isset($request->dv)) {
                $fhir = new FhirService;
                $responseFhir = $fhir->find($request->run, $request->dv);
                if ($responseFhir['find'] == false) {
                    return response()->json($fhir->save($request), Response::HTTP_OK);
                }

                return response()->json([
                    'message' => "El paciente $request->run-$request->dv ya existe en Fhir",
                    'find' => $responseFhir['find']
                ], Response::HTTP_BAD_REQUEST);
            }

            return response()->json([
                'message' => 'No se especificó el run y el dv como parámetros'
            ], Response::HTTP_BAD_REQUEST);
        } catch (\Throwable $th) {
            $error = [
                'message' => $th->getMessage(),
                'code' => $th->getCode(),
                'line' => $th->getLine()
            ];
            Log::channel('slack')->error('La función storePatientAsTemp produjo una excepción', $error);
            return response()->json($error, Response::HTTP_BAD_REQUEST);
        }
    }

    /**
     * Busca un paciente en Fhir dado un Run y un DV
     *
     * @param  \Illuminate\Http\Request  $request
     */
    public function findPatient(Request $request)
    {
        try {
            $fhir = new FhirService;
            $responseFhir = $fhir->find($request->run, $request->dv);

            if ($responseFhir['find'] == true)
                return response()->json($responseFhir, Response::HTTP_OK);
            else {
                return response()->json([
                    'message' => "El paciente $request->run-$request->dv no fue encontrado en Fhir"
                ], Response::HTTP_BAD_REQUEST);
            }
        } catch (\Throwable $th) {
            $error = [
                'message' => $th->getMessage(),
                'code' => $th->getCode(),
                'line' => $th->getLine()
            ];
            Log::channel('slack')->error('La función findPatient produjo una excepción', $error);
            return response()->json($error, Response::HTTP_BAD_REQUEST);
        }
    }

    /**
     * Elimina un paciente en Fhir dado un Run y un DV
     *
     * @param  \Illuminate\Http\Request  $request
     */
    public function deletePatient(Request $request)
    {
        try {
            $run = $request->run;
            $dv = $request->dv;

            if (isset($run) && isset($dv)) {
                $fhir = new FhirService;
                $responseFind = $fhir->find($request->run, $request->dv);

                if ($responseFind['find'] == true) {
                    $fhir = new FhirService;
                    $respondeDelete = $fhir->delete($responseFind['idFhir']);

                    if($respondeDelete['deleted']) {
                        return response()->json([
                            'message' => "El paciente $run-$dv fue eliminado"
                        ], Response::HTTP_OK);
                    }
                }

                return response()->json([
                    'message' => "El paciente $run-$dv no fue encontrado"
                ], Response::HTTP_BAD_REQUEST);
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
            Log::channel('slack')->error('La función deletePatient produjo una excepción', $error);
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
