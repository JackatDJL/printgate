<?php

namespace App\Connect\SiteAgent;

use RuntimeException;

final class SitePairer
{
    public function __construct(private readonly HubClient $hub, private readonly CredentialStore $store) {}

    public function pair(string $baseUrl, string $siteName, string $invitation, string $comparisonCode): string
    {
        $request = $this->hub->request($baseUrl);
        $begin = $this->hub->response($request->post(rtrim($baseUrl, '/').'/api/connect/pairings', [
            'invitation' => $invitation,
            'site_name' => $siteName,
            'comparison_code' => $comparisonCode,
        ]));

        foreach (['claim_id', 'claim_token', 'expires_at'] as $field) {
            if (! is_string($begin[$field] ?? null)) {
                throw new RuntimeException('The Printgate Hub returned an invalid pairing claim.');
            }
        }

        $claimUrl = rtrim($baseUrl, '/').'/api/connect/pairings/'.rawurlencode($begin['claim_id']).'/credential';
        do {
            $delivery = $this->hub->response($this->hub->request($baseUrl)->post($claimUrl, ['claim_token' => $begin['claim_token']]));
            if (($delivery['status'] ?? null) === 'paired') {
                if (! is_string($delivery['credential'] ?? null) || ! is_string($delivery['site_id'] ?? null)) {
                    throw new RuntimeException('The Printgate Hub returned an invalid site credential.');
                }
                $this->store->save(rtrim($baseUrl, '/'), $delivery['credential'], $delivery['site_id']);

                return $delivery['site_id'];
            }
            if (($delivery['status'] ?? null) !== 'pending') {
                throw new RuntimeException('The Printgate Hub returned an unknown pairing status.');
            }
            if (strtotime($begin['expires_at']) <= time()) {
                throw new RuntimeException('The pairing claim expired before the Hub confirmed it.');
            }
            sleep(2);
        } while (true);
    }
}
