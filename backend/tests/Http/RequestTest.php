<?php

declare(strict_types=1);

namespace DoubtQueue\Tests\Http;

use DoubtQueue\Exception\ValidationException;
use DoubtQueue\Http\Request;
use PHPUnit\Framework\TestCase;

class RequestTest extends TestCase
{
    public function testEmptyBodyReturnsEmptyArray(): void
    {
        $request = new Request('POST', '/api/sessions');

        $this->assertSame([], $request->body());
    }

    public function testValidJsonObjectReturnsDecodedArray(): void
    {
        $request = new Request(
            'POST',
            '/api/sessions',
            [],
            '{"title":"PHP Basics","mentor_name":"Priya"}'
        );

        $this->assertSame([
            'title' => 'PHP Basics',
            'mentor_name' => 'Priya',
        ], $request->body());
    }

    public function testInvalidJsonThrowsValidationException(): void
    {
        $request = new Request('POST', '/api/sessions', [], '{bad');

        $this->expectException(ValidationException::class);

        $request->body();
    }

    public function testJsonNumberThrowsValidationException(): void
    {
        $request = new Request('POST', '/api/sessions', [], '123');

        $this->expectException(ValidationException::class);

        $request->body();
    }

    public function testJsonStringThrowsValidationException(): void
    {
        $request = new Request('POST', '/api/sessions', [], '"hello"');

        $this->expectException(ValidationException::class);

        $request->body();
    }
}
