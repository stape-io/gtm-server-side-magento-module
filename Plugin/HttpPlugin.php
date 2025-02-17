<?php

namespace Stape\Gtm\Plugin;

use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Framework\HTTP\PhpEnvironment\RemoteAddress;
use Magento\Framework\Stdlib\Cookie\CookieMetadataFactory;
use Magento\Framework\Stdlib\CookieManagerInterface;
use Magento\Store\Model\StoreManagerInterface;
use Pdp\CannotProcessHost;
use Stape\Gtm\Model\ConfigProvider;
use Magento\Framework\App\Response\Http as HttpResponse;
use Magento\Framework\App\RequestInterface as HttpRequest;
use Magento\Framework\App\CacheInterface;
use Pdp\Domain;
use Pdp\Rules;

class HttpPlugin
{
    const CACHE_TAG = 'STAPE_COOKIE_DOMAIN';

    /*
     * Cookie name
     */
    public const COOKIE_NAME = '_sbp';

    /*
     * Cookie lifetime
     */
    public const COOKIE_LIFETIME = 63072000;

    /**
     * @var CookieManagerInterface $cookieManager
     */
    private $cookieManager;

    /**
     * @var CookieMetadataFactory $cookieMetaDataFactory
     */
    private $cookieMetaDataFactory;

    /**
     * @var ConfigProvider $config
     */
    private $config;

    /**
     * @var \Magento\Framework\App\HttpRequestInterface $request
     */
    private $remoteAddress;

    /**
     * @var EncryptorInterface $encryptor
     */
    private $encryptor;

    /**
     * @var HttpRequest $request
     */
    private $request;

    /**
     * @var CacheInterface $cache
     */
    private $cache;

    /**
     * @var StoreManagerInterface $storeManager
     */
    protected $storeManager;

    /**
     * Define class dependencies
     *
     * @param CookieManagerInterface $cookieManager
     * @param CookieMetadataFactory $cookieMetadataFactory
     * @param RemoteAddress $remoteAddress
     * @param ConfigProvider $configProvider
     * @param EncryptorInterface $encryptor
     * @param HttpRequest $request
     * @param CacheInterface $cache
     * @param StoreManagerInterface $storeManager
     */
    public function __construct(
        CookieManagerInterface $cookieManager,
        CookieMetadataFactory $cookieMetadataFactory,
        RemoteAddress $remoteAddress,
        ConfigProvider $configProvider,
        EncryptorInterface $encryptor,
        HttpRequest $request,
        CacheInterface $cache,
        StoreManagerInterface $storeManager
    ) {
        $this->cookieManager = $cookieManager;
        $this->cookieMetaDataFactory = $cookieMetadataFactory;
        $this->config = $configProvider;
        $this->remoteAddress = $remoteAddress;
        $this->encryptor = $encryptor;
        $this->request = $request;
        $this->cache = $cache;
        $this->storeManager = $storeManager;
    }

    /**
     * Generate cookie value
     *
     * @return string
     */
    private function generateCookieValue()
    {
        return $this->encryptor->hash(implode(':', [
            $this->remoteAddress->getRemoteAddress(),
            time(),
            uniqid()
        ]));
    }

    /**
     * Retrieve cache key
     *
     * @return string
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    protected function getCacheKey()
    {
        return implode('_', [self::CACHE_TAG, '_STORE_', $this->storeManager->getStore()->getId()]);
    }

    /**
     * Retrieve cookie domain
     *
     * @return string
     * @throws CannotProcessHost
     */
    private function getCookieDomain()
    {
        try {

            if ($cacheValue = $this->cache->load($this->getCacheKey())) {
                return $cacheValue;
            }

            $publicSuffixList = Rules::fromPath($this->config->getDomainListUrl());
            $domain = Domain::fromIDNA2008($this->request->getHttpHost());
            $result = $publicSuffixList->resolve($domain);
            $cacheValue = $result->registrableDomain()->toString();
            $this->cache->save($cacheValue, $this->getCacheKey());
            return $cacheValue;
        } catch (\Exception $e) {
            return $this->request->getHttpHost();
        }
    }

    /**
     * Setting _sbp cookie
     *
     * @param HttpResponse $subject
     * @return void
     * @throws \Magento\Framework\Exception\InputException
     * @throws \Magento\Framework\Stdlib\Cookie\CookieSizeLimitReachedException
     * @throws \Magento\Framework\Stdlib\Cookie\FailureToSendException
     */
    public function beforeSendResponse(HttpResponse $subject)
    {
        $metadata = $this->cookieMetaDataFactory->createPublicCookieMetadata()
            ->setHttpOnly(false)
            ->setSecure(true)
            ->setDuration(self::COOKIE_LIFETIME)
            ->setPath('/')
            ->setDomain('.' . $this->getCookieDomain());

        if (!$this->config->isActive() || !$this->config->useCookieKeeper()) {
            $this->cookieManager->deleteCookie(self::COOKIE_NAME, $metadata);
            return;
        }

        $cookieValue = $this->cookieManager->getCookie(self::COOKIE_NAME, $this->generateCookieValue());

        /** @var \Magento\Framework\App\Request\Http $request */
        $this->cookieManager->setPublicCookie(self::COOKIE_NAME, $cookieValue, $metadata);
    }
}
