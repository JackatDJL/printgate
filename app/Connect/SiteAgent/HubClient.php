<?php

namespace App\Connect\SiteAgent;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;

final class HubClient
{
    public function request(string $baseUrl, ?string $credential = null): PendingRequest
    {
        $parts = parse_url($baseUrl);
        if (! is_array($parts) || ($parts['scheme'] ?? null) !== 'https' || empty($parts['host']) || isset($parts['user']) || isset($parts['pass']) || isset($parts['query']) || isset($parts['fragment'])) {
            throw new RuntimeException('The Printgate Hub URL must be an HTTPS URL without credentials or query parameters.');
        }

        $request = Http::acceptJson()
            ->connectTimeout(5)
            ->timeout(20)
            ->withOptions(['allow_redirects' => false]);

        if ($credential !== null) {
            $request = $request->withToken($credential);
        }

        return $request;
    }

    public function response(Response $response): array
    {
        if ($response->status() >= 300 && $response->status() < 400) {
            throw new RuntimeException('The Printgate Hub redirected the request; redirects are not allowed.');
        }

        $response->throw();
        if ($response->body() === '') {
            return [];
        }
        $data = $response->json();
        if (! is_array($data)) {
            throw new RuntimeException('The Printgate Hub returned an invalid response.');
        }

        return $data;
    }
}
