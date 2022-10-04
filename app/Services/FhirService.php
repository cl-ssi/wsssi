<?php

namespace App\Services;

use App\Traits\GoogleToken;
use GuzzleHttp\Client as Client;

class FhirService
{
    use GoogleToken;

    public function __construct()
    {

    }

    public function save($person)
    {
        $names = explode(" ", $person['name']);
        $data = [
            "resourceType" => "Patient",
            "birthDate" => $person['birthday'],
            "gender" => ($person['gender'] == "Masculino") ? "male" : "female",
            "name" => [[
                "use" => "temp",
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

    public function updateName($fullname, $idFhir)
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

        $error = true;
        if ($response->getStatusCode() == 200)
            $error = false;

        return $error;
    }
}
