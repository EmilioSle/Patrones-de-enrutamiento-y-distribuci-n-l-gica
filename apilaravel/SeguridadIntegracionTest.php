<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SeguridadIntegracionTest extends TestCase
{
    use RefreshDatabase;

    public function test_p1_token_con_lectura_consulta_su_carrito_con_200(): void
    {
        $cliente = User::factory()->create();

        $this->withToken($this->token($cliente, ['carrito:leer']))
            ->getJson('/api/carrito')->assertOk()->assertJsonPath('data.cliente_id', $cliente->id);
    }

    public function test_p1_token_con_escritura_actualiza_su_articulo_con_200(): void
    {
        $cliente = User::factory()->create();
        $otro = User::factory()->create();
        $this->prepararCarrito($cliente);
        $this->prepararCarrito($otro);

        $this->withToken($this->token($cliente, ['carrito:escribir']))
            ->patchJson('/api/carrito/articulos/1', ['cantidad' => 4, 'cliente_id' => $otro->id])
            ->assertOk()->assertJsonPath('data.cantidad', 4);

        $this->assertSame(4, Cache::get('carrito:'.$cliente->id)[0]['cantidad']);
        $this->assertSame(2, Cache::get('carrito:'.$otro->id)[0]['cantidad']);
    }

    public function test_p2_peticion_sin_token_recibe_401(): void
    {
        $this->getJson('/api/carrito')->assertUnauthorized();
        $this->patchJson('/api/carrito/articulos/1', ['cantidad' => 3])->assertUnauthorized();
        $this->getJson('/api/resumen-solicitud/1')->assertUnauthorized();
    }

    public function test_p3_token_de_lectura_no_puede_escribir_y_recibe_403(): void
    {
        $cliente = User::factory()->create();
        $this->prepararCarrito($cliente);

        $this->withToken($this->token($cliente, ['carrito:leer']))
            ->patchJson('/api/carrito/articulos/1', ['cantidad' => 4])->assertForbidden();
        $this->assertSame(2, Cache::get('carrito:'.$cliente->id)[0]['cantidad']);
    }

    public function test_articulo_que_solo_existe_en_carrito_ajeno_devuelve_404(): void
    {
        $cliente = User::factory()->create();
        $otro = User::factory()->create();
        $this->prepararCarrito($otro);

        $this->withToken($this->token($cliente, ['carrito:escribir']))
            ->patchJson('/api/carrito/articulos/1', ['cantidad' => 4])->assertNotFound();
        $this->assertSame(2, Cache::get('carrito:'.$otro->id)[0]['cantidad']);
    }

    public function test_token_anterior_con_comodin_no_obtiene_los_permisos_nuevos(): void
    {
        $cliente = User::factory()->create();
        $this->prepararCarrito($cliente);

        $this->withToken($this->token($cliente, ['*']))
            ->patchJson('/api/carrito/articulos/1', ['cantidad' => 4])->assertForbidden();
        $this->getJson('/api/resumen-solicitud/1')->assertForbidden();
    }

    public function test_cantidad_fuera_de_rango_devuelve_422(): void
    {
        $cliente = User::factory()->create();
        $this->prepararCarrito($cliente);

        $this->withToken($this->token($cliente, ['carrito:escribir']))
            ->patchJson('/api/carrito/articulos/1', ['cantidad' => 0])
            ->assertUnprocessable()->assertJsonValidationErrors('cantidad');
        $this->assertSame(2, Cache::get('carrito:'.$cliente->id)[0]['cantidad']);
    }

    public function test_orquestacion_denegada_no_contacta_a_servicios(): void
    {
        Http::preventStrayRequests();
        Http::fake([]);

        $this->withToken($this->token(User::factory()->create(), ['carrito:leer']))
            ->getJson('/api/resumen-solicitud/1')->assertForbidden();

        Http::assertNothingSent();
    }

    public function test_orquestador_autorizado_recibe_200_sin_reenviar_el_token(): void
    {
        config([
            'services.orquestacion.users_url' => 'https://usuarios.example.test',
            'services.orquestacion.status_url' => 'https://estados.example.test',
        ]);
        Http::preventStrayRequests();
        Http::fake([
            'https://usuarios.example.test/usuarios/7' => Http::response(['id' => 7]),
            'https://estados.example.test/estados/1' => Http::response(['estado' => 'pendiente']),
        ]);

        $this->withToken($this->token(User::factory()->create(), ['solicitudes:leer']))
            ->getJson('/api/resumen-solicitud/1')->assertOk()->assertJsonPath('estado.estado', 'pendiente');

        Http::assertSentCount(2);
        Http::assertNotSent(fn ($request) => $request->hasHeader('Authorization'));
    }

    private function token(User $cliente, array $permisos): string
    {
        return $cliente->createToken('prueba', $permisos, now()->addHour())->plainTextToken;
    }

    private function prepararCarrito(User $cliente): void
    {
        Cache::forever('carrito:'.$cliente->id, [
            ['producto_id' => 1, 'nombre' => 'Taza', 'cantidad' => 2, 'precio_centavos' => 850],
        ]);
    }
}
