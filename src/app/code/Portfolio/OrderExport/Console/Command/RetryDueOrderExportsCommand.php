<?php

declare(strict_types=1);

namespace Portfolio\OrderExport\Console\Command;

use Magento\Framework\App\Area;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\App\State;
use Magento\Framework\Exception\LocalizedException;
use Portfolio\OrderExport\Model\Queue\OrderExportRetryManager;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class RetryDueOrderExportsCommand extends Command
{
    private const OPTION_LIMIT = 'limit';

    public function __construct(
        private readonly OrderExportRetryManager $retryManager,
        ?string $name = null,
        private ?State $appState = null
    ) {
        $this->appState ??= ObjectManager::getInstance()->get(State::class);
        parent::__construct($name);
    }

    protected function configure(): void
    {
        $this->setName('portfolio:order-export:retry-due');
        $this->setDescription('Queue due order export retries that were scheduled after failed ERP attempts.');
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
            $output->writeln(sprintf('<error>Order export retry enqueue failed: %s</error>', $exception->getMessage()));
            return Command::FAILURE;
        }

        $output->writeln(sprintf('<info>Queued %d due order export retry message(s).</info>', $queued));

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
