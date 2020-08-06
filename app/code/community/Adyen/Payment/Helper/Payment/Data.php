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
 * Because of the dynamic payment methods used we rewrite the Payment helper so we always get the right payment methods
 * back.
 * Class Adyen_Payment_Helper_Payment_Data
 */
class Adyen_Payment_Helper_Payment_Data extends Mage_Payment_Helper_Data
{

    /**
     * Retrieve method model object
     *
     * @param   string $code
     * @return  Mage_Payment_Model_Method_Abstract|false
     */
    public function getMethodInstance($code)
    {
        $key = self::XML_PATH_PAYMENT_METHODS . '/' . $code . '/model';
        $class = Mage::getStoreConfig($key);

        $methodInstance = Mage::getModel($class);
        if (method_exists($methodInstance, 'setCode')) {
            $methodInstance->setCode($code);
        }

        return $methodInstance;
    }

    /**
     * Get and sort available payment methods for specified or current store
     *
     * array structure:
     *  $index => Varien_Simplexml_Element
     *
     * @todo maybe we can use this method instead of loading the payment methods on each pageload.
     * @param mixed $store
     * @param Mage_Sales_Model_Quote $quote
     * @return array
     */
    public function getStoreMethods($store = null, $quote = null)
    {
        $res = array();
        foreach ($this->getPaymentMethods($store) as $code => $methodConfig) {
            $prefix = self::XML_PATH_PAYMENT_METHODS . '/' . $code . '/';
            if (!$model = Mage::getStoreConfig($prefix . 'model', $store)) {
                continue;
            }

            /** @var Mage_Payment_Model_Method_Abstract $methodInstance */
            $methodInstance = Mage::getModel($model);
            if (method_exists($methodInstance, 'setCode')) {
                $methodInstance->setCode($code);
            }

            if (!$methodInstance) {
                continue;
            }

            $methodInstance->setStore($store);
            if (!$methodInstance->isAvailable($quote)) {
                /* if the payment method cannot be used at this time */
                continue;
            }

            $sortOrder = (int)$methodInstance->getConfigData('sort_order', $store);
            $methodInstance->setSortOrder($sortOrder);
            $res[] = $methodInstance;
        }

        usort($res, array($this, '_sortMethods'));
        return $res;
    }

    /**
     * Retrieve payment information block
     *
     * @param   Mage_Payment_Model_Info $info
     * @return  Mage_Core_Block_Template
     */
    public function getInfoBlock(Mage_Payment_Model_Info $info)
    {
        $instance = $this->getMethodInstance($info->getMethod());
        if ($instance) {
            $instance->setInfoInstance($info);
            $info->setMethodInstance($instance);
        }

        $blockType = $instance->getInfoBlockType();
        if ($this->getLayout()) {
            $block = $this->getLayout()->createBlock($blockType);
        } else {
            $className = Mage::getConfig()->getBlockClassName($blockType);
            $block = new $className;
        }

        $block->setInfo($info);
        return $block;
    }
}
