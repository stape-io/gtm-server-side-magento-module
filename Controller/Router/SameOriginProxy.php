<?php

namespace Stape\Gtm\Controller\Router;

use Magento\Framework\App\ActionFactory;
use Magento\Framework\App\ActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\RouterInterface;
use Stape\Gtm\Model\ConfigProvider;

/**
 * Matches requests under the merchant-configured same-origin proxy path.
 *
 * The proxy path is arbitrary merchant input, so a fixed route name cannot
 * be used; this router is registered with high priority (low sort order)
 * so existing routes/rewrites cannot shadow the proxy path.
 */
class SameOriginProxy implements RouterInterface
{
    /*
     * Request parameter holding the sub-path captured after the proxy path
     */
    public const PARAM_SUB_PATH = 'stape_same_origin_sub_path';

    /**
     * @var ActionFactory $actionFactory
     */
    private $actionFactory;

    /**
     * @var ConfigProvider $configProvider
     */
    private $configProvider;

    /**
     * Define class dependencies
     *
     * @param ActionFactory $actionFactory
     * @param ConfigProvider $configProvider
     */
    public function __construct(
        ActionFactory $actionFactory,
        ConfigProvider $configProvider
    ) {
        $this->actionFactory = $actionFactory;
        $this->configProvider = $configProvider;
    }

    /**
     * Match requests whose path starts with the configured proxy path
     *
     * @param RequestInterface $request
     * @return ActionInterface|null
     */
    public function match(RequestInterface $request)
    {
        if (!$this->configProvider->isActive() || !$this->configProvider->isSameOriginActive()) {
            return null;
        }

        // normalize slashes for comparison; a bare "/" would shadow the whole site
        $path = '/' . trim((string) $this->configProvider->getSameOriginPath(), '/');
        if ($path === '/') {
            return null;
        }

        $pathInfo = '/' . ltrim((string) $request->getPathInfo(), '/');

        if (!preg_match('#^' . preg_quote($path, '#') . '(/.*)?$#', $pathInfo, $matches)) {
            return null;
        }

        $request->setParam(self::PARAM_SUB_PATH, $matches[1] ?? '');

        return $this->actionFactory->create(\Stape\Gtm\Controller\SameOrigin\Proxy::class);
    }
}
