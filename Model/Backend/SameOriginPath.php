<?php

namespace Stape\Gtm\Model\Backend;

use Magento\Framework\App\Route\ConfigInterface as RouteConfigInterface;
use Magento\Framework\Exception\ValidatorException;
use Magento\Framework\Message\ManagerInterface as MessageManagerInterface;
use Magento\UrlRewrite\Model\UrlFinderInterface;
use Magento\UrlRewrite\Service\V1\Data\UrlRewrite;

class SameOriginPath extends \Magento\Framework\App\Config\Value
{
    /**
     * @var MessageManagerInterface $messageManager
     */
    private $messageManager;

    /**
     * @var RouteConfigInterface $routeConfig
     */
    private $routeConfig;

    /**
     * @var UrlFinderInterface $urlFinder
     */
    private $urlFinder;

    /**
     * Define class dependencies
     *
     * @param \Magento\Framework\Model\Context $context
     * @param \Magento\Framework\Registry $registry
     * @param \Magento\Framework\App\Config\ScopeConfigInterface $config
     * @param \Magento\Framework\App\Cache\TypeListInterface $cacheTypeList
     * @param MessageManagerInterface $messageManager
     * @param RouteConfigInterface $routeConfig
     * @param UrlFinderInterface $urlFinder
     * @param \Magento\Framework\Model\ResourceModel\AbstractResource|null $resource
     * @param \Magento\Framework\Data\Collection\AbstractDb|null $resourceCollection
     * @param array $data
     */
    public function __construct(
        \Magento\Framework\Model\Context $context,
        \Magento\Framework\Registry $registry,
        \Magento\Framework\App\Config\ScopeConfigInterface $config,
        \Magento\Framework\App\Cache\TypeListInterface $cacheTypeList,
        MessageManagerInterface $messageManager,
        RouteConfigInterface $routeConfig,
        UrlFinderInterface $urlFinder,
        ?\Magento\Framework\Model\ResourceModel\AbstractResource $resource = null,
        ?\Magento\Framework\Data\Collection\AbstractDb $resourceCollection = null,
        array $data = []
    ) {
        parent::__construct($context, $registry, $config, $cacheTypeList, $resource, $resourceCollection, $data);
        $this->messageManager = $messageManager;
        $this->routeConfig = $routeConfig;
        $this->urlFinder = $urlFinder;
    }

    /**
     * Validate and normalize the proxy path
     *
     * @return SameOriginPath
     * @throws ValidatorException
     */
    public function beforeSave()
    {
        $value = trim((string) $this->getValue());

        if ($value === '') {
            $this->setValue('');
            return parent::beforeSave();
        }

        // strip any pasted query string / fragment
        $value = preg_split('/[?#]/', $value)[0];

        // collapse duplicate slashes and drop the trailing one, so the stored
        // path always matches the proxy URL shown in the admin
        $value = preg_replace('#/{2,}#', '/', $value);
        $value = rtrim($value, '/');

        if ($value === '') {
            throw new ValidatorException(
                __('Proxy path cannot be the store root. Use a sub-path, e.g. "/gtm".')
            );
        }

        if (strpos($value, '/') !== 0) {
            throw new ValidatorException(
                __('Proxy path must start with "/" (e.g. "/gtm").')
            );
        }

        $this->setValue($value);
        return parent::beforeSave();
    }

    /**
     * Warn about likely path intersections after saving
     *
     * @return SameOriginPath
     */
    public function afterSave()
    {
        $value = (string) $this->getValue();

        if ($value !== '' && $value !== '/') {
            try {
                $this->checkConflicts($value);
            } catch (\Throwable $e) {
                // conflict detection is informational only and must never block saving
                $this->_logger->debug(
                    sprintf('[STAPE] Same-origin path conflict check failed. Error: %s', $e->getMessage())
                );
            }
        }

        return parent::afterSave();
    }

    /**
     * Check the saved path against existing route front names and URL rewrites
     *
     * @param string $path
     * @return void
     */
    private function checkConflicts($path)
    {
        $relative = trim($path, '/');
        $frontName = explode('/', $relative)[0];

        if (!empty($this->routeConfig->getModulesByFrontName($frontName, 'frontend'))) {
            $this->messageManager->addWarningMessage(
                __(
                    'The proxy path "%1" collides with the existing route front name "%2". '
                    . 'Requests to that route will be shadowed by the same-origin proxy. '
                    . 'Consider choosing a different path.',
                    $path,
                    $frontName
                )
            );
        }

        $rewrite = $this->urlFinder->findOneByData([
            UrlRewrite::REQUEST_PATH => [$relative, $relative . '/'],
        ]);

        if ($rewrite !== null) {
            $this->messageManager->addWarningMessage(
                __(
                    'The proxy path "%1" collides with an existing URL rewrite ("%2"). '
                    . 'That page will be shadowed by the same-origin proxy. '
                    . 'Consider choosing a different path.',
                    $path,
                    $rewrite->getRequestPath()
                )
            );
        }
    }
}
