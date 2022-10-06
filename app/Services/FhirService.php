<?php

namespace App\Services;

use App\Traits\GoogleToken;
use GuzzleHttp\Client as Client;

class FhirService
{
    use GoogleToken;

    public function save($person)
    {
        $person['name'] = trim($person['name']);
        $person['fathers_family'] = isset($person['fathers_family']) ? trim($person['fathers_family']) : null;
        $person['mothers_family'] = isset($person['mothers_family']) ? trim($person['mothers_family']) : null;
        $names = explode(" ", $person['name']);
        $fullname = isset($person['fathers_family']) ? $person['name'] . " " . $person['fathers_family'] : $person['name'];
        $fullname = isset($person['mothers_family']) ? $fullname . " " . $person['mothers_family'] : $fullname;
        $extensionFamily = [];

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
                            "url" => "http:://iquique.com",
                            "valueString" => "fonasa"
                        ]
                    ]
                ]
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

        if(isset($person['fathers_family']) && isset($person['mothers_family']))
            $data["name"][0]["family"] = $person['fathers_family'] . " " . $person['mothers_family'];

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

    public function updateName($name, $idFhir)
    {
        $names = implode(" ", $name['nombres']);
        $family = implode(" ", $name['apellidos']);
        $fullname = "$names $family";
        $fathersFamily = (count($name['apellidos']) >= 1) ? $name['apellidos'][0] : "";
        $mothersFamily = (count($name['apellidos']) == 2) ? $name['apellidos'][1] : "";

        $data = [[
            "op" => "replace",
            "path" => "/name/0",
            "value" => [
                "use" => "official",
                "text" => $fullname,
                "family" => $family,
                "given" => $name['nombres'],
                "_family" => [
                    "extension" => [
                        [
                            "url" => "http://hl7.org/fhir/StructureDefinition/humanname-fathers-family",
                            "valueString" => $fathersFamily
                        ],
                        [
                            "url" => "http://hl7.org/fhir/StructureDefinition/humanname-mothers-family",
                            "valueString" => $mothersFamily
                        ]
                    ]
                ],
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

        $error = true;
        if ($response->getStatusCode() == 200)
            $error = false;

        return $error;
    }
}
