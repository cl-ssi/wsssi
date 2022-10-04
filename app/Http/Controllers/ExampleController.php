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
            $response = $fonasa->getPerson();

            if ($response['error'] == false)
            {
                $fhir = new FhirService;
                $result = $fhir->find($request->input('run'), $request->input('dv'));

                if ($result['find'] == true)
                    $fhir = $result['fhir'];
                else
                {
                    $new = $fhir->save($response['user']);
                    $fhir = $new['fhir'];
                }
            }

            return ($response['error'] == true)
                ? response()->json($response['message'])
                : response()->json([
                    'user' => $response['user'],
                    'fhir' => $fhir,
                    'find' => $result['find'],
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
                if($responseFhir['find'] == true)
                {
                    $qtyNames = count($responseFhir['fhir']->entry[0]->resource->name);
                    $idFhir = $responseFhir['fhir']->entry[0]->resource->id;

                    $fullname = $responseFonasa['user']['name'] . " " . $responseFonasa['user']['fathers_family'] . " " . $responseFonasa['user']['mothers_family'];

                    if($qtyNames == 1)
                    {
                        $data = [[
                            "op" => "add",
                            "path" => "/name/0",
                            "value" => [
                                "use" => "official",
                                "text" => $fullname,
                            ]
                        ]];

                        $client = new Client(['base_uri' => $this->getUrlBase()]);
                        $response = $client->request(
                            'PATCH',
                            "Patient/" . $idFhir,
                            [
                                'json' => $data,
                                'headers' => [
                                    'Authorization' => 'Bearer ' . $this->getToken(),
                                    'Content-Type' => 'application/json-patch+json'
                                ],
                            ]
                        );
                    }

                    return response()->json([
                        $responseFhir['fhir'],
                        $qtyNames
                    ]);
                }
                else
                {
                    // guardar en fhir y actualizar agregar el name de fonasa como use official
                }
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
