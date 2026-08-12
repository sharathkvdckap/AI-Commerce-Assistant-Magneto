<?php
/**
 * Copyright © Klizer. All rights reserved.
 */
declare(strict_types=1);

namespace Klizer\AiCommerceAssistant\Block\Adminhtml;

use Klizer\AiCommerceAssistant\Helper\Data as Config;
use Magento\Backend\Block\Template;
use Magento\Framework\HTTP\Client\Curl;

class Dashboard extends Template
{
    public function __construct(
        Template\Context $context,
        private readonly Config $config,
        private readonly Curl $curl,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    public function getApiBaseUrl(): string
    {
        return $this->config->getApiBaseUrl();
    }

    public function getDashboardUrl(): string
    {
        return $this->config->getAnalyticsDashboardUrl();
    }

    public function getWindowDays(): int
    {
        return $this->config->getAnalyticsWindowDays();
    }

    /**
     * @return array{
     *   ok: bool,
     *   error?: string,
     *   summary?: array<string, mixed>
     * }
     */
    public function getSummaryPayload(): array
    {
        if (!$this->config->isEnabled()) {
            return [
                'ok' => false,
                'error' => (string) __(
                    'Enable AI Commerce Assistant under Stores → Configuration → Klizer → AI Commerce Assistant. Analytics only works when the assistant is enabled.'
                ),
            ];
        }

        if (!$this->config->isAnalyticsEnabled()) {
            return [
                'ok' => false,
                'error' => (string) __(
                    'Enable Analytics Menu under Stores → Configuration → Klizer → AI Commerce Assistant → Search Analytics (Admin ROI).'
                ),
            ];
        }

        $apiBase = $this->getApiBaseUrl();
        if ($apiBase === '') {
            return [
                'ok' => false,
                'error' => (string) __(
                    'Configure AI API Base URL under Stores → Configuration → Klizer → AI Commerce Assistant → General.'
                ),
            ];
        }

        $url = $apiBase . '/api/analytics/summary?days=' . $this->getWindowDays();

        try {
            $this->curl->setTimeout(8);
            $this->curl->get($url);
            $status = $this->curl->getStatus();
            $body = $this->curl->getBody();

            if ($status < 200 || $status >= 300) {
                return [
                    'ok' => false,
                    'error' => (string) __('API HTTP %1 from %2', $status, $url),
                ];
            }

            $json = json_decode($body, true);
            $summary = is_array($json) ? ($json['summary'] ?? null) : null;
            if (!is_array($summary)) {
                return [
                    'ok' => false,
                    'error' => (string) __(
                        'No summary payload. Is DATABASE_URL set and analytics migration applied?'
                    ),
                ];
            }

            return [
                'ok' => true,
                'summary' => $summary,
            ];
        } catch (\Throwable $e) {
            return [
                'ok' => false,
                'error' => (string) __(
                    'Could not reach AI API: %1 — ensure Node is running and Magento can reach %2',
                    $e->getMessage(),
                    $apiBase
                ),
            ];
        }
    }

    public function formatPercent(mixed $rate): string
    {
        return number_format(((float) $rate) * 100, 1) . '%';
    }
}
