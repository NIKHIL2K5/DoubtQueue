<?php
declare(strict_types=1);

namespace DoubtQueue\Tests;

use DoubtQueue\Exception\NotFoundException;
use DoubtQueue\Http\Request;
use DoubtQueue\Http\Response;
use DoubtQueue\Router;
use PHPUnit\Framework\TestCase;

class RouterTest extends TestCase
{
    public function testIdParameterIsPassedAsInteger(): void
    {
        $router = new Router();

        $router->add('GET', '/api/sessions/{id}', function (Request $request, array $params): Response {
            $this->assertIsInt($params['id']);
            $this->assertSame(42, $params['id']);

            return new Response(200, ['id' => $params['id']]);
        });

        $response = $router->dispatch(new Request('GET', '/api/sessions/42'));

        $this->assertSame(200, $response->status);
        $this->assertSame(['id' => 42], $response->body);
    }

    public function testTwoParametersAreMatched(): void
    {
        $router = new Router();

        $router->add('GET', '/api/sessions/{sessionId}/doubts/{doubtId}', function (Request $request, array $params): Response {
            $this->assertSame(12, $params['sessionId']);
            $this->assertSame(34, $params['doubtId']);

            return new Response(200, $params);
        });

        $response = $router->dispatch(
            new Request('GET', '/api/sessions/12/doubts/34')
        );

        $this->assertSame(['sessionId' => 12, 'doubtId' => 34], $response->body);
    }

    public function testWrongMethodThrowsNotFoundException(): void
    {
        $router = new Router();

        $router->add('GET', '/api/sessions/{id}', function (Request $request, array $params): Response {
            return new Response(200, []);
        });

        $this->expectException(NotFoundException::class);

        $router->dispatch(new Request('POST', '/api/sessions/42'));
    }

    public function testUnknownPathThrowsNotFoundException(): void
    {
        $router = new Router();

        $router->add('GET', '/api/sessions/{id}', function (Request $request, array $params): Response {
            return new Response(200, []);
        });

        $this->expectException(NotFoundException::class);

        $router->dispatch(new Request('GET', '/api/unknown'));
    }

    public function testNonNumericIdDoesNotMatchRoute(): void
    {
        $router = new Router();

        $router->add('GET', '/api/sessions/{id}', function (Request $request, array $params): Response {
            return new Response(200, []);
        });

        $this->expectException(NotFoundException::class);

        $router->dispatch(new Request('GET', '/api/sessions/abc'));
    }
}
