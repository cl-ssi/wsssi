<?php

namespace App\Helpers;

use Illuminate\Support\Facades\Log;

class Fonasa
{
    /**
     * Get prevision
     *
     */
    public static function getPrevision($patient)
    {
        switch ($patient['estado_afiliado'])
        {
            case 'INDEPENDIENTE':
                if($patient['codigo_prais'] == "111")
                    $prevision = "PRAIS";
                else
                    $prevision = "INDEPENDIENTE";
                break;

            case 'INDIGENTE':
                if($patient['codigo_prais'] == "111")
                    $prevision = "PRAIS";
                else
                    $prevision = "INDIGENTE";
                break;

            case 'PENSIONADO':
                if($patient['codigo_prais'] == "111")
                    $prevision = "PRAIS";
                else
                    $prevision = "PENSIONADO";
                break;

            case ' ':
                if($patient['codigo_prais'] == '111')
                    $prevision = "PRAIS";
                elseif($patient['codigo_isapre'] != ' ')
                    $prevision = "ISAPRE";
                elseif($patient['codigo_isapre'] == ' ' && $patient['codigo_afiliado'] == "BLOQUEADO CAPREDENA")
                    $prevision = "CAPREDENA";
                elseif($patient['codigo_isapre'] == ' ' && $patient['codigo_afiliado'] == "BLOQUEADO DIPRECA")
                    $prevision = "DIPRECA";
                elseif($patient['codigo_isapre'] == ' ' && $patient['codigo_afiliado'] == "BLOQUEADO SIN INFORMACION")
                    $prevision = $patient['codigo_afiliado'];
                else
                {
                    $prevision = $patient['codigo_afiliado'];
                    Log::channel('slack')->warning("El paciente tiene una prevision: "  . $patient['codigo_afiliado'], $patient);
                }
                break;

            case 'ACTIVO':
                if($patient['tramo'] != null)
                    $prevision = "FONASA " . $patient['tramo'];
                else
                {
                    $prevision = "INDETERMINADA";
                    Log::channel('slack')->warning("El paciente tiene estado afiliado activo y tramo nulo", $patient);
                }
                break;

            default:
                $prevision = $patient['estado_afiliado'];
                Log::channel('slack')->warning("El paciente tiene un estado afiliado no contemplado", $patient);
                break;
        }
        return $prevision;
    }
}