<?php

declare(strict_types=1);

namespace Portfolio\OrderExport\Console\Command;

use Magento\Framework\App\Area;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\App\State;
use Magento\Framework\Exception\LocalizedException;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\ResourceModel\Order\CollectionFactory as OrderCollectionFactory;
use Portfolio\OrderExport\Model\OrderExportService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class ExportOrderCommand extends Command
{
    private const ARGUMENT_ORDER = 'order';
    private const OPTION_FORCE = 'force';

    public function __construct(
        private readonly OrderExportService $orderExportService,
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly OrderCollectionFactory $orderCollectionFactory,
        ?string $name = null,
        private ?State $appState = null
    ) {
        $this->appState ??= ObjectManager::getInstance()->get(State::class);
        parent::__construct($name);
    }

    protected function configure(): void
    {
        $this->setName('portfolio:order-export:export');
        $this->setDescription('Export a Magento order to the configured ERP API.');
        $this->addArgument(self::ARGUMENT_ORDER, InputArgument::REQUIRED, 'Order entity ID or increment ID.');
        $this->addOption(self::OPTION_FORCE, null, InputOption::VALUE_NONE, 'Export even if the order was already exported.');

        parent::configure();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->setAreaCode();

        try {
            $orderId = $this->resolveOrderId((string)$input->getArgument(self::ARGUMENT_ORDER));
            $log = $this->orderExportService->exportByOrderId($orderId, (bool)$input->getOption(self::OPTION_FORCE));
        } catch (\Throwable $exception) {
            $output->writeln(sprintf('<error>Order export failed: %s</error>', $exception->getMessage()));
            return Command::FAILURE;
        }

        $output->writeln(sprintf(
            '<info>Order export %s. Increment ID: %s. External ID: %s. Attempts: %d.</info>',
            $log->getData('status'),
            $log->getData('increment_id'),
            $log->getData('external_id') ?: 'n/a',
            (int)$log->getData('attempts')
        ));

        return Command::SUCCESS;
    }

    private function resolveOrderId(string $orderReference): int
    {
        if (ctype_digit($orderReference)) {
            try {
                $order = $this->orderRepository->get((int)$orderReference);
                return (int)$order->getEntityId();
            } catch (\Throwable) {
                // Fall back to increment ID lookup below.
            }
        }

        $collection = $this->orderCollectionFactory->create();
        $collection->addFieldToFilter('increment_id', $orderReference);
        $collection->setPageSize(1);
        $order = $collection->getFirstItem();

        if (!$order->getId()) {
            throw new LocalizedException(__('Order %1 was not found.', $orderReference));
        }

        return (int)$order->getId();
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
