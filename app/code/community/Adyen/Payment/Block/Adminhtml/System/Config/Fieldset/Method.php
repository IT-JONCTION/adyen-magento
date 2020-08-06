<?php
/**
 *                       ######
 *                       ######
 * ############    ####( ######  #####. ######  ############   ############
 * #############  #####( ######  #####. ######  #############  #############
 *        ######  #####( ######  #####. ######  #####  ######  #####  ######
 * ###### ######  #####( ######  #####. ######  #####  #####   #####  ######
 * ###### ######  #####( ######  #####. ######  #####          #####  ######
 * #############  #############  #############  #############  #####  ######
 *  ############   ############  #############   ############  #####  ######
 *                                      ######
 *                               #############
 *                               ############
 *
 * Adyen Payment Module
 *
 * Copyright (c) 2019 Adyen B.V.
 * This file is open source and available under the MIT license.
 * See the LICENSE file for more info.
 *
 * Author: Adyen <magento@adyen.com>
 */

/**
 * @category   Payment Gateway
 * @package    Adyen_Payment
 * @author     Adyen
 * @property   Adyen B.V
 * @copyright  Copyright (c) 2014 Adyen BV (http://www.adyen.com)
 */
class Adyen_Payment_Block_Adminhtml_System_Config_Fieldset_Method
    extends Adyen_Payment_Block_Adminhtml_System_Config_Fieldset_Fieldset
{
    /**
     * Check whether current payment method is enabled
     *
     * @param Varien_Data_Form_Element_Abstract $element
     * @param callback|null $configCallback
     * @return bool
     */
    protected function _isPaymentEnabled($element)
    {
        $groupConfig = $this->getGroup($element)->asArray();
        $activityPath = isset($groupConfig['activity_path']) ? $groupConfig['activity_path'] : '';

        if (empty($activityPath)) {
            return false;
        }

        $isPaymentEnabled = (bool)(string)$this->_getConfigDataModel()->getConfigDataValue($activityPath);

        return (bool)$isPaymentEnabled;
    }

    /**
     * Check whether current payment method will be supported after June 2020
     *
     * @param Varien_Data_Form_Element_Abstract $element
     * @param callback|null $configCallback
     * @return bool
     */
    protected function isPaymentDeprecated($element)
    {
        if ((strpos($element->getId(), 'payment_adyen_pay_by_link') !== false) ||
            (strpos($element->getId(), 'payment_adyen_pos_cloud') !== false) ||
            (strpos($element->getId(), 'payment_adyen_abstract') !== false)) {
            return false;
        }
        return true;
    }

    /**
     * Return header title part of html for payment solution
     *
     * @param Varien_Data_Form_Element_Abstract $element
     * @return string
     */
    protected function _getHeaderTitleHtml($element)
    {
        $html = '<div class="entry-edit-head collapseable" ><a id="' . $element->getHtmlId()
            . '-head" href="#" onclick="Fieldset.toggleCollapse(\'' . $element->getHtmlId() . '\', \''
            . $this->getUrl('*/*/state') . '\'); return false;">';

        $html .= ' <img src="' . $this->getSkinUrl('images/adyen/logo.png') . '" height="20" style="vertical-align: text-bottom; margin-right: 5px;"/> ';
        $html .= $element->getLegend();
        if ($this->_isPaymentEnabled($element)) {
            $html .= ' <img src="' . $this->getSkinUrl('images/icon-enabled.png') . '" style="vertical-align: middle"/> ';
        }
        if ($this->isPaymentDeprecated($element)) {
            $html .= ' <img src="' . $this->getSkinUrl('images/warning_msg_icon.gif') . '" style="vertical-align: middle"/> ';
            $html .= "Not available after June 2020, please use Pay By Link instead";
        }


        $html .= '</a></div>';
        return $html;
    }
}
