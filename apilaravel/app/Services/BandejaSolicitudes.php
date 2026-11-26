<?php

namespace App\Services;

class BandejaSolicitudes
{
    public function procesar(array $evento): array
    {
        return [
            'accion' => 'preparar_revision',
            'solicitud_id' => (int) $evento['datos']['solicitud_id'],
            'orden_atencion' => $evento['prioridad'] === 'alta' ? 'preferente' : 'normal',
        ];
    }
}
