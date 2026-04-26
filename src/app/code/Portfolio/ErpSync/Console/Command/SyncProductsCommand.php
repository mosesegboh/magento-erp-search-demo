<?php

declare(strict_types=1);

namespace Portfolio\ErpSync\Console\Command;

use Magento\Framework\App\Area;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\App\State;
use Magento\Framework\Exception\LocalizedException;
use Portfolio\ErpSync\Model\ProductSyncService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class SyncProductsCommand extends Command
{
    private const OPTION_SINCE = 'since';
    private const OPTION_PAGE_SIZE = 'page-size';
    private const OPTION_DRY_RUN = 'dry-run';

    public function __construct(
        private readonly ProductSyncService $productSyncService,
        ?string $name = null,
        private ?State $appState = null
    ) {
        $this->appState ??= ObjectManager::getInstance()->get(State::class);
        parent::__construct($name);
    }

    protected function configure(): void
    {
        $this->setName('portfolio:erp:sync-products');
        $this->setDescription('Sync product updates from the configured ERP/PIM API.');
        $this->addOption(
            self::OPTION_SINCE,
            null,
            InputOption::VALUE_OPTIONAL,
            'Only sync ERP products updated after this ISO-8601 timestamp.'
        );
        $this->addOption(
            self::OPTION_PAGE_SIZE,
            null,
            InputOption::VALUE_OPTIONAL,
            'Override the configured ERP API page size.'
        );
        $this->addOption(
            self::OPTION_DRY_RUN,
            null,
            InputOption::VALUE_NONE,
            'Fetch ERP products and write a sync log without changing Magento products.'
        );

        parent::configure();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->setAreaCode();

        $since = $input->getOption(self::OPTION_SINCE);
        $pageSize = $input->getOption(self::OPTION_PAGE_SIZE);
        $dryRun = (bool)$input->getOption(self::OPTION_DRY_RUN);

        try {
            $result = $this->productSyncService->sync(
                is_string($since) && $since !== '' ? $since : null,
                $pageSize !== null ? (int)$pageSize : null,
                $dryRun
            );
        } catch (\Throwable $exception) {
            $output->writeln(sprintf('<error>ERP product sync failed: %s</error>', $exception->getMessage()));
            return Command::FAILURE;
        }

        $output->writeln(sprintf(
            '<info>ERP product sync completed. Items: %d. Pages: %d. Dry run: %s.</info>',
            $result['items_processed'],
            $result['pages_processed'],
            $result['dry_run'] ? 'yes' : 'no'
        ));

        return Command::SUCCESS;
    }

    private function setAreaCode(): void
    {
        try {
            $this->appState->setAreaCode(Area::AREA_ADMINHTML);
        } catch (LocalizedException) {
            // Area code may already be set by another command/bootstrap path.
        }
    }
}
