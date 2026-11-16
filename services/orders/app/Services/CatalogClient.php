<?php

namespace App\Services;

use Ecommerce\Shared\Http\Middleware\CorrelationId;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\Http;

class CatalogClient
{
    /**
     * Current data for the given products. Inactive and unknown products are absent.
     *
     * @param  list<string>  $ids
     * @return array<string, array<string, mixed>> keyed by product id
     *
     * @throws ConnectionException|RequestException when the catalog cannot answer
     */
    public function products(array $ids): array
    {
        $response = Http::baseUrl(config('orders.catalog_url'))
            ->acceptJson()
            ->connectTimeout(2)
            ->timeout(5)
            ->retry(2, 100, throw: false)
            ->withHeaders(array_filter([CorrelationId::HEADER => Context::get('correlation_id')]))
            ->get('/api/v1/products', ['ids' => $ids, 'per_page' => 100])
            ->throw();

        return collect($response->json('data'))->keyBy('id')->all();
    }
}
