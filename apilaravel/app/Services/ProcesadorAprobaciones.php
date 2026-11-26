<?php

namespace App\Services;

class ProcesadorAprobaciones
{
    public function procesar(array $evento): array
    {
        return [
            'accion' => 'preparar_aviso_aprobacion',
            'solicitud_id' => (int) $evento['datos']['solicitud_id'],
            'estado_publicado' => 'aprobada',
        ];
    }
}
