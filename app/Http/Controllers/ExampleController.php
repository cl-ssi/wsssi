<?php

namespace App\Http\Controllers;

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
        if ($request->has('run') and $request->has('dv'))
        {
            $fonasa = new FonasaService($request->input('run'), $request->input('dv'));
            $response = $fonasa->getPerson();

            if ($response['error'] == false)
            {
                $result = $this->findFhir($request->input('run'), $request->input('dv'));

                if ($result['find'] == true)
                    $fhir = $result['fhir'];
                else
                {
                    $new = $this->saveFhir($response);
                    $fhir = $new['fhir'];
                }
            }

            return ($response['error'] == true)
                ? response()->json($response['message'])
                : response()->json([
                    'user' => $response['user'],
                    'fhir' => $fhir,
                    'find' => $response['find'],
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

                    $result = $this->findFhir($beneficiario->rutbenef, $beneficiario->dgvbenef);

                    if ($result['find'] == true)
                        $fhir = $result['fhir'];
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

    public function saveFhir($person)
    {
        $names = explode(" ", $person['name']);
        $data = [
            "resourceType" => "Patient",
            "birthDate" => $person['birthday'],
            "gender" => $person['gender'] == "Masculino" ? "male" : "female",
            "name" => [[
                "use" => "temp", //temp
                "text" => $person['name'] . " " . $person['fathers_family'] . " " .$person['mothers_family'],
                "family" => $person['fathers_family'] . " " . $person['mothers_family'],
                "given" => $names
            ]],
            "identifier" => [[
                "system" => "http://www.registrocivil.cl/run",
                "use" => "temp",
                "value" => $person['run'] . "-" . $person['dv'],
                "type" => [
                    "text" => "RUN"
                ]
            ]]
        ];

        $client = new Client(['base_uri' => $this->getUrlBase()]);
        $response = $client->request(
            'POST',
            'Patient',
            [
                'json' => $data,
                'headers' => ['Authorization' => 'Bearer ' . $this->getToken()],
            ]
        );

        $result['fhir'] = null;

        if ($response->getStatusCode() == 201)
        {
            $response = $response->getBody()->getContents();
            $result['fhir'] = json_decode($response);
        }

        return $result;
    }

    public function findFhir($run, $dv)
    {
        $client = new Client(['base_uri' => $this->getUrlBase()]);
        $response = $client->request(
            'GET',
            "Patient?identifier=http://www.registrocivil.cl/run|$run-$dv",
            [
                'headers' => ['Authorization' => 'Bearer ' . $this->getToken()],
            ]
        );

        if ($response->getStatusCode() == 200)
        {
            $response = $response->getBody()->getContents();
            $response = json_decode($response);

            if ($response->total >= 1)
            {
                $result['fhir'] = $response;
                $result['find'] = true;
                $result['id'] = $response->id;
            }
            else
            {
                $result['fhir'] = null;
                $result['find'] = false;
                $result['id'] = null;
            }
        }

        return $result;
    }

    public function update()
    {
        $result = $this->findFhir("15287582", "7");

        if ($result['find'] == true)
        {
            // return response()->json($result['fhir']);
            // $obj = json_decode($result['fhir']);

            $qtyNames = count($result['fhir']->entry[0]->resource->name);
            $idFhir = $result['fhir']->entry[0]->resource->id;

            $data = [
                [
                    "op" => "replace",
                    "path" => "/birthDate",
                    "value" => "1998-01-14"
                ],
                [
                    "op" => "add",
                    "path" => "/name/0",
                    "value" => [
                        "use" => "temp",
                        "text" => "ÁLVARO RAYMUNDO EDGARDO TORRES FUCHSLOCHER",
                    ]
                ]
            ];

            $client = new Client(['base_uri' => 'http://hapi.fhir.org/baseR4/']); // $this->getUrlBase()
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

            return response()->json(json_decode($response->getBody()->getContents()));
        }

        return response()->json(['find' => false]);
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
