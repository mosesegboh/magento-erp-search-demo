<?php

declare(strict_types=1);

namespace Portfolio\OrderExport\Console\Command;

use Magento\Framework\App\Area;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\App\State;
use Magento\Framework\Exception\LocalizedException;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\ResourceModel\Order\CollectionFactory as OrderCollectionFactory;
use Portfolio\OrderExport\Model\OrderReferenceResolver;
use Portfolio\OrderExport\Model\Queue\OrderExportPublisher;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class QueueExportOrderCommand extends Command
{
    private const ARGUMENT_ORDER = 'order';
    private const OPTION_FORCE = 'force';

    public function __construct(
        private readonly OrderExportPublisher $orderExportPublisher,
        OrderRepositoryInterface $orderRepository,
        OrderCollectionFactory $orderCollectionFactory,
        ?string $name = null,
        private ?State $appState = null,
        private ?OrderReferenceResolver $orderReferenceResolver = null
    ) {
        $this->appState ??= ObjectManager::getInstance()->get(State::class);
        $this->orderReferenceResolver ??= ObjectManager::getInstance()->get(OrderReferenceResolver::class);
        parent::__construct($name);
    }

    protected function configure(): void
    {
        $this->setName('portfolio:order-export:enqueue');
        $this->setDescription('Queue a Magento order export message for asynchronous ERP processing.');
        $this->addArgument(self::ARGUMENT_ORDER, InputArgument::REQUIRED, 'Order entity ID or increment ID.');
        $this->addOption(self::OPTION_FORCE, null, InputOption::VALUE_NONE, 'Queue export even if the order was already exported.');

        parent::configure();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->setAreaCode();

        try {
            $orderId = $this->orderReferenceResolver->resolve((string)$input->getArgument(self::ARGUMENT_ORDER));
            $logId = $this->orderExportPublisher->publish($orderId, (bool)$input->getOption(self::OPTION_FORCE));
        } catch (\Throwable $exception) {
            $output->writeln(sprintf('<error>Order export enqueue failed: %s</error>', $exception->getMessage()));
            return Command::FAILURE;
        }

        $output->writeln(sprintf('<info>Order export queued. Log ID: %d.</info>', $logId));

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
