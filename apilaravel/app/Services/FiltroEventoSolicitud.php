<?php

namespace App\Services;

class FiltroEventoSolicitud
{
    public function motivo(array $evento): ?string
    {
        return $evento['datos']['entorno'] === 'prueba' ? 'evento_de_prueba' : null;
    }
}
