<?php
namespace Stape\Gtm\Block\Adminhtml\System\Config\Webhook\Button;

use Magento\Backend\Block\Widget\Button;
use Magento\Config\Block\System\Config\Form\Field;
use Magento\Framework\Data\Form\Element\AbstractElement;

class Test extends Field
{
    /**
     * @var string $_template
     */
    protected $_template = 'Stape_Gtm::system/config/webhook/button/test.phtml';

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
     * Retrieve url
     *
     * @return string
     */
    public function getActionUrl()
    {
        return $this->getUrl('stape/webhook/test');
    }

    /**
     * Retrieve button html
     *
     * @return string
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function getButton()
    {
        return $this->getLayout()
            ->createBlock(Button::class)
            ->setData([
                'id' => 'test_webhook',
                'label' => __('Test Webhook'),
            ]);
    }
}
