<?php

namespace App\Http\Controllers;

use App\Services\FhirService;
use App\Traits\GoogleToken;
use Illuminate\Http\Request;
use App\Services\FonasaService;

use GuzzleHttp\Client as Client;

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

    public function query(Request $request)
    {
        if ($request->has('run') && $request->has('dv'))
        {
            $fonasa = new FonasaService($request->input('run'), $request->input('dv'));
            $responseFonasa = $fonasa->getPerson();

            if ($responseFonasa['error'] == false)
            {
                $fhir = new FhirService;
                $responseFhir = $fhir->find($request->input('run'), $request->input('dv'));

                if ($responseFhir['find'] == true)
                    $fhir = $responseFhir['fhir'];
                else
                {
                    $new = $fhir->save($responseFonasa['user']);
                    $fhir = $new['fhir'];
                }
            }

            return ($responseFonasa['error'] == true)
                ? response()->json($responseFonasa['message'])
                : response()->json([
                    'user' => $responseFonasa['user'],
                    'fhir' => $fhir,
                    'find' => $responseFhir['find'],
                ]);
        }
        else
            return response()->json("No se especificó el run y el dv como parámetro");
    }

    public function certificate(Request $request)
    {
        if ($request->has('run') and $request->has('dv'))
        {
            $rut = $request->input('run');
            $dv = $request->input('dv');

            $wsdl = 'wsdl/fonasa/CertificadorPrevisionalSoap.wsdl';
            $client = new \SoapClient($wsdl, array('trace' => TRUE));
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

            if ($result === false)
                $error = array("error" => "No se pudo conectar a FONASA");
            else
            {
                if ($result->getCertificadoPrevisionalResult->replyTO->estado == 0)
                {
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

                    if ($afiliado->desEstado == 'ACTIVO')
                        $user['tramo'] = $afiliado->tramo;
                    else
                        $user['tramo'] = null;

                    // $result = $this->findFhir($beneficiario->rutbenef, $beneficiario->dgvbenef);

                    if ($result['find'] == true)
                        $fhir = $result['fhir'];
                    else
                    {
                        // $new = $this->saveFhir($beneficiario);
                        // $fhir = $new['fhir'];
                    }
                }
                else
                    $error = array("error" => $result->getCertificadoPrevisionalResult->replyTO->errorM);
            }

            return isset($user)
                ? response()->json(['user' => $user, 'fhir' => $fhir, 'find' => $result['find']])
                : response()->json($error);
        }
        else
            echo "no se especificó el run y el dv como parámetro";
    }

    public function update(Request $request)
    {
        if($request->has('run') && $request->has('dv'))
        {
            $fonasa = new FonasaService($request->input('run'), $request->input('dv'));
            $responseFonasa = $fonasa->getPerson();

            $fhir = new FhirService;
            $responseFhir = $fhir->find($request->input('run'), $request->input('dv'));

            if($responseFonasa['error'] == false)
            {
                $fullname = $responseFonasa['user']['name'] . " " . $responseFonasa['user']['fathers_family'] . " " . $responseFonasa['user']['mothers_family'];

                if($responseFhir['find'] == true)
                {
                    $qtyNames = count($responseFhir['fhir']->entry[0]->resource->name);
                    $idFhir = $responseFhir['idFhir'];
                    $fhir = $responseFhir['fhir'];
                    if($qtyNames == 1)
                        $error = $fhir->updateName($fullname, $idFhir);
                }
                else
                {
                    $newFhir = $fhir->save($responseFonasa['user']);
                    $fhir = $newFhir['fhir'];
                    return response()->json($fhir);
                    // $fhir->updateName($fullname, $idFhir);
                }

                $find = $fhir->find($request->input('run'), $request->input('dv'));

                return response()->json($find['fhir']);
            }

            return response()->json($responseFonasa['message']);
        }
        return response()->json("No se especificó el run y el dv como parámetro");
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
