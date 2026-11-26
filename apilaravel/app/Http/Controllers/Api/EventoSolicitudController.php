<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\EnrutadorEventoSolicitud;
use App\Services\FiltroEventoSolicitud;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class EventoSolicitudController extends Controller
{
    public function store(
        Request $request,
        FiltroEventoSolicitud $filtro,
        EnrutadorEventoSolicitud $enrutador,
    ): JsonResponse {
        $header = $request->header('X-Correlation-ID');
        $cid = is_string($header) && Str::isUuid($header) ? $header : (string) Str::uuid();
        $validador = Validator::make($request->json()->all(), [
            'id' => ['required', 'uuid'],
            'tipo' => ['required', 'string', 'max:80', 'regex:/^[a-z][a-z0-9._-]*$/D'],
            'prioridad' => ['required', 'in:normal,alta'],
            'datos' => ['required', 'array'],
            'datos.solicitud_id' => ['required', 'integer', 'min:1'],
            'datos.entorno' => ['required', 'in:operativo,prueba'],
        ]);

        if ($validador->fails()) {
            $campos = $validador->errors()->keys();
            Log::warning('evento_solicitud.invalido', ['cid' => $cid, 'campos' => $campos]);

            return $this->responder($cid, 422, ['estado' => 'invalido', 'campos' => $campos]);
        }

        $evento = $validador->validated();
        $contexto = ['cid' => $cid, 'evento_id' => $evento['id'], 'tipo' => $evento['tipo']];
        $motivo = $filtro->motivo($evento);

        if ($motivo !== null) {
            Log::info('evento_solicitud.filtrado', $contexto + ['motivo' => $motivo]);

            return $this->responder($cid, 200, ['estado' => 'filtrado', 'motivo' => $motivo]);
        }

        $entrega = $enrutador->enviar($evento);

        if ($entrega === null) {
            Log::warning('evento_solicitud.no_enrutable', $contexto + ['destino' => 'no_enrutables']);

            return $this->responder($cid, 422, ['estado' => 'no_enrutable', 'destino' => 'no_enrutables']);
        }

        Log::info('evento_solicitud.procesado', $contexto + ['destino' => $entrega['destino']]);

        return $this->responder($cid, 200, ['estado' => 'procesado'] + $entrega);
    }

    private function responder(string $cid, int $status, array $datos): JsonResponse
    {
        return response()->json($datos + ['correlation_id' => $cid], $status)
            ->header('X-Correlation-ID', $cid);
    }
}
