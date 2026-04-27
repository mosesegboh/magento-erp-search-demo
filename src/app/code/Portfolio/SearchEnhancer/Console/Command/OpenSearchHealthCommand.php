<?php

declare(strict_types=1);

namespace Portfolio\SearchEnhancer\Console\Command;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\HTTP\Client\CurlFactory;
use Magento\Store\Model\ScopeInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class OpenSearchHealthCommand extends Command
{
    private const XML_PATH_ENGINE = 'catalog/search/engine';
    private const XML_PATH_HOST = 'catalog/search/opensearch_server_hostname';
    private const XML_PATH_PORT = 'catalog/search/opensearch_server_port';
    private const XML_PATH_TIMEOUT = 'catalog/search/opensearch_server_timeout';

    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly CurlFactory $curlFactory,
        ?string $name = null
    ) {
        parent::__construct($name);
    }

    protected function configure(): void
    {
        $this->setName('portfolio:search:opensearch-health');
        $this->setDescription('Check configured Magento OpenSearch cluster health.');

        parent::configure();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $engine = (string)$this->scopeConfig->getValue(self::XML_PATH_ENGINE, ScopeInterface::SCOPE_STORE);
        $host = (string)$this->scopeConfig->getValue(self::XML_PATH_HOST, ScopeInterface::SCOPE_STORE);
        $port = (string)$this->scopeConfig->getValue(self::XML_PATH_PORT, ScopeInterface::SCOPE_STORE);
        $timeout = max((int)$this->scopeConfig->getValue(self::XML_PATH_TIMEOUT, ScopeInterface::SCOPE_STORE), 1);
        $url = sprintf('http://%s:%s/_cluster/health', $host !== '' ? $host : 'opensearch', $port !== '' ? $port : '9200');

        $client = $this->curlFactory->create();
        $client->setTimeout($timeout);
        $client->get($url);

        $status = $client->getStatus();
        $body = (string)$client->getBody();

        if ($status < 200 || $status >= 300) {
            $output->writeln(sprintf('<error>OpenSearch health check failed. HTTP %d: %s</error>', $status, $body));
            return Command::FAILURE;
        }

        $data = json_decode($body, true);

        if (!is_array($data)) {
            $output->writeln('<error>OpenSearch returned invalid JSON.</error>');
            return Command::FAILURE;
        }

        $output->writeln(sprintf('<info>Search engine: %s</info>', $engine));
        $output->writeln(sprintf('<info>Cluster: %s</info>', $data['cluster_name'] ?? 'unknown'));
        $output->writeln(sprintf('<info>Status: %s</info>', $data['status'] ?? 'unknown'));
        $output->writeln(sprintf('<info>Nodes: %s</info>', $data['number_of_nodes'] ?? 'unknown'));

        return Command::SUCCESS;
    }
}
