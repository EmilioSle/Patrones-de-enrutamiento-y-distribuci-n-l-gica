<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\ProcesarExportacionCarrito;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ExportacionCarritoController extends Controller
{
    public function store(Request $request, string $modo): JsonResponse
    {
        $datos = $request->validate([
            'formato' => ['required', 'in:csv'],
        ]);

        $cliente = $request->user()->getAuthIdentifier();
        $correlationId = $request->header('X-Correlation-ID');
        $mensaje = [
            'message_id' => (string) Str::uuid(),
            'correlation_id' => is_string($correlationId) && Str::isUuid($correlationId)
                ? $correlationId
                : (string) Str::uuid(),
            'tipo' => 'carrito.exportacion.solicitada',
            'ocurrido_en' => now()->toISOString(),
            'payload' => [
                'formato' => $datos['formato'],
                'articulos' => array_map(
                    static fn (array $articulo): array => [
                        'producto_id' => $articulo['producto_id'],
                        'cantidad' => $articulo['cantidad'],
                        'precio_centavos' => $articulo['precio_centavos'],
                    ],
                    Cache::get('carrito:'.$cliente, []),
                ),
            ],
        ];

        $exportacion = [
            'message_id' => $mensaje['message_id'],
            'correlation_id' => $mensaje['correlation_id'],
            'cliente_id' => $cliente,
            'estado' => 'pendiente',
            'solicitado_en' => $mensaje['ocurrido_en'],
        ];
        $trabajo = new ProcesarExportacionCarrito(json_encode($mensaje, JSON_THROW_ON_ERROR));

        DB::transaction(function () use ($exportacion, $trabajo, $modo): void {
            DB::table('exportaciones_carrito')->insert($exportacion);

            if ($modo === 'asincrona') {
                Bus::dispatch($trabajo->onConnection('exportaciones')->onQueue(ProcesarExportacionCarrito::COLA));
                Log::info('Exportación publicada', [
                    'message_id' => $exportacion['message_id'],
                    'correlation_id' => $exportacion['correlation_id'],
                    'estado' => 'pendiente',
                    'cola' => ProcesarExportacionCarrito::COLA,
                ]);
            } else {
                Log::info('Exportación síncrona recibida', [
                    'message_id' => $exportacion['message_id'],
                    'correlation_id' => $exportacion['correlation_id'],
                    'estado' => 'pendiente',
                ]);
                $trabajo->handle();
            }
        });

        $consulta = route('carrito.exportaciones.show', $mensaje['message_id']);

        return response()->json([
            'message_id' => $mensaje['message_id'],
            'correlation_id' => $mensaje['correlation_id'],
            'estado' => $modo === 'asincrona' ? 'pendiente' : 'completada',
            'consulta' => $consulta,
        ], $modo === 'asincrona' ? 202 : 201)->withHeaders([
            'X-Correlation-ID' => $mensaje['correlation_id'],
            'Location' => $consulta,
        ]);
    }

    public function show(Request $request, string $id): JsonResponse
    {
        $exportacion = DB::table('exportaciones_carrito')
            ->where('cliente_id', $request->user()->getAuthIdentifier())
            ->where('message_id', $id)
            ->first(['message_id', 'correlation_id', 'estado', 'solicitado_en', 'finalizado_en', 'resultado_csv']);

        abort_if($exportacion === null, 404);

        return response()->json($exportacion)->header('X-Correlation-ID', $exportacion->correlation_id);
    }
}
