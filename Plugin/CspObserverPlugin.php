<?php

namespace Stape\Gtm\Plugin;

use Magento\Csp\Model\Collector\DynamicCollector;
use Magento\Csp\Model\Policy\FetchPolicyFactory;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Stape\Gtm\Model\ConfigProvider;

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
     * Define class dependencies
     *
     * @param ConfigProvider $configProvider
     * @param FetchPolicyFactory $fetchPolicyFactory
     * @param DynamicCollector $dynamicCollector
     */
    public function __construct(
        ConfigProvider $configProvider,
        FetchPolicyFactory $fetchPolicyFactory,
        DynamicCollector $dynamicCollector
    ) {
        $this->configProvider = $configProvider;
        $this->fetchPolicyFactory = $fetchPolicyFactory;
        $this->dynamicCollector = $dynamicCollector;
    }

    /**
     * Whitelist the configured GTM snippet and custom domain in the CSP policy.
     *
     * Runs on the csp_render observer (controller_front_send_response_before),
     * which fires on every request including full page cache hits. Inline
     * scripts are whitelisted by content hash rather than nonce so the policy
     * stays consistent with the cached page body.
     *
     * @param ObserverInterface $subject
     * @param Observer $observer
     * @return array
     */
    public function beforeExecute(ObserverInterface $subject, $observer)
    {
        if (!$this->configProvider->isActive()) {
            return [$observer];
        }

        $this->addSnippetScriptHashes();

        $customDomain = $this->configProvider->getCustomDomain();
        if (empty($customDomain)) {
            return [$observer];
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
        return [$observer];
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
        $snippet = (string) $this->configProvider->getGtmSnippet();
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
}
