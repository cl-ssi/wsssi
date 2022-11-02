<?php

namespace App\Helpers;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class Fonasa
{
    /**
     * Get prevision
     *
     * @param  array  $patient
     * @return string
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
                elseif($patient['codigo_isapre'] == ' ' && Str::contains($patient['codigo_afiliado'], 'BLOQUEADO'))
                {
                    $prevision = Str::replace('BLOQUEADO', '', $patient['codigo_afiliado']);
                    $prevision = Str::replace('POR', '', $prevision);
                    $prevision = trim($prevision);
                }
                else
                {
                    $prevision = $patient['codigo_afiliado'];
                    // Log::channel('slack')->warning("El paciente tiene una prevision: "  . $patient['codigo_afiliado'], $patient);
                }
                break;

            case 'ACTIVO':
                if($patient['tramo'] != null)
                    $prevision = "FONASA " . $patient['tramo'];
                else
                {
                    $prevision = "FONASA";
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

    /**
     * Get locked
     *
     * @param  array  $patient
     * @return boolean
     */
    public static function getLocked($patient)
    {
        $locked = false;
        if(Str::contains($patient['codigo_afiliado'], 'BLOQUEADO'))
            $locked = true;
        return $locked;
    }

    /**
     * Get section
     *
     * @param  array  $patient
     * @param  string  $sectionFonasa
     * @return boolean
     */
    public static function getSection($patient, $sectionFonasa)
    {
        $section = null;
        if($patient['estado_afiliado'] == 'ACTIVO')
            $section = trim($sectionFonasa);

        return ($section != "" && $section != null) ? $section : null;
    }
}
