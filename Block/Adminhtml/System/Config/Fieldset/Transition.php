<?php
namespace Stape\Gtm\Block\Adminhtml\System\Config\Fieldset;

use Magento\Config\Block\System\Config\Form\Fieldset;
use Magento\Framework\Data\Form\Element\AbstractElement;

class Transition extends Fieldset
{
    /**
     * Add the temporary badge next to the fieldset title
     *
     * @param AbstractElement $element
     * @return string
     */
    protected function _getHeaderTitleHtml($element)
    {
        return parent::_getHeaderTitleHtml($element)
            . '<span class="stape-config-badge">' . $this->escapeHtml(__('Temporary')) . '</span>';
    }
}
