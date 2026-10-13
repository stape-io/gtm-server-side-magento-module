<?php
namespace Stape\Gtm\Block\Adminhtml\System\Config\SameOrigin;

use Magento\Backend\Block\Template\Context;
use Magento\Backend\Block\Widget\Button;
use Magento\Config\Block\System\Config\Form\Field;
use Magento\Framework\Data\Form\Element\AbstractElement;
use Magento\Store\Model\StoreManagerInterface;
use Stape\Gtm\Model\ConfigProvider;

class Path extends Field
{
    /**
     * Test connection button html id
     */
    public const BUTTON_ID = 'stape_same_origin_test';

    /**
     * Panel template, rendered below the field comment
     */
    public const PANEL_TEMPLATE = 'Stape_Gtm::system/config/same-origin/path-panel.phtml';

    /**
     * @var string $_template
     */
    protected $_template = 'Stape_Gtm::system/config/same-origin/path.phtml';

    /**
     * @var ConfigProvider $configProvider
     */
    private $configProvider;

    /**
     * @var StoreManagerInterface $storeManager
     */
    private $storeManager;

    /**
     * Define class dependencies
     *
     * @param Context $context
     * @param ConfigProvider $configProvider
     * @param StoreManagerInterface $storeManager
     * @param array $data
     */
    public function __construct(
        Context $context,
        ConfigProvider $configProvider,
        StoreManagerInterface $storeManager,
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->configProvider = $configProvider;
        $this->storeManager = $storeManager;
    }

    /**
     * Retrieve element html code
     *
     * @param AbstractElement $element
     * @return string
     */
    protected function _getElementHtml(AbstractElement $element)
    {
        return $this->setElement($element)->_toHtml();
    }

    /**
     * Render element value with the info panel placed below the field comment
     *
     * @param AbstractElement $element
     * @return string
     */
    protected function _renderValue(AbstractElement $element)
    {
        $html = '<td class="value">';
        $html .= $this->_getElementHtml($element);

        if ($element->getComment()) {
            $html .= '<p class="note"><span>' . $element->getComment() . '</span></p>';
        }

        $currentTemplate = $this->getTemplate();
        $html .= $this->setTemplate(self::PANEL_TEMPLATE)->_toHtml();
        $this->setTemplate($currentTemplate);

        $html .= '</td>';

        return $html;
    }

    /**
     * Resolve the store matching the configuration scope being edited
     *
     * @return \Magento\Store\Api\Data\StoreInterface
     * @throws \Magento\Framework\Exception\LocalizedException
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    private function resolveStore()
    {
        if ($storeCode = $this->getRequest()->getParam('store')) {
            return $this->storeManager->getStore($storeCode);
        }

        if ($websiteCode = $this->getRequest()->getParam('website')) {
            return $this->storeManager->getWebsite($websiteCode)->getDefaultStore();
        }

        return $this->storeManager->getDefaultStoreView();
    }

    /**
     * Retrieve saved proxy path for the current configuration scope
     *
     * @return string
     */
    public function getSavedPath()
    {
        try {
            return (string) $this->configProvider->getSameOriginPath($this->resolveStore()->getCode());
        } catch (\Exception $e) {
            return '';
        }
    }

    /**
     * Retrieve store base URL for the current configuration scope
     *
     * @return string
     */
    public function getStoreBaseUrl()
    {
        try {
            return $this->resolveStore()->getBaseUrl();
        } catch (\Exception $e) {
            return '';
        }
    }

    /**
     * Check whether the same-origin proxy is enabled and fully configured with a valid API key
     *
     * @return bool
     */
    public function isSameOriginReady()
    {
        try {
            $storeCode = $this->resolveStore()->getCode();

            return $this->configProvider->isSameOriginConfigured($storeCode)
                && strlen($this->configProvider->getSameOriginIdentifier($storeCode) ?? '') > 0
                && strlen($this->configProvider->getSameOriginEndpoint($storeCode) ?? '') > 0;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Retrieve the first-party URL requests are proxied through
     *
     * @return string
     */
    public function getProxyUrl()
    {
        $path = $this->getSavedPath();

        if ($path === '') {
            return '';
        }

        return rtrim(rtrim($this->getStoreBaseUrl(), '/') . '/' . ltrim($path, '/'), '/');
    }

    /**
     * Retrieve button html
     *
     * @return \Magento\Framework\View\Element\BlockInterface
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function getButton()
    {
        return $this->getLayout()
            ->createBlock(Button::class)
            ->setData([
                'id' => self::BUTTON_ID,
                'label' => __('Test connection'),
            ]);
    }
}
