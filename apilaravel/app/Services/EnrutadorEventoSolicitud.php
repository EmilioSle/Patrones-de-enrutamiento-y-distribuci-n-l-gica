<?php

namespace App\Services;

class EnrutadorEventoSolicitud
{
    public function __construct(
        private BandejaSolicitudes $bandeja,
        private ProcesadorAprobaciones $aprobaciones,
        private ProcesadorRechazos $rechazos,
    ) {}

    public function enviar(array $evento): ?array
    {
        return match ($evento['tipo']) {
            'solicitud.registrada' => [
                'destino' => 'bandeja_solicitudes',
                'resultado' => $this->bandeja->procesar($evento),
            ],
            'solicitud.aprobada' => [
                'destino' => 'procesador_aprobaciones',
                'resultado' => $this->aprobaciones->procesar($evento),
            ],
            'solicitud.rechazada' => [
                'destino' => 'procesador_rechazos',
                'resultado' => $this->rechazos->procesar($evento),
            ],
            default => null,
        };
    }
}
