<?php

namespace App\Services;

use App\Helpers\Fonasa;
use Illuminate\Support\Facades\Log;
use SoapFault;

class FonasaService
{
    public $rut;
    public $dv;

    public function __construct($rut, $dv)
    {
        $this->rut = $rut;
        $this->dv = $dv;
    }

    /**
     * Get a person from the fonasa soap service
     *
     * @return void
     */
    public function getPerson()
    {
        $wsdl = 'wsdl/fonasa/CertificadorPrevisionalSoap.wsdl';
        try {
            $context = stream_context_create([
                'ssl' => [
                    'verify_peer' => false,      // Solo para pruebas
                    'verify_peer_name' => false,
                ],
                'http' => [
                    'timeout' => 30,              // Timeout general de lectura
                ]
            ]);
            $client = new \SoapClient($wsdl, [
                'trace' => true,
                'connection_timeout' => 30,       // Tiempo de conexión inicial
                'stream_context' => $context,
                'exceptions' => true,             // Lanza excepción en error
            ]);
        } catch (SoapFault $e) {
            Log::error('Error creando cliente SOAP: ' . $e->getMessage());
            return ['error' => true, 'message' => 'Error de conexión con FONASA (cliente): ' . $e->getMessage()];
        }        
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
                "canal"             => 2
            )
        );
        try {
            $result = $client->getCertificadoPrevisional($parameters);
        } catch (SoapFault $e) {
            Log::error('Error en llamada SOAP getCertificadoPrevisional: ' . $e->getMessage());
            Log::error('Última petición: ' . $client->__getLastRequest());
            Log::error('Última respuesta: ' . $client->__getLastResponse());
            return ['error' => true, 'message' => 'Error al obtener certificado: ' . $e->getMessage()];
        }

        /*
        $client = new \SoapClient($wsdl, array(
            'trace' => TRUE,
            // the waiting time to establish the connection to the server
            'connection_timeout'=> 30, // in seconds
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
        */

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
                $user['descripcion_isapre'] = $certificado->desIsapre;
                $user['codigo_isapre']  = $certificado->cdgIsapre;
                $user['tramo'] = Fonasa::getSection($user, $afiliado->tramo);
                $user['prevision'] = Fonasa::getPrevision($user);
                $user['bloqueado'] = Fonasa::getLocked($user);

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
