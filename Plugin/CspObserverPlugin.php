<?php

namespace Stape\Gtm\Plugin;

use Magento\Framework\Event\ObserverInterface;

class CspObserverPlugin
{
    /**
     * @var \Stape\Gtm\Model\ConfigProvider $configProvider
     */
    private $configProvider;

    /**
     * @var \Magento\Csp\Model\Policy\FetchPolicyFactory $fetchPolicyFactory
     */
    private $fetchPolicyFactory;

    /**
     * @var \Magento\Csp\Model\Collector\DynamicCollector $dynamicCollector
     */
    private $dynamicCollector;

    /**
     * Define class dependencies
     *
     * @param \Stape\Gtm\Model\ConfigProvider $configProvider
     */
    public function __construct(
        \Stape\Gtm\Model\ConfigProvider $configProvider,
        \Magento\Csp\Model\Policy\FetchPolicyFactory $fetchPolicyFactory,
        \Magento\Csp\Model\Collector\DynamicCollector $dynamicCollector
    ) {
        $this->configProvider = $configProvider;
        $this->fetchPolicyFactory = $fetchPolicyFactory;
        $this->dynamicCollector = $dynamicCollector;
    }

    /**
     * Adding custom domain to CSP policy
     *
     * @param ObserverInterface $subject
     * @param $observer
     * @return array
     */
    public function beforeExecute(ObserverInterface $subject, $observer)
    {
        $customDomain = parse_url($this->configProvider->getCustomDomain() ?? '', PHP_URL_HOST);
        if (!$this->configProvider->isActive() || empty($customDomain)) {
            return [$observer];
        }

        $scriptPolicy = $this->fetchPolicyFactory->create([
            'id' => 'script-src',
            'hostSources' => [$customDomain],
            'schemeSources' => ['https'],
            'noneAllowed' => false,
            'selfAllowed' => true,
            'inlineAllowed' => true
        ]);

        $connectPolicy = $this->fetchPolicyFactory->create([
            'id' => 'connect-src',
            'hostSources' => [$customDomain],
            'schemeSources' => ['https'],
            'noneAllowed' => false,
            'selfAllowed' => true,
            'inlineAllowed' => true
        ]);

        $this->dynamicCollector->add($scriptPolicy);
        $this->dynamicCollector->add($connectPolicy);
        return [$observer];
    }
}
