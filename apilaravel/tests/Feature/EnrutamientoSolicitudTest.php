<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\BandejaSolicitudes;
use App\Services\ProcesadorAprobaciones;
use App\Services\ProcesadorRechazos;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class EnrutamientoSolicitudTest extends TestCase
{
    use RefreshDatabase;

    #[DataProvider('eventos')]
    public function test_decision_de_enrutamiento(
        ?string $tipo,
        string $entorno,
        int $http,
        string $estado,
        array $resultado,
        ?string $consumidor = null,
    ): void {
        $evento = [
            'id' => '10000000-0000-4000-8000-000000000032',
            'prioridad' => 'alta',
            'datos' => ['solicitud_id' => 42, 'entorno' => $entorno],
        ];

        if ($tipo !== null) {
            $evento['tipo'] = $tipo;
        }

        foreach ([BandejaSolicitudes::class, ProcesadorAprobaciones::class, ProcesadorRechazos::class] as $clase) {
            $expectativa = $this->partialMock($clase)->shouldReceive('procesar');
            if ($clase === $consumidor) {
                $expectativa->once()->passthru();
            } else {
                $expectativa->never();
            }
        }

        Log::spy();
        $cid = '20000000-0000-4000-8000-000000000032';
        $token = User::factory()->create()->createToken('prueba', ['solicitudes:eventos'])->plainTextToken;

        $this->withToken($token)->withHeader('X-Correlation-ID', $cid)
            ->postJson('/api/solicitudes/eventos', $evento)
            ->assertStatus($http)
            ->assertHeader('X-Correlation-ID', $cid)
            ->assertExactJson(['estado' => $estado, 'correlation_id' => $cid] + $resultado);

        $nivel = $http === 422 ? 'warning' : 'info';
        Log::shouldHaveReceived($nivel)->withArgs(
            fn (string $mensaje, array $contexto): bool => $mensaje === 'evento_solicitud.'.$estado
                && $contexto['cid'] === $cid,
        )->once();
    }

    public static function eventos(): array
    {
        return [
            'registro' => ['solicitud.registrada', 'operativo', 200, 'procesado', [
                'destino' => 'bandeja_solicitudes',
                'resultado' => ['accion' => 'preparar_revision', 'solicitud_id' => 42, 'orden_atencion' => 'preferente'],
            ], BandejaSolicitudes::class],
            'aprobacion' => ['solicitud.aprobada', 'operativo', 200, 'procesado', [
                'destino' => 'procesador_aprobaciones',
                'resultado' => ['accion' => 'preparar_aviso_aprobacion', 'solicitud_id' => 42, 'estado_publicado' => 'aprobada'],
            ], ProcesadorAprobaciones::class],
            'rechazo' => ['solicitud.rechazada', 'operativo', 200, 'procesado', [
                'destino' => 'procesador_rechazos',
                'resultado' => ['accion' => 'preparar_revision_rechazo', 'solicitud_id' => 42, 'estado_publicado' => 'rechazada', 'requiere_revision_prioritaria' => true],
            ], ProcesadorRechazos::class],
            'tipo desconocido' => ['solicitud.archivada', 'operativo', 422, 'no_enrutable', ['destino' => 'no_enrutables']],
            'evento filtrado' => ['solicitud.aprobada', 'prueba', 200, 'filtrado', ['motivo' => 'evento_de_prueba']],
            'campo obligatorio ausente' => [null, 'operativo', 422, 'invalido', ['campos' => ['tipo']]],
        ];
    }

    public function test_exige_un_token_con_permiso_explicito(): void
    {
        $this->postJson('/api/solicitudes/eventos')->assertUnauthorized();

        $token = User::factory()->create()->createToken('lectura', ['solicitudes:leer'])->plainTextToken;
        $this->withToken($token)->postJson('/api/solicitudes/eventos')->assertForbidden();
    }
}
