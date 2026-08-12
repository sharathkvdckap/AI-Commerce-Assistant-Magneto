<?php
/**
 * Copyright © Klizer. All rights reserved.
 */
declare(strict_types=1);

namespace Klizer\AiCommerceAssistant\Controller\Ajax;

use Klizer\AiCommerceAssistant\Helper\Data as Config;
use Klizer\AiCommerceAssistant\Model\Api\Client;
use Klizer\AiCommerceAssistant\Model\UserIdentity;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Data\Form\FormKey\Validator as FormKeyValidator;
use Magento\Framework\Serialize\Serializer\Json;
use Psr\Log\LoggerInterface;

/**
 * Magento proxy: browser → Magento → AI /api/analytics/track (CTR impressions/clicks).
 */
class Track implements HttpPostActionInterface, CsrfAwareActionInterface
{
    public function __construct(
        private readonly RequestInterface $request,
        private readonly JsonFactory $jsonFactory,
        private readonly Config $config,
        private readonly Client $client,
        private readonly FormKeyValidator $formKeyValidator,
        private readonly UserIdentity $userIdentity,
        private readonly Json $json,
        private readonly LoggerInterface $logger
    ) {
    }

    public function createCsrfValidationException(RequestInterface $request): ?InvalidRequestException
    {
        $result = $this->jsonFactory->create();
        $result->setHttpResponseCode(403);
        $result->setData([
            'success' => false,
            'message' => (string) __('Invalid form key. Please refresh the page.'),
        ]);

        return new InvalidRequestException($result);
    }

    public function validateForCsrf(RequestInterface $request): ?bool
    {
        return $this->formKeyValidator->validate($request);
    }

    public function execute()
    {
        $result = $this->jsonFactory->create();

        if (!$this->config->shouldEmbedOnSearchResults()) {
            return $result->setHttpResponseCode(403)->setData([
                'success' => false,
                'message' => (string) __('AI Commerce Assistant is disabled.'),
            ]);
        }

        $events = $this->readEvents();
        if ($events === []) {
            return $result->setHttpResponseCode(400)->setData([
                'success' => false,
                'message' => (string) __('No tracking events.'),
            ]);
        }

        $userId = $this->userIdentity->resolve(
            trim((string) $this->request->getParam('userId', ''))
        );
        if ($userId === '') {
            return $result->setHttpResponseCode(400)->setData([
                'success' => false,
                'message' => (string) __('Missing user id.'),
            ]);
        }

        $identity = $this->userIdentity->getIdentityProof();
        $sessionId = trim((string) $this->request->getParam('sessionId', ''));
        $searchId = trim((string) $this->request->getParam('searchId', ''));
        $source = trim((string) $this->request->getParam('source', ''));

        try {
            $data = $this->client->trackAnalytics(
                $userId,
                $events,
                $sessionId !== '' ? $sessionId : null,
                $searchId !== '' ? $searchId : null,
                $source !== '' ? $source : null,
                $identity
            );

            return $result->setData([
                'success' => true,
                'data' => $data,
            ]);
        } catch (\Throwable $e) {
            // Tracking must not break shopping — soft-fail.
            $this->logger->warning('AiCommerceAssistant track failed', [
                'error' => $e->getMessage(),
            ]);

            return $result->setData([
                'success' => true,
                'skipped' => true,
            ]);
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function readEvents(): array
    {
        $raw = $this->request->getParam('events');
        if (is_string($raw) && $raw !== '') {
            try {
                $decoded = $this->json->unserialize($raw);
            } catch (\InvalidArgumentException $e) {
                return [];
            }
            $raw = $decoded;
        }

        if (!is_array($raw)) {
            return [];
        }

        $events = [];
        foreach ($raw as $row) {
            if (!is_array($row)) {
                continue;
            }
            $type = (string) ($row['eventType'] ?? '');
            $sku = trim((string) ($row['sku'] ?? ''));
            if (($type !== 'impression' && $type !== 'click') || $sku === '') {
                continue;
            }

            $event = [
                'eventType' => $type,
                'sku' => $sku,
            ];
            foreach (['productId', 'productName', 'productUrl', 'listType', 'position'] as $key) {
                if (!array_key_exists($key, $row) || $row[$key] === null || $row[$key] === '') {
                    continue;
                }
                $event[$key] = $row[$key];
            }
            if (isset($event['position'])) {
                $event['position'] = (int) $event['position'];
            }
            if (isset($event['listType'])
                && $event['listType'] !== 'primary'
                && $event['listType'] !== 'alternative'
            ) {
                unset($event['listType']);
            }

            $events[] = $event;
            if (count($events) >= 100) {
                break;
            }
        }

        return $events;
    }
}
