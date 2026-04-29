<?php

declare(strict_types=1);

namespace Portfolio\ErpSync\Controller\Adminhtml\SyncLog;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\View\Result\Page;
use Magento\Framework\View\Result\PageFactory;

class Index extends Action
{
    public const ADMIN_RESOURCE = 'Portfolio_ErpSync::sync_logs';

    public function __construct(
        Context $context,
        private readonly PageFactory $resultPageFactory
    ) {
        parent::__construct($context);
    }

    public function execute(): Page
    {
        $resultPage = $this->resultPageFactory->create();
        $resultPage->setActiveMenu('Portfolio_ErpSync::sync_logs');
        $resultPage->getConfig()->getTitle()->prepend(__('ERP Sync Logs'));

        return $resultPage;
    }
}
