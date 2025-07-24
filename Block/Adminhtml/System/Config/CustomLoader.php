<?php
namespace Stape\Gtm\Block\Adminhtml\System\Config;

use Magento\Framework\Data\Form\Element\AbstractElement;

class CustomLoader extends \Magento\Config\Block\System\Config\Form\Field
{
    /**
     * @var string $_template
     */
    protected $_template = 'Stape_Gtm::system/config/custom-loader.phtml';

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
        $elementHtml = parent::_getElementHtml($element);
        $this->setHtmlId($element->getHtmlId());
        return $this->getLayout()
            ->createBlock(\Magento\Framework\View\Element\Template::class)
            ->setTemplate($this->_template)
            ->setElementHtml($elementHtml)
            ->toHtml();
    }
}
