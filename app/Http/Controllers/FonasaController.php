<?php

namespace App\Http\Controllers;

use App\Services\FonasaService;
use Illuminate\Http\Request;
use Auth;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class FonasaController extends Controller
{
    public function __construct()
    {
        //$this->middleware('auth');
    }

    /**
     * New fonasa endpoint certificate.
     *
     * @return \Illuminate\Http\Response
     */
    public function testCertificate(Request $request)
    {
        try {
            if($request->has('run') && $request->has('dv')) {
                $fonasa = new FonasaService($request->input('run'), $request->input('dv'));
                $responseFonasa = $fonasa->getPerson();

                return ($responseFonasa['error'] == true)
                    ? response()->json($responseFonasa, Response::HTTP_BAD_REQUEST)
                    : response()->json($responseFonasa['user'], Response::HTTP_OK);
            }

            return response()->json([
                'message' => 'No se especificó el run y el dv como parámetro.'
            ], Response::HTTP_BAD_REQUEST);
        } catch (\Throwable $th) {
            $error = [
                'message' => $th->getMessage(),
                'code' => $th->getCode(),
                'line' => $th->getLine()
            ];
            Log::channel('slack')->error("El servicio Fonasa produjo una excepción.", $error);
            return response()->json($error, Response::HTTP_BAD_REQUEST);
        }
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function certificate(Request $request)
    {
        /* Si se le envió el run y el dv por GET */
        if($request->has('run') AND $request->has('dv')) {
            $rut = $request->input('run');
            $dv  = $request->input('dv');
            // $rut = 15287582;
            // $dv  = 7;

            $wsdl = 'wsdl/fonasa/CertificadorPrevisionalSoap.wsdl';
            $client = new \SoapClient($wsdl,array('trace'=>TRUE));
            $parameters = array(
                "query" => array(
                    "queryTO" => array(
                        "tipoEmisor"  => 3,
                        "tipoUsuario" => 2
                    ),
                    "entidad"           => env('FONASA_ENTIDAD'),
                    "claveEntidad"      => env('FONASA_CLAVE'),
                    "rutBeneficiario"   => $rut,
                    "dgvBeneficiario"   => $dv,
                    "canal"             => 3
                )
            );
            $result = $client->getCertificadoPrevisional($parameters);

            if ($result === false) {
                /* No se conecta con el WS */
                $error = array("error" => "No se pudo conectar a FONASA");
            }
            else {
                /* Si se conectó al WS */
                if($result->getCertificadoPrevisionalResult->replyTO->estado == 0) {
                    /* Si no hay error en los datos enviados */

                    $certificado            = $result->getCertificadoPrevisionalResult;
                    $beneficiario           = $certificado->beneficiarioTO;
                    $afiliado               = $certificado->afiliadoTO;

                    $user['run']            = $beneficiario->rutbenef;
                    $user['dv']             = $beneficiario->dgvbenef;
                    $user['name']           = $beneficiario->nombres;
                    $user['fathers_family'] = $beneficiario->apell1;
                    $user['mothers_family'] = $beneficiario->apell2;
                    $user['birthday']       = $beneficiario->fechaNacimiento;
                    $user['gender']         = $beneficiario->generoDes;
                    $user['desRegion']      = $beneficiario->desRegion;
                    $user['desComuna']      = $beneficiario->desComuna;
                    $user['direccion']      = $beneficiario->direccion;
                    $user['telefono']       = $beneficiario->telefono;

                    if($afiliado->desEstado == 'ACTIVO') {
                        $user['tramo'] = $afiliado->tramo;
                        $user['prevision'] = "FONASA $afiliado->tramo";
                    }
                    else {
                        $user['tramo'] = null;
                        $user['prevision'] = "ISAPRE";
                    }
                }
                else {
                    /* Error */
                    $error = array("error" => $result->getCertificadoPrevisionalResult->replyTO->errorM);
                }
            }

            //echo '<pre>';
            //print_r($result);
            //dd($result);

            return isset($user) ? response()->json($user) : response()->json($error);
        }
        else
        {
           echo "no se especificó el run y el dv como parámetro";
        }
    }
}
