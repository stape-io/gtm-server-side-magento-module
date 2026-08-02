<?php
namespace Stape\Gtm\Block\Adminhtml\System\Config\SameOrigin\Button;

use Magento\Backend\Block\Template\Context;
use Magento\Backend\Block\Widget\Button;
use Magento\Config\Block\System\Config\Form\Field;
use Magento\Framework\Data\Form\Element\AbstractElement;
use Magento\Store\Model\StoreManagerInterface;
use Stape\Gtm\Model\ConfigProvider;

class Test extends Field
{
    /**
     * @var string $_template
     */
    protected $_template = 'Stape_Gtm::system/config/same-origin/button/test.phtml';

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
     * Render element
     *
     * @param AbstractElement $element
     * @return string
     */
    public function render(AbstractElement $element)
    {
        $element->unsScope()->unsCanUseWebsiteValue()->unsCanUseDefaultValue();
        return parent::render($element);
    }

    /**
     * Retrieve element html code
     *
     * @param AbstractElement $element
     * @return string
     */
    protected function _getElementHtml(AbstractElement $element)
    {
        return $this->_toHtml();
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
                'id' => 'stape_same_origin_test',
                'label' => __('Test connection'),
            ]);
    }
}
