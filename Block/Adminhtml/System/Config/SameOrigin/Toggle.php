<?php
namespace Stape\Gtm\Block\Adminhtml\System\Config\SameOrigin;

use Magento\Config\Block\System\Config\Form\Field;
use Magento\Framework\Data\Form\Element\AbstractElement;

class Toggle extends Field
{
    /**
     * @var string $_template
     */
    protected $_template = 'Stape_Gtm::system/config/same-origin/toggle.phtml';

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
     * Check whether the field is currently enabled
     *
     * @return bool
     */
    public function isChecked()
    {
        return (string) $this->getElement()->getValue() === '1';
    }
}
