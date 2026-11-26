<?php

namespace App\Services;

class ProcesadorRechazos
{
    public function procesar(array $evento): array
    {
        return [
            'accion' => 'preparar_revision_rechazo',
            'solicitud_id' => (int) $evento['datos']['solicitud_id'],
            'estado_publicado' => 'rechazada',
            'requiere_revision_prioritaria' => $evento['prioridad'] === 'alta',
        ];
    }
}
