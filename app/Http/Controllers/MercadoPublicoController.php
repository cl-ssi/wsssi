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

            if($response->successful()) {
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
            } else {
                return response()->json([
                    'message' => "El número de orden de compra es errado.",
                ], Response::HTTP_BAD_REQUEST);
            }

        } catch (\Throwable $th) {
            return response()->json([
                'message' => $th->getCode() == 0 ? 'No fue posible establecer conexión con Mercado Público.' : $th->getMessage(),
                'detail' => 'Disculpe, no pudimos obtener los datos de la orden de compra, intente nuevamente.',
                'code' => $th->getCode(),
            ], Response::HTTP_BAD_REQUEST);
        }
    }
}
