<?php

declare(strict_types=1);

namespace Portfolio\ErpSync\Console\Command;

use Magento\Framework\App\Area;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\App\State;
use Magento\Framework\Exception\LocalizedException;
use Portfolio\ErpSync\Model\Queue\ProductSyncRetryManager;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class RetryDueSyncProductsCommand extends Command
{
    private const OPTION_LIMIT = 'limit';

    public function __construct(
        private readonly ProductSyncRetryManager $retryManager,
        ?string $name = null,
        private ?State $appState = null
    ) {
        $this->appState ??= ObjectManager::getInstance()->get(State::class);
        parent::__construct($name);
    }

    protected function configure(): void
    {
        $this->setName('portfolio:erp:sync-products:retry-due');
        $this->setDescription('Queue due ERP/PIM product sync retries that were scheduled after failed attempts.');
        $this->addOption(
            self::OPTION_LIMIT,
            null,
            InputOption::VALUE_OPTIONAL,
            'Maximum number of due retries to queue.',
            50
        );

        parent::configure();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->setAreaCode();
        $limit = max((int)$input->getOption(self::OPTION_LIMIT), 1);

        try {
            $queued = $this->retryManager->retryDue($limit);
        } catch (\Throwable $exception) {
            $output->writeln(sprintf('<error>ERP product sync retry enqueue failed: %s</error>', $exception->getMessage()));
            return Command::FAILURE;
        }

        $output->writeln(sprintf('<info>Queued %d due ERP product sync retry message(s).</info>', $queued));

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
