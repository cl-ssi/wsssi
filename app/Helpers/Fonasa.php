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
                elseif($patient['codigo_isapre'] == ' ')
                    $prevision = $patient['codigo_afiliado'];
                else
                {
                    $prevision = "INDETERMINADA";
                    Log::channel('slack')->warning("El paciente tiene una prevision indeterminada", $patient);
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