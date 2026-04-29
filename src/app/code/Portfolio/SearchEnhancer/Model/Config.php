<?php

declare(strict_types=1);

namespace Portfolio\SearchEnhancer\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;

class Config
{
    private const XML_PATH_ENABLED = 'portfolio_search_enhancer/general/enabled';
    private const XML_PATH_MIN_QUERY_LENGTH = 'portfolio_search_enhancer/general/min_query_length';
    private const XML_PATH_MAX_QUERY_LENGTH = 'portfolio_search_enhancer/general/max_query_length';
    private const XML_PATH_SUGGESTION_LIMIT = 'portfolio_search_enhancer/general/suggestion_limit';
    private const XML_PATH_CACHE_TTL = 'portfolio_search_enhancer/general/cache_ttl';
    private const XML_PATH_LOG_ZERO_RESULTS = 'portfolio_search_enhancer/general/log_zero_results';

    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig
    ) {
    }

    public function isEnabled(): bool
    {
        return $this->scopeConfig->isSetFlag(self::XML_PATH_ENABLED, ScopeInterface::SCOPE_STORE);
    }

    public function getMinQueryLength(): int
    {
        $value = (int)$this->scopeConfig->getValue(self::XML_PATH_MIN_QUERY_LENGTH, ScopeInterface::SCOPE_STORE);
        return min(max($value, 1), 10);
    }

    public function getMaxQueryLength(): int
    {
        $value = (int)$this->scopeConfig->getValue(self::XML_PATH_MAX_QUERY_LENGTH, ScopeInterface::SCOPE_STORE);
        return min(max($value, 20), 255);
    }

    public function getSuggestionLimit(): int
    {
        $value = (int)$this->scopeConfig->getValue(self::XML_PATH_SUGGESTION_LIMIT, ScopeInterface::SCOPE_STORE);
        return min(max($value, 1), 12);
    }

    public function getCacheTtl(): int
    {
        $value = (int)$this->scopeConfig->getValue(self::XML_PATH_CACHE_TTL, ScopeInterface::SCOPE_STORE);
        return min(max($value, 0), 300);
    }

    public function shouldLogZeroResults(): bool
    {
        return $this->scopeConfig->isSetFlag(self::XML_PATH_LOG_ZERO_RESULTS, ScopeInterface::SCOPE_STORE);
    }
}
