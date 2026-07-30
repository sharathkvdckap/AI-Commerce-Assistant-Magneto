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
use Psr\Log\LoggerInterface;

/**
 * Magento proxy: browser → Magento → AI /api/assistant/start
 */
class Start implements HttpPostActionInterface, CsrfAwareActionInterface
{
    public function __construct(
        private readonly RequestInterface $request,
        private readonly JsonFactory $jsonFactory,
        private readonly Config $config,
        private readonly Client $client,
        private readonly FormKeyValidator $formKeyValidator,
        private readonly UserIdentity $userIdentity,
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

        $query = trim((string) $this->request->getParam('query', ''));
        if ($query === '') {
            $query = trim((string) $this->request->getParam('q', ''));
        }

        if ($query === '') {
            return $result->setHttpResponseCode(400)->setData([
                'success' => false,
                'message' => (string) __('Please enter a search query.'),
            ]);
        }

        $userId = $this->userIdentity->resolve(
            trim((string) $this->request->getParam('userId', ''))
        );
        $reuseHistoryId = trim((string) $this->request->getParam('reuseHistoryId', ''));

        try {
            $data = $this->client->start(
                $query,
                $userId,
                $this->readReuseFilters(),
                $reuseHistoryId
            );

            return $result->setData([
                'success' => true,
                'data' => $data,
            ]);
        } catch (\Throwable $e) {
            $this->logger->error('AiCommerceAssistant start failed', ['error' => $e->getMessage()]);

            return $result->setHttpResponseCode(500)->setData([
                'success' => false,
                'message' => (string) __('Unable to start AI assistant. Please try again.'),
            ]);
        }
    }

    /**
     * Previous-session filters the shopper explicitly chose to reuse.
     *
     * @return array<string, string|int|float|bool>
     */
    private function readReuseFilters(): array
    {
        $raw = $this->request->getParam('reuseFilters');
        if (!is_array($raw)) {
            return [];
        }

        $filters = [];
        foreach ($raw as $key => $value) {
            if (!is_string($key) || is_array($value) || $value === null || $value === '') {
                continue;
            }
            $filters[$key] = is_scalar($value) ? $value : (string) $value;
        }

        return $filters;
    }
}
