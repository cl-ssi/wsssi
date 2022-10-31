<?php

namespace App\Services;

use App\Traits\GoogleToken;
use GuzzleHttp\Client as Client;
use Illuminate\Support\Str;

class FhirService
{
    use GoogleToken;

    public function save($person)
    {
        $person['name'] = trim($person['name']);

        $person['fathers_family'] = trim($person['fathers_family']);
        $person['mothers_family'] = trim($person['mothers_family']);

        $person['fathers_family'] = ($person['fathers_family'] != "") ? $person['fathers_family'] : null;
        $person['mothers_family'] = ($person['mothers_family'] != "") ? $person['mothers_family'] : null;

        $names = explode(" ", $person['name']);

        $fullname = isset($person['fathers_family']) ? $person['name'] . " " . $person['fathers_family'] : $person['name'];
        $fullname = isset($person['mothers_family']) ? $fullname . " " . $person['mothers_family'] : $fullname;

        $run = $person['run'] . "-" . Str::upper($person['dv']);
        $extensionFamily = [];
        $family = "";

        if(isset($person['fathers_family']))
        {
            $extensionFamily[] = [
                "url" => "http://hl7.org/fhir/StructureDefinition/humanname-fathers-family",
                "valueString" => $person['fathers_family']
            ];
        }

        if(isset($person['mothers_family']))
        {
            $extensionFamily[] = [
                "url" => "http://hl7.org/fhir/StructureDefinition/humanname-mothers-family",
                "valueString" => $person['mothers_family']
            ];
        }

        $data = [
            "resourceType" => "Patient",
            "name" => [[
                "use" => "temp",
                "text" => $fullname,
                "given" => $names,
                "_use" => [
                    "extension" => [
                        [
                            "url" => "https:://fonasa.cl",
                            "valueString" => "fonasa"
                        ]
                    ]
                ]
            ]],
            "identifier" => [[
                "system" => "http://www.registrocivil.cl/run",
                "use" => "official",
                "value" => $run,
                "type" => [
                    "text" => "RUN"
                ]
            ]]
        ];

        if(count($extensionFamily) >= 1)
        {
            $data["name"][0]["_family"] = [
                "extension" => $extensionFamily
            ];
        }

        if($person["birthday"] != null)
            $data["birthDate"] = $person['birthday'];

        if($person["gender"])
            $data["gender"] = ($person['gender'] == "Masculino" || $person['gender'] == "male") ? "male" : "female";

        if(isset($person['fathers_family']))
            $family = $person['fathers_family'];

        if(isset($person['mothers_family']))
            $family = $family . " ". $person['mothers_family'];

        if(isset($person['fathers_family']) || isset($person['mothers_family']))
            $data["name"][0]["family"] = trim($family);

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

    public function find($run, $dv)
    {
        $dv = Str::upper($dv);
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
                $result['idFhir'] = $response->entry[0]->resource->id;
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

    public function updateName($person, $idFhir)
    {
        $names = implode(" ", $person['nombres']);
        $family = implode(" ", $person['apellidos']);
        $fullname = "$names $family";
        $extensionFamily = [];

        if(count($person['apellidos']) >= 1)
        {
            $extensionFamily[] = [
                "url" => "http://hl7.org/fhir/StructureDefinition/humanname-fathers-family",
                "valueString" => $person['apellidos'][0]
            ];
        }

        if(count($person['apellidos']) == 2)
        {
            $extensionFamily[] = [
                "url" => "http://hl7.org/fhir/StructureDefinition/humanname-mothers-family",
                "valueString" => $person['apellidos'][1]
            ];
        }

        $resource = [
            "use" => "official",
            "text" => $fullname,
            "family" => $family,
            "given" => $person['nombres'],
            "_use" => [
                "extension" => [
                    [
                        "url" => "https://claveunica.gob.cl/",
                        "valueString" => "claveunica"
                    ]
                ]
            ]
        ];

        if(count($extensionFamily) >= 1)
        {
            $resource["_family"]  = [
                "extension" => $extensionFamily,
            ];
        }

        $data = [[
            "op" => "replace",
            "path" => "/name/0",
            "value" => $resource
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

        $error = true;
        if ($response->getStatusCode() == 200)
            $error = false;

        return $error;
    }
}
