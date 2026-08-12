<?php
/**
 * Copyright © Klizer. All rights reserved.
 */
declare(strict_types=1);

namespace Klizer\AiCommerceAssistant\Model;

use Klizer\AiCommerceAssistant\Helper\Data as Config;
use Magento\Customer\Model\Session as CustomerSession;

/**
 * Resolves the identity used to key AI context memory.
 *
 * Logged-in customers are resolved server-side from Magento session (not browser spoof).
 * When CUSTOMER_ID_HMAC_SECRET is configured (same value as Node), Magento attaches an
 * HMAC proof so Node can trust customer_* without accepting bare client claims.
 */
class UserIdentity
{
    private const MAX_GUEST_ID_LENGTH = 100;

    public function __construct(
        private readonly CustomerSession $customerSession,
        private readonly Config $config
    ) {
    }

    /**
     * @return string Empty string when no identity can be established.
     */
    public function resolve(string $guestId = ''): string
    {
        $customerId = $this->getLoggedInCustomerId();
        if ($customerId !== null) {
            return 'customer_' . $customerId;
        }

        $clean = (string) preg_replace('/[^A-Za-z0-9_\-]/', '', $guestId);
        if ($clean === '') {
            return '';
        }

        return 'guest_' . substr($clean, 0, self::MAX_GUEST_ID_LENGTH);
    }

    /**
     * HMAC proof for Node: { customerId, exp, sig }.
     * Null when guest, or when Magento HMAC secret is empty (dev unsigned mode).
     *
     * @return array{customerId: string, exp: int, sig: string}|null
     */
    public function getIdentityProof(): ?array
    {
        $customerId = $this->getLoggedInCustomerId();
        if ($customerId === null) {
            return null;
        }

        $secret = $this->config->getCustomerIdHmacSecret();
        if ($secret === '') {
            return null;
        }

        $exp = time() + $this->config->getCustomerIdTokenTtlSec();
        $payload = $this->buildPayload($customerId, $exp);
        $sig = hash_hmac('sha256', $payload, $secret);

        return [
            'customerId' => $customerId,
            'exp' => $exp,
            'sig' => $sig,
        ];
    }

    /**
     * Query params for external React redirect (?customer_id=&cid_exp=&cid_sig=).
     *
     * @return array<string, string>
     */
    public function getRedirectQueryParams(): array
    {
        $customerId = $this->getLoggedInCustomerId();
        if ($customerId === null) {
            return [];
        }

        $proof = $this->getIdentityProof();
        if ($proof !== null) {
            return [
                'customer_id' => $proof['customerId'],
                'cid_exp' => (string) $proof['exp'],
                'cid_sig' => $proof['sig'],
            ];
        }

        // Dev: Magento secret empty — bare id (Node accepts only if its secret is also empty).
        return ['customer_id' => $customerId];
    }

    private function getLoggedInCustomerId(): ?string
    {
        if (!$this->customerSession->isLoggedIn()) {
            return null;
        }

        $customerId = (int) $this->customerSession->getCustomerId();
        if ($customerId <= 0) {
            return null;
        }

        return (string) $customerId;
    }

    private function buildPayload(string $customerId, int $expUnix): string
    {
        return 'v1|' . $customerId . '|' . $expUnix;
    }
}
