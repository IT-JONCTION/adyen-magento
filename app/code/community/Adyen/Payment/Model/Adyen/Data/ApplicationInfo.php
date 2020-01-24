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
 * @copyright  Copyright (c) 2018 Adyen BV (http://www.adyen.com)
 */
class Adyen_Payment_Model_Adyen_Data_ApplicationInfo extends Adyen_Payment_Model_Adyen_Data_Abstract
{

    public $merchantApplication = array(
        'name' => Adyen_Payment_Helper_Data::PLUGIN_NAME
    );

    public $adyenPaymentSource = array(
        'name' => Adyen_Payment_Helper_Data::PLUGIN_NAME
    );
    public $externalPlatform = array(
        'name' => Adyen_Payment_Helper_Data::EXTERNAL_PLATFORM_NAME
    );

    public function __construct()
    {
        $pluginVersion = Mage::helper('adyen')->getExtensionVersion();
        $this->merchantApplication['version'] = $pluginVersion;
        $this->adyenPaymentSource['version'] = $pluginVersion;
        $this->externalPlatform['version'] = Mage::getVersion();

    }
}
