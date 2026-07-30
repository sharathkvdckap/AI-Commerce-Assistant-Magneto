<?php
/**
 * Copyright © Klizer. All rights reserved.
 */
declare(strict_types=1);

namespace Klizer\AiCommerceAssistant\Model;

use Magento\Customer\Model\Session as CustomerSession;

/**
 * Resolves the identity used to key AI context memory.
 *
 * Logged-in customers are resolved server-side so the value cannot be spoofed.
 * Guests fall back to a sanitized browser-generated id.
 */
class UserIdentity
{
    private const MAX_GUEST_ID_LENGTH = 100;

    public function __construct(
        private readonly CustomerSession $customerSession
    ) {
    }

    /**
     * @return string Empty string when no identity can be established.
     */
    public function resolve(string $guestId = ''): string
    {
        if ($this->customerSession->isLoggedIn()) {
            $customerId = (int) $this->customerSession->getCustomerId();
            if ($customerId > 0) {
                return 'customer_' . $customerId;
            }
        }

        $clean = (string) preg_replace('/[^A-Za-z0-9_\-]/', '', $guestId);
        if ($clean === '') {
            return '';
        }

        return 'guest_' . substr($clean, 0, self::MAX_GUEST_ID_LENGTH);
    }
}
