<?php

declare(strict_types=1);

namespace Portfolio\OrderExport\Controller\Adminhtml\ExportLog;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\View\Result\Page;
use Magento\Framework\View\Result\PageFactory;

class Index extends Action
{
    public const ADMIN_RESOURCE = 'Portfolio_OrderExport::export_logs';

    public function __construct(
        Context $context,
        private readonly PageFactory $resultPageFactory
    ) {
        parent::__construct($context);
    }

    public function execute(): Page
    {
        $resultPage = $this->resultPageFactory->create();
        $resultPage->setActiveMenu('Portfolio_OrderExport::export_logs');
        $resultPage->getConfig()->getTitle()->prepend(__('Order Export Logs'));

        return $resultPage;
    }
}
