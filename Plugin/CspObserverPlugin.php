<?php

namespace Stape\Gtm\Plugin;

use Magento\Csp\Model\Collector\DynamicCollector;
use Magento\Csp\Model\Policy\FetchPolicyFactory;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Store\Model\StoreManagerInterface;
use Stape\Gtm\Model\ConfigProvider;
use Stape\Gtm\Model\SameOrigin\ProxyContext;
use Stape\Gtm\Model\SnippetProvider;

class CspObserverPlugin
{
    /**
     * @var ConfigProvider $configProvider
     */
    private $configProvider;

    /**
     * @var FetchPolicyFactory $fetchPolicyFactory
     */
    private $fetchPolicyFactory;

    /**
     * @var DynamicCollector $dynamicCollector
     */
    private $dynamicCollector;

    /**
     * @var StoreManagerInterface $storeManager
     */
    private $storeManager;

    /**
     * @var SnippetProvider $snippetProvider
     */
    private $snippetProvider;

    /**
     * @var ProxyContext $proxyContext
     */
    private $proxyContext;

    /**
     * Define class dependencies
     *
     * @param ConfigProvider $configProvider
     * @param FetchPolicyFactory $fetchPolicyFactory
     * @param DynamicCollector $dynamicCollector
     * @param StoreManagerInterface $storeManager
     * @param SnippetProvider $snippetProvider
     * @param ProxyContext $proxyContext
     */
    public function __construct(
        ConfigProvider $configProvider,
        FetchPolicyFactory $fetchPolicyFactory,
        DynamicCollector $dynamicCollector,
        StoreManagerInterface $storeManager,
        SnippetProvider $snippetProvider,
        ProxyContext $proxyContext
    ) {
        $this->configProvider = $configProvider;
        $this->fetchPolicyFactory = $fetchPolicyFactory;
        $this->dynamicCollector = $dynamicCollector;
        $this->storeManager = $storeManager;
        $this->snippetProvider = $snippetProvider;
        $this->proxyContext = $proxyContext;
    }

    /**
     * Whitelist the configured GTM snippet and custom domain in the CSP policy.
     *
     * Runs around the csp_render observer (controller_front_send_response_before),
     * which fires on every request including full page cache hits. Inline
     * scripts are whitelisted by content hash rather than nonce so the policy
     * stays consistent with the cached page body.
     *
     * @param ObserverInterface $subject
     * @param callable $proceed
     * @param Observer $observer
     * @return mixed
     */
    public function aroundExecute(ObserverInterface $subject, callable $proceed, $observer)
    {
        if ($this->proxyContext->isActive()) {
            return;
        }

        if ($this->configProvider->isActive()) {
            $this->addSnippetScriptHashes();
            $this->addCustomDomainPolicies();
        }

        return $proceed($observer);
    }

    /**
     * Whitelist the configured custom domain for images, scripts and XHR/fetch requests
     *
     * @return void
     */
    private function addCustomDomainPolicies()
    {
        $customDomain = $this->configProvider->getCustomDomain();
        if (empty($customDomain)) {
            return;
        }

        $imgPolicy = $this->fetchPolicyFactory->create([
            'id' => 'img-src',
            'hostSources' => [$customDomain],
            'noneAllowed' => false,
        ]);

        $scriptPolicy = $this->fetchPolicyFactory->create([
            'id' => 'script-src',
            'hostSources' => [$customDomain],
            'noneAllowed' => false,
        ]);

        $connectPolicy = $this->fetchPolicyFactory->create([
            'id' => 'connect-src',
            'hostSources' => [$customDomain],
            'noneAllowed' => false,
        ]);

        $this->dynamicCollector->add($imgPolicy);
        $this->dynamicCollector->add($scriptPolicy);
        $this->dynamicCollector->add($connectPolicy);
    }

    /**
     * Register a script-src hash for every inline script in the configured snippet.
     *
     * The snippet is stored in config with its <script> tags and rendered verbatim,
     * so it never receives a CSP nonce. Hashing the exact inline content is cache
     * safe: the hash is derived from the content, not the request, so it keeps
     * matching the body served from full page cache.
     *
     * @return void
     */
    private function addSnippetScriptHashes()
    {
        $snippet = (string) $this->snippetProvider->getHtml($this->getCurrentStore());
        if ($snippet === '') {
            return;
        }

        if (!preg_match_all('/<script\b[^>]*>(.*?)<\/script>/is', $snippet, $matches)) {
            return;
        }

        $hashes = [];
        foreach ($matches[1] as $scriptContent) {
            if ($scriptContent === '') {
                continue;
            }
            $hash = base64_encode(hash('sha256', $scriptContent, true));
            $hashes[$hash] = 'sha256';
        }

        if (empty($hashes)) {
            return;
        }

        $this->dynamicCollector->add($this->fetchPolicyFactory->create([
            'id' => 'script-src',
            'hashValues' => $hashes,
            'noneAllowed' => false,
        ]));
    }

    /**
     * Retrieve the current store, or null when it cannot be resolved
     *
     * @return \Magento\Store\Api\Data\StoreInterface|null
     */
    private function getCurrentStore()
    {
        try {
            return $this->storeManager->getStore();
        } catch (\Exception $e) {
            return null;
        }
    }
}
