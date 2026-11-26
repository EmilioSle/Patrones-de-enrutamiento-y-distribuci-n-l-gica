<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Tests\TestCase;

class ExportacionCarritoTest extends TestCase
{
    use RefreshDatabase;

    public function test_el_worker_procesa_el_mismo_reporte_que_la_ruta_sincrona(): void
    {
        $cliente = User::factory()->create();
        $this->withToken($cliente->createToken('prueba', ['carrito:leer'])->plainTextToken);
        Cache::put('carrito:'.$cliente->id, [
            ['producto_id' => 9, 'cantidad' => 2, 'precio_centavos' => 850],
        ]);
        $correlationId = (string) Str::uuid();

        $asincrona = $this->withHeader('X-Correlation-ID', $correlationId)
            ->postJson('/api/carrito/exportaciones/asincrona', ['formato' => 'csv'])
            ->assertAccepted()->assertHeader('X-Correlation-ID', $correlationId);

        $this->assertDatabaseCount('jobs', 1);
        $this->getJson($asincrona->json('consulta'))->assertOk()
            ->assertJsonPath('estado', 'pendiente')->assertJsonPath('resultado_csv', null);

        $sincrona = $this->postJson('/api/carrito/exportaciones/sincrona', ['formato' => 'csv'])
            ->assertCreated()->assertJsonPath('estado', 'completada');

        Cache::forget('carrito:'.$cliente->id);
        $this->artisan('queue:work', ['connection' => 'exportaciones', '--queue' => 'exportaciones-carrito', '--once' => true])
            ->assertSuccessful();

        $csv = "producto_id,cantidad,precio_centavos,subtotal_centavos\n9,2,850,1700\n";
        $this->getJson($asincrona->json('consulta'))->assertOk()
            ->assertJsonPath('estado', 'completada')->assertJsonPath('correlation_id', $correlationId)
            ->assertJsonPath('resultado_csv', $csv);
        $this->getJson($sincrona->json('consulta'))->assertOk()->assertJsonPath('resultado_csv', $csv);
        $this->assertDatabaseCount('jobs', 0);
        $this->assertDatabaseCount('failed_jobs', 0);
    }

    public function test_valida_el_formato_y_restringe_la_consulta_al_propietario(): void
    {
        $cliente = User::factory()->create();
        $this->withToken($cliente->createToken('prueba', ['carrito:leer'])->plainTextToken);
        $this->postJson('/api/carrito/exportaciones/asincrona', [])
            ->assertUnprocessable()->assertJsonValidationErrors('formato');
        $this->assertDatabaseCount('exportaciones_carrito', 0);
        $this->assertDatabaseCount('jobs', 0);

        $exportacion = $this->postJson('/api/carrito/exportaciones/asincrona', ['formato' => 'csv'])
            ->assertAccepted();
        $otro = User::factory()->create();
        app('auth')->forgetGuards();
        $this->withToken($otro->createToken('prueba', ['carrito:leer'])->plainTextToken);
        $this->getJson($exportacion->json('consulta'))->assertNotFound();
    }
}
