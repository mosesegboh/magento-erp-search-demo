<?php

declare(strict_types=1);

namespace Portfolio\OrderExport\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Store\Model\ScopeInterface;

class Config
{
    private const XML_PATH_ENABLED = 'portfolio_order_export/general/enabled';
    private const XML_PATH_ASYNC_ENABLED = 'portfolio_order_export/general/async_enabled';
    private const XML_PATH_API_BASE_URL = 'portfolio_order_export/api/base_url';
    private const XML_PATH_API_TOKEN = 'portfolio_order_export/api/token';
    private const XML_PATH_API_TIMEOUT = 'portfolio_order_export/api/timeout';

    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly EncryptorInterface $encryptor
    ) {
    }

    public function isEnabled(): bool
    {
        return $this->scopeConfig->isSetFlag(self::XML_PATH_ENABLED, ScopeInterface::SCOPE_STORE);
    }

    public function isAsyncEnabled(): bool
    {
        return $this->scopeConfig->isSetFlag(self::XML_PATH_ASYNC_ENABLED, ScopeInterface::SCOPE_STORE);
    }

    public function getApiBaseUrl(): string
    {
        return rtrim((string)$this->scopeConfig->getValue(self::XML_PATH_API_BASE_URL, ScopeInterface::SCOPE_STORE), '/');
    }

    public function getApiToken(): string
    {
        $value = (string)$this->scopeConfig->getValue(self::XML_PATH_API_TOKEN, ScopeInterface::SCOPE_STORE);

        if ($value === '') {
            return '';
        }

        if (!preg_match('/^\d+:/', $value)) {
            return $value;
        }

        try {
            $decrypted = (string)$this->encryptor->decrypt($value);
            return $decrypted !== '' ? $decrypted : $value;
        } catch (\Throwable) {
            return $value;
        }
    }

    public function getApiTimeout(): int
    {
        $timeout = (int)$this->scopeConfig->getValue(self::XML_PATH_API_TIMEOUT, ScopeInterface::SCOPE_STORE);
        return max($timeout, 1);
    }
}
