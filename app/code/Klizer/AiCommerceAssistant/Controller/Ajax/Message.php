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
 * Magento proxy: browser → Magento → AI /api/assistant/message
 */
class Message implements HttpPostActionInterface, CsrfAwareActionInterface
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

        $sessionId = trim((string) $this->request->getParam('sessionId', ''));
        $answer = trim((string) $this->request->getParam('answer', ''));

        if ($sessionId === '' || $answer === '') {
            return $result->setHttpResponseCode(400)->setData([
                'success' => false,
                'message' => (string) __('sessionId and answer are required.'),
            ]);
        }

        $userId = $this->userIdentity->resolve(
            trim((string) $this->request->getParam('userId', ''))
        );
        $identity = $this->userIdentity->getIdentityProof();

        try {
            $data = $this->client->message($sessionId, $answer, $userId, $identity);

            return $result->setData([
                'success' => true,
                'userId' => $userId,
                'identityAttached' => $identity !== null,
                'data' => $data,
            ]);
        } catch (\Throwable $e) {
            $this->logger->error('AiCommerceAssistant message failed', ['error' => $e->getMessage()]);

            return $result->setHttpResponseCode(500)->setData([
                'success' => false,
                'message' => (string) __('Unable to continue AI assistant. Please try again.'),
            ]);
        }
    }
}
