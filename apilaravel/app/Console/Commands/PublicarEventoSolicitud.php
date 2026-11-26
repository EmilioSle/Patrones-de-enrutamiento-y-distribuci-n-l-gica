<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class PublicarEventoSolicitud extends Command
{
    protected $signature = 'solicitudes:publicar-evento
                            {solicitud_id}
                            {estado : pendiente, aprobada o rechazada}
                            {--prioridad=normal}
                            {--entorno=operativo}';

    protected $description = 'Publica un evento del flujo de estados de solicitudes en la API local';

    public function handle(): int
    {
        if (! app()->environment('local')) {
            $this->error('El productor de demostración solo se ejecuta en el entorno local.');

            return self::FAILURE;
        }

        $tipo = match ($this->argument('estado')) {
            'pendiente' => 'solicitud.registrada',
            'aprobada' => 'solicitud.aprobada',
            'rechazada' => 'solicitud.rechazada',
            default => null,
        };

        if ($tipo === null) {
            $this->error('Estado no reconocido.');

            return self::FAILURE;
        }

        $token = $this->secret('Token Sanctum con permiso solicitudes:eventos');
        if (! is_string($token) || trim($token) === '') {
            $this->error('Se requiere un token.');

            return self::FAILURE;
        }

        try {
            $respuesta = Http::acceptJson()->withToken(trim($token))
                ->withHeaders(['X-Correlation-ID' => (string) Str::uuid()])
                ->withoutRedirecting()->connectTimeout(3)->timeout(5)
                ->post(rtrim(config('app.url'), '/').'/api/solicitudes/eventos', [
                    'id' => (string) Str::uuid(),
                    'tipo' => $tipo,
                    'prioridad' => $this->option('prioridad'),
                    'datos' => [
                        'solicitud_id' => $this->argument('solicitud_id'),
                        'entorno' => $this->option('entorno'),
                    ],
                ]);
        } catch (ConnectionException) {
            $this->error('La API no está disponible.');

            return self::FAILURE;
        }

        $this->line('HTTP '.$respuesta->status());
        $this->line($respuesta->body());

        return $respuesta->successful() ? self::SUCCESS : self::FAILURE;
    }
}
