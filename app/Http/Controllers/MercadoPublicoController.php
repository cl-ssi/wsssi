<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Http;

class MercadoPublicoController extends Controller
{
    /**
     * @param  string $code
     * @return \Illuminate\Http\Response
     */
    public function getPurchaseOrder($code)
    {
        try {
            $response = Http::get('https://api.mercadopublico.cl/servicios/v1/publico/ordenesdecompra.json', [
                'codigo' => $code,
                'ticket' => env('TICKET_MERCADO_PUBLICO')
            ]);

            $oc = json_decode($response);

            if($response->status() != Response::HTTP_OK) {
                return response()->json([
                    'message' => "No existe en nuestros registros y no se pudo conectar con MercadoPublico. Error ".$response->status(),
                ], Response::HTTP_BAD_REQUEST);
            }

            if($response->status() == Response::HTTP_OK) {
                if($oc->Cantidad == 0) {
                    return response()->json([
                        'message' => "La orden de compra está eliminada, no aceptada o no válida.",
                    ], Response::HTTP_BAD_REQUEST);
                } elseif($oc->Listado[0]->Estado == 'Cancelada') {
                    return response()->json([
                        'message' => "La orden de compra esta cancelada.",
                    ], Response::HTTP_BAD_REQUEST);
                }

                if(($oc->Cantidad > 0) && ($oc->Listado[0]->Estado != 'Cancelada')) {
                    return response()->json($oc, Response::HTTP_OK);
                }
            }

        } catch(\Illuminate\Http\Client\ConnectionException $e) {
            return "No existe en nuestros registros y no se pudo conectar con MercadoPublico.";
            // return response()->json([
            //     'message' => $th->getCode() == 0 ? 'No fue posible establecer conexión con Mercado Público.' : $th->getMessage(),
            //     'detail' => 'Disculpe, no pudimos obtener los datos de la orden de compra, intente nuevamente.',
            //     'code' => $th->getCode(),
            // ], Response::HTTP_BAD_REQUEST);
        }
    }
    /**
     * @param  string $code
     * @return \Illuminate\Http\Response
     */
    public function getPurchaseOrderV2($code)
    {
        try {
            $response = Http::get('https://api.mercadopublico.cl/servicios/v2/publico/ordenesdecompra.json', [
                'codigo' => $code,
                'ticket' => env('TICKET_MERCADO_PUBLICO')
            ]);

            if($response->status() != Response::HTTP_OK) {
                return response()->json([
                    'message' => "No existe en nuestros registros y no se pudo conectar con MercadoPublico. Error ".$response->status(),
                ], Response::HTTP_BAD_REQUEST);
            }

            $oc = json_decode($response);

            if($response->status() == Response::HTTP_OK) {
                if($oc->Cantidad == 0) {
                    return response()->json([
                        'message' => "La orden de compra está eliminada, no aceptada o no válida.",
                    ], Response::HTTP_BAD_REQUEST);
                } elseif($oc->Listado[0]->Estado == 'Cancelada') {
                    return response()->json([
                        'message' => "La orden de compra esta cancelada.",
                    ], Response::HTTP_BAD_REQUEST);
                }

                if(($oc->Cantidad > 0) && ($oc->Listado[0]->Estado != 'Cancelada')) {
                    return response()->json($oc, Response::HTTP_OK);
                }
            }

        } catch (\Throwable $th) {
            return response()->json([
                'message' => $th->getMessage(),
                'detail' => 'Disculpe, no pudimos obtener los datos de la orden de compra, intente nuevamente.',
                'code' => $th->getCode(),
            ], Response::HTTP_BAD_REQUEST);
        }
    }
}
