<?php

declare(strict_types=1);

namespace Portfolio\SearchEnhancer\Controller\Adminhtml\SearchLog;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\View\Result\Page;
use Magento\Framework\View\Result\PageFactory;

class Index extends Action
{
    public const ADMIN_RESOURCE = 'Portfolio_SearchEnhancer::search_logs';

    public function __construct(
        Context $context,
        private readonly PageFactory $resultPageFactory
    ) {
        parent::__construct($context);
    }

    public function execute(): Page
    {
        $resultPage = $this->resultPageFactory->create();
        $resultPage->setActiveMenu('Portfolio_SearchEnhancer::search_logs');
        $resultPage->getConfig()->getTitle()->prepend(__('Search Query Logs'));

        return $resultPage;
    }
}
