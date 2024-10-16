<?php

namespace Stape\Gtm\Plugin;

use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Framework\HTTP\PhpEnvironment\RemoteAddress;
use Magento\Framework\Stdlib\Cookie\CookieMetadataFactory;
use Magento\Framework\Stdlib\CookieManagerInterface;
use Pdp\CannotProcessHost;
use Stape\Gtm\Model\ConfigProvider;
use Magento\Framework\App\Response\Http as HttpResponse;
use Magento\Framework\App\RequestInterface as HttpRequest;
use Pdp\Domain;
use Pdp\Rules;

class HttpPlugin
{
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
     * Define class dependencies
     *
     * @param CookieManagerInterface $cookieManager
     * @param CookieMetadataFactory $cookieMetadataFactory
     * @param RemoteAddress $remoteAddress
     * @param ConfigProvider $configProvider
     * @param EncryptorInterface $encryptor
     * @param HttpRequest $request
     */
    public function __construct(
        CookieManagerInterface $cookieManager,
        CookieMetadataFactory $cookieMetadataFactory,
        RemoteAddress $remoteAddress,
        ConfigProvider $configProvider,
        EncryptorInterface $encryptor,
        HttpRequest $request
    ) {
        $this->cookieManager = $cookieManager;
        $this->cookieMetaDataFactory = $cookieMetadataFactory;
        $this->config = $configProvider;
        $this->remoteAddress = $remoteAddress;
        $this->encryptor = $encryptor;
        $this->request = $request;
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
     * Retrieve cookie domain
     *
     * @return string
     * @throws CannotProcessHost
     */
    private function getCookieDomain()
    {
        try {
            $publicSuffixList = Rules::fromPath($this->config->getDomainListUrl());
            $domain = Domain::fromIDNA2008($this->request->getHttpHost());
            $result = $publicSuffixList->resolve($domain);
            return $result->registrableDomain()->toString();
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
