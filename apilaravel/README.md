<p align="center"><a href="https://laravel.com" target="_blank"><img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="400" alt="Laravel Logo"></a></p>

<p align="center">
<a href="https://github.com/laravel/framework/actions"><img src="https://github.com/laravel/framework/workflows/tests/badge.svg" alt="Build Status"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/dt/laravel/framework" alt="Total Downloads"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/v/laravel/framework" alt="Latest Stable Version"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/l/laravel/framework" alt="License"></a>
</p>

## About Laravel

Laravel is a web application framework with expressive, elegant syntax. We believe development must be an enjoyable and creative experience to be truly fulfilling. Laravel takes the pain out of development by easing common tasks used in many web projects, such as:

- [Simple, fast routing engine](https://laravel.com/docs/routing).
- [Powerful dependency injection container](https://laravel.com/docs/container).
- Multiple back-ends for [session](https://laravel.com/docs/session) and [cache](https://laravel.com/docs/cache) storage.
- Expressive, intuitive [database ORM](https://laravel.com/docs/eloquent).
- Database agnostic [schema migrations](https://laravel.com/docs/migrations).
- [Robust background job processing](https://laravel.com/docs/queues).
- [Real-time event broadcasting](https://laravel.com/docs/broadcasting).

Laravel is accessible, powerful, and provides tools required for large, robust applications.

## Learning Laravel

Laravel has the most extensive and thorough [documentation](https://laravel.com/docs) and video tutorial library of all modern web application frameworks, making it a breeze to get started with the framework.

In addition, [Laracasts](https://laracasts.com) contains thousands of video tutorials on a range of topics including Laravel, modern PHP, unit testing, and JavaScript. Boost your skills by digging into our comprehensive video library.

You can also watch bite-sized lessons with real-world projects on [Laravel Learn](https://laravel.com/learn), where you will be guided through building a Laravel application from scratch while learning PHP fundamentals.

## Agentic Development

Laravel's predictable structure and conventions make it ideal for AI coding agents like Claude Code, Cursor, and GitHub Copilot. Install [Laravel Boost](https://laravel.com/docs/ai) to supercharge your AI workflow:

```bash
composer require laravel/boost --dev

php artisan boost:install
```

Boost provides your agent 15+ tools and skills that help agents build Laravel applications while following best practices.

## Contributing

Thank you for considering contributing to the Laravel framework! The contribution guide can be found in the [Laravel documentation](https://laravel.com/docs/contributions).

## Code of Conduct

In order to ensure that the Laravel community is welcoming to all, please review and abide by the [Code of Conduct](https://laravel.com/docs/contributions#code-of-conduct).

## Security Vulnerabilities

If you discover a security vulnerability within Laravel, please send an e-mail to Taylor Otwell via [taylor@laravel.com](mailto:taylor@laravel.com). All security vulnerabilities will be promptly addressed.

## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).

## Práctica 2.4 Seguridad de la información en tránsito

Implementación individual de IS-803, 8.º A. Las publicaciones son de acceso público. El carrito requiere un token de Sanctum y se resuelve a partir del titular del token. El resumen de solicitudes queda reservado a la cuenta de integración porque combina datos de los servicios internos de usuarios y estados.

### Matriz de acceso

| Método y endpoint | Público | Cliente de lectura | Cliente con lectura y escritura | Orquestador |
| --- | --- | --- | --- | --- |
| GET `/api/publicaciones` y `/api/publicaciones/{id}` | Sí | Sí | Sí | Sí |
| GET `/api/carrito` y `/api/carrito/resumen` | 401 | Sí, propio | Sí, propio | 403 |
| PATCH `/api/carrito/articulos/{producto}` | 401 | 403 | Sí, artículo propio existente | 403 |
| GET `/api/resumen-solicitud/{id}` | 401 | 403 | 403 | Sí, con permiso de integración |

Los perfiles corresponden a permisos del token. La consulta exige `carrito:leer`, la actualización exige `carrito:escribir` y la integración exige `solicitudes:leer`. La escritura y la integración requieren el permiso explícito. Un token anterior con comodín puede conservar la lectura del carrito mientras siga vigente.

El PATCH acepta un objeto JSON como `{"cantidad":4}`, con un entero de 1 a 10000. El servidor deriva la clave del carrito del titular autenticado, incluso si el cuerpo o la URL de consulta incluyen otro identificador de cliente. Si el artículo no está en el carrito propio, devuelve 404. Una cantidad inválida devuelve 422. La actualización conserva el precio y los demás artículos, y usa el bloqueo de caché del carrito para serializar escrituras concurrentes.

### Configuración y credenciales

| Variables | Uso |
| --- | --- |
| `APP_ENV`, `APP_DEBUG`, `APP_URL`, `APP_KEY` | Entorno, depuración, URL base y clave de Laravel |
| `DB_CONNECTION`, `DB_DATABASE`, `DB_HOST`, `DB_PORT`, `DB_USERNAME`, `DB_PASSWORD` | Conexión y credenciales del almacén de usuarios y tokens según el motor |
| `CACHE_STORE` | Almacén y bloqueo de carritos |
| `SANCTUM_EXPIRATION` | Límite global de vigencia en minutos |
| `USERS_URL`, `STATUS_URL` | Destinos configurados para los servicios internos |

`.env.example` sirve de plantilla pública. `.gitignore` excluye `.env` y sus variantes, con excepción de la plantilla. En despliegue se inyectan la clave y las credenciales desde el gestor de secretos y se desactiva la depuración. Sanctum almacena el hash del token. Los clientes reciben sus tokens por un canal seguro, los envían en `Authorization` y los guardan en un almacén privado. Para revocar un token, el operador elimina su registro de Sanctum. La vigencia efectiva termina al alcanzar el límite global o la fecha individual de expiración, lo que ocurra primero.

La cuenta dedicada del orquestador debe recibir exclusivamente `solicitudes:leer` mediante el aprovisionamiento de Sanctum. En el laboratorio, la integración consulta un usuario ficticio fijo y el estado indicado en la URL. Su alcance es de servicio y no se asigna a clientes del carrito. La aplicación transmite un UUID de correlación a las dependencias y mantiene el token de entrada en Laravel. Los registros de esta orquestación incluyen el UUID y el ID de la solicitud.

### Transporte

Los servicios de laboratorio usan HTTP en localhost. Las pruebas funcionales se ejecutan en el kernel HTTP de Laravel y simulan las respuestas de las dependencias, por lo que no certifican cifrado de red. En despliegue se publicaría la API en HTTPS con un certificado válido en el proxy de entrada, configurando los clientes para usar la URL HTTPS desde la primera solicitud. Las dependencias quedarían en red privada con URLs HTTPS y verificación de certificados activa. Si el tramo entre el proxy y Laravel cruza una red, también usaría TLS en ese tramo. El proxy y el sistema de monitoreo deben excluir la cabecera `Authorization` de los registros.

### Pruebas

Ejecutar `php vendor/bin/phpunit --testdox --colors=never tests/Feature/SeguridadIntegracionTest.php`. La suite usa SQLite en memoria y genera los tokens de prueba durante la ejecución.

La [imagen de evidencia](Evidencia_Practica_2_4.png) y su [transcripción](Evidencia_Practica_2_4.txt) recogen el resultado de PHPUnit y la exclusión de archivos de entorno. Se usó una copia temporal para ejecutar PHP y un repositorio Git temporal para comprobar las reglas del proyecto, ya que la carpeta entregada carece de metadatos Git.

| Caso | Resultado comprobado |
| --- | --- |
| P1 | 200 para consulta y escritura autorizadas. La escritura actualiza el carrito propio y conserva intacto el de otro cliente. |
| P2 | 401 al enviar una consulta o escritura protegida sin token. |
| P3 | 403 si el token de lectura intenta escribir o acceder a la integración. La operación denegada no modifica el carrito ni invoca servicios internos. |
| P4 | La comprobación de Git aplica la exclusión de las configuraciones locales. Las evidencias registran resultados de prueba y reglas de exclusión sin valores de credenciales. |

También se comprueba el rechazo del comodín para permisos nuevos, el 404 para un artículo exclusivamente ajeno, el 422 para cantidades inválidas y la ausencia del token entrante en las peticiones simuladas del orquestador.

Para repetir las pruebas manualmente en un entorno local configurado, ejecutar las migraciones y usar `php artisan demo:preparar-api` en una consola privada. El comando prepara el carrito ficticio y emite un token de cliente con lectura y escritura. Usar `GET /api/carrito` y `PATCH /api/carrito/articulos/1` con `Accept: application/json` y la opción Bearer del cliente HTTP. Evitar capturar la consola del comando, las cabeceras de la solicitud o las variables privadas del cliente.

### Reflexión individual

La práctica resolvió el acceso sin permisos a datos del carrito y al resumen compuesto por dos servicios. Usé Sanctum para identificar al titular y permisos explícitos para las operaciones nuevas. Asocié el carrito a la identidad autenticada para que los parámetros del cliente no determinen a quién pertenecen los datos. Verifiqué la solución con peticiones que cubren el acceso autorizado, la ausencia de autenticación, la falta de permisos y el aislamiento entre carritos.

