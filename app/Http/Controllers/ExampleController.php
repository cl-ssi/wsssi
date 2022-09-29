<?php

namespace App\Http\Controllers;
use GuzzleHttp\Client as Client;
use Illuminate\Http\Request;

class ExampleController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }

    public function certificate(Request $request)
    {
        if($request->has('run') AND $request->has('dv'))
        {
            $rut = $request->input('run');
            $dv  = $request->input('dv');

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

            if ($result === false)
                $error = array("error" => "No se pudo conectar a FONASA");
            else
            {
                if($result->getCertificadoPrevisionalResult->replyTO->estado == 0)
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
                    $user['desRegion']         = $beneficiario->desRegion;
                    $user['desComuna']         = $beneficiario->desComuna;
                    $user['direccion']      = $beneficiario->direccion;
                    $user['telefono']      = $beneficiario->telefono;

                    if($afiliado->desEstado == 'ACTIVO')
                        $user['tramo'] = $afiliado->tramo;
                    else
                        $user['tramo'] = null;

                    $result = $this->findFhir($beneficiario->rutbenef, $beneficiario->dgvbenef);

                    if($result['find'] == true)
                    {
                        $fhir = $result['fhir'];
                    }
                    else
                    {
                        $new = $this->saveFhir($beneficiario);
                        $fhir = $new['fhir'];
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

    public function saveFhir($beneficiario)
    {
        $names = explode(" ", $beneficiario->nombres);
        $data = [
            "resourceType" => "Patient",
            "birthDate" => $beneficiario->fechaNacimiento,
            "gender" => $beneficiario->generoDes == "Masculino" ? "male" : "female",
            "name" => [[
                "use" => "official",
                "text" => "$beneficiario->nombres $beneficiario->apell1 $beneficiario->apell2",
                "family" => "$beneficiario->apell1 $beneficiario->apell2",
                "given" => $names
            ]],
            "identifier" => [
                "system" => "http://www.registrocivil.cl/run",
                "use" => "official",
                "value" => "$beneficiario->rutbenef-$beneficiario->dgvbenef",
                "type" => [
                    "text" => "RUN"
                ]
            ]
        ];

        $client = new Client(['base_uri' => 'http://hapi.fhir.org/baseR4/']);
        $response = $client->request('POST', 'Patient', [
            'json' => $data
        ]);

        $result['fhir'] = null;

        if($response->getStatusCode() == 201)
        {
            $response = $response->getBody()->getContents();
            $result['fhir'] = json_decode($response);
        }

        return $result;
    }

    public function findFhir($run, $dv)
    {
        $client = new Client(['base_uri' => 'http://hapi.fhir.org/baseR4/']);
        $response = $client->request(
            'GET',
            "Patient?identifier=http://www.registrocivil.cl/run|$run-$dv"
        );

        if($response->getStatusCode() == 200)
        {
            $response = $response->getBody()->getContents();
            $response = json_decode($response);

            if($response->total > 0)
            {
                $result['fhir'] = $response;
                $result['find'] = true;
            }
            else
            {
                $result['fhir'] = null;
                $result['find'] = false;
            }
        }

        return $result;
    }
}
