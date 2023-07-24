<?php

namespace Stape\Gtm\Controller\Adminhtml\Webhook;

use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\ResultFactory;
use Stape\Gtm\Model\ConfigProvider;
use Stape\Gtm\Model\Webhook\Adapter;

class Test extends \Magento\Backend\App\Action
{
    /**
     * @var Adapter $adapter
     */
    private $adapter;

    /**
     * @var ConfigProvider $configProvider
     */
    private $configProvider;

    /**
     * Define class dependencies
     *
     * @param Context $context
     * @param Adapter $adapter
     * @param ConfigProvider $configProvider
     */
    public function __construct(
        Context $context,
        Adapter $adapter,
        ConfigProvider $configProvider
    ) {
        parent::__construct($context);
        $this->adapter = $adapter;
        $this->configProvider = $configProvider;
    }

    /**
     * Execute controller logic
     *
     * @return \Magento\Framework\App\ResponseInterface|\Magento\Framework\Controller\Result\Json|(\Magento\Framework\Controller\Result\Json&\Magento\Framework\Controller\ResultInterface)|\Magento\Framework\Controller\ResultInterface
     */
    public function execute()
    {
        $result = $this->resultFactory->create(ResultFactory::TYPE_JSON);
        if (!$this->configProvider->isActive() || !$this->configProvider->webhooksEnabled()) {
            return $result->setData([
                'status' => 'fail',
                'message' => __('Either module or webhooks is disabled')
            ]);
        }

        if (!$this->adapter->test()) {
            return $result->setData([
                'status' => 'fail',
                'message' => __('Request to GTM server container URL failed.')
            ]);
        }

        return $result->setData([
            'status' => 'ok',
            'message' => __('Success!')
        ]);
    }
}
