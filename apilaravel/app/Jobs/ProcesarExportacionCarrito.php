<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class ProcesarExportacionCarrito implements ShouldQueue
{
    use Queueable;

    public const COLA = 'exportaciones-carrito';

    public int $tries = 3;

    public int $backoff = 5;

    public function __construct(public string $mensaje) {}

    public function handle(): void
    {
        $mensaje = json_decode($this->mensaje, true, flags: JSON_THROW_ON_ERROR);
        $lineas = ['producto_id,cantidad,precio_centavos,subtotal_centavos'];
        foreach ($mensaje['payload']['articulos'] as $articulo) {
            $lineas[] = sprintf('%d,%d,%d,%d',
                $articulo['producto_id'],
                $articulo['cantidad'],
                $articulo['precio_centavos'],
                $articulo['cantidad'] * $articulo['precio_centavos'],
            );
        }

        $this->finalizar($mensaje, 'completada', implode("\n", $lineas)."\n");
    }

    public function failed(?Throwable $exception): void
    {
        $mensaje = json_decode($this->mensaje, true, flags: JSON_THROW_ON_ERROR);
        $this->finalizar($mensaje, 'fallida', null);
    }

    private function finalizar(array $mensaje, string $estado, ?string $csv): void
    {
        $actualizadas = DB::table('exportaciones_carrito')
            ->where('message_id', $mensaje['message_id'])
            ->where('correlation_id', $mensaje['correlation_id'])
            ->where('estado', 'pendiente')
            ->update([
                'estado' => $estado,
                'resultado_csv' => $csv,
                'finalizado_en' => now()->toISOString(),
            ]);

        if ($actualizadas > 0) {
            Log::log($estado === 'fallida' ? 'warning' : 'info', 'Exportación finalizada', [
                'message_id' => $mensaje['message_id'],
                'correlation_id' => $mensaje['correlation_id'],
                'estado' => $estado,
            ]);
        }
    }
}
