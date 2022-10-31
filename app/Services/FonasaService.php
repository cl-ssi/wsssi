<?php

namespace App\Services;

class FonasaService
{
    public $rut;
    public $dv;

    public function __construct($rut, $dv)
    {
        $this->rut = $rut;
        $this->dv = $dv;
    }

    public function getPerson()
    {
        $wsdl = 'wsdl/fonasa/CertificadorPrevisionalSoap.wsdl';
        $client = new \SoapClient($wsdl, array(
            'trace' => TRUE,
            'connection_timeout'=> 5
        ));
        $parameters = array(
            "query" => array(
                "queryTO" => array(
                    "tipoEmisor"  => 3,
                    "tipoUsuario" => 2
                ),
                "entidad"           => env('FONASA_ENTIDAD'),
                "claveEntidad"      => env('FONASA_CLAVE'),
                "rutBeneficiario"   => $this->rut,
                "dgvBeneficiario"   => $this->dv,
                "canal"             => 3
            )
        );
        $result = $client->getCertificadoPrevisional($parameters);

        if ($result === false)
        {
            $response['user'] = null;
            $response['error'] = true;
            $response['message'] = "No se pudo conectar a FONASA";
        }
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
                $user['desRegion']      = $beneficiario->desRegion;
                $user['desComuna']      = $beneficiario->desComuna;
                $user['direccion']      = $beneficiario->direccion;
                $user['telefono']       = $beneficiario->telefono;
                $user['estado_afiliado'] = $afiliado->desEstado;
                $user['codigo_afiliado'] = $certificado->coddesc;
                $user['codigo_prais']    = $certificado->codigoprais;
                $user['descripcion_prais'] = $certificado->descprais;
                // $user['descripcion_isapre'] = $certificado->descIsapre;

                if($afiliado->desEstado == 'ACTIVO') {
                    $user['tramo'] = $afiliado->tramo;
                    $user['prevision'] = "FONASA $afiliado->tramo";
                }
                else
                {
                    $user['tramo'] = null;
                    $user['prevision'] = "ISAPRE";
                }

                $response['user'] = $user;
                $response['error'] = false;
                $response['message'] = null;
            }
            else
            {
                $response['user'] = null;
                $response['error'] = true;
                $response['message'] =  $result->getCertificadoPrevisionalResult->replyTO->errorM;
            }
        }

        return $response;
    }

}
