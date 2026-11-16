<?php

namespace Tests\Feature;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GatewayTest extends TestCase
{
    public function test_health_endpoint_reports_ok_with_a_correlation_id(): void
    {
        $this->getJson('/api/v1/health')
            ->assertOk()
            ->assertJson(['service' => 'gateway', 'status' => 'ok'])
            ->assertHeader('X-Correlation-ID');
    }

    public function test_request_is_forwarded_to_the_mapped_service(): void
    {
        Http::fake(['catalog:8080/*' => Http::response(['data' => [['id' => 1]]], 200, ['X-Upstream' => 'catalog'])]);

        $this->withHeaders(['X-Correlation-ID' => 'test-correlation-1', 'Authorization' => 'Bearer abc'])
            ->getJson('/api/v1/products?page=2&filter[color]=red')
            ->assertOk()
            ->assertJson(['data' => [['id' => 1]]])
            ->assertHeader('X-Upstream', 'catalog')
            ->assertHeader('X-Correlation-ID', 'test-correlation-1');

        Http::assertSent(fn (Request $request) => str_starts_with($request->url(), 'http://catalog:8080/api/v1/products?page=2&filter')
            && $request->hasHeader('X-Correlation-ID', 'test-correlation-1')
            && $request->hasHeader('Authorization', 'Bearer abc'));
    }

    public function test_body_and_upstream_status_are_passed_through(): void
    {
        Http::fake(['auth:8080/*' => Http::response(['error' => ['code' => 'validation_failed']], 422)]);

        $this->postJson('/api/v1/auth/register', ['email' => 'nope'])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'validation_failed');

        Http::assertSent(fn (Request $request) => $request->method() === 'POST'
            && $request->url() === 'http://auth:8080/api/v1/auth/register'
            && $request->data() === ['email' => 'nope']);
    }

    public function test_unknown_resource_returns_not_found_in_the_error_format(): void
    {
        Http::fake();

        $this->getJson('/api/v1/unicorns/1')
            ->assertNotFound()
            ->assertJsonPath('error.code', 'not_found');

        Http::assertNothingSent();
    }

    public function test_unreachable_upstream_returns_service_unavailable(): void
    {
        Http::fake(fn () => throw new ConnectionException('Connection refused'));

        $this->getJson('/api/v1/orders/42')
            ->assertStatus(503)
            ->assertJsonPath('error.code', 'service_unavailable');
    }

    public function test_unmatched_route_uses_the_error_format(): void
    {
        $this->getJson('/nothing-here')
            ->assertNotFound()
            ->assertJsonPath('error.code', 'not_found');
    }
}
