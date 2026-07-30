<?php
/**
 * Copyright © Klizer. All rights reserved.
 */
declare(strict_types=1);

namespace Klizer\AiCommerceAssistant\Controller\Context;

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
 * Magento proxy: browser → Magento → AI /api/context/search
 *
 * Returns a suggestion only. The shopper always decides whether to reuse it.
 */
class Search implements HttpPostActionInterface, CsrfAwareActionInterface
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
        $userId = $this->userIdentity->resolve(
            trim((string) $this->request->getParam('userId', ''))
        );

        // No identity or no query means there is nothing to match against.
        if ($query === '' || $userId === '') {
            return $result->setData([
                'success' => true,
                'data' => ['shouldReuse' => false],
            ]);
        }

        try {
            $data = $this->client->contextSearch($userId, $query);

            return $result->setData([
                'success' => true,
                'data' => $data,
            ]);
        } catch (\Throwable $e) {
            // Memory is an enhancement — never block the shopping flow.
            $this->logger->warning('AiCommerceAssistant context search failed', [
                'error' => $e->getMessage(),
            ]);

            return $result->setData([
                'success' => true,
                'data' => ['shouldReuse' => false],
            ]);
        }
    }
}
