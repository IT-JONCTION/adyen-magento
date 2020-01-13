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
class Adyen_Payment_Model_Cronjob
{

    /**
     * Collected debug information
     *
     * @var array
     */
    protected $_debugData = array();

    /**
     * This updates the notifications that are in the adyen event queue. This is called by the cronjob of Magento
     * To enable the cronjob on your webserver see the following magento documentation:
     * http://www.magentocommerce.com/wiki/1_-_installation_and_configuration/how_to_setup_a_cron_job
     */
    public function updateNotificationQueue()
    {
        // call ProcessNotifications
        $this->_debugData = Mage::getModel('adyen/processNotification')->updateNotProcessedNotifications();
        $this->_debug(null);
    }

    /**
     * This cancels the orders created with Pay By Link after 1 hour. This is called by the cronjob of Magento
     * To enable the cronjob on your webserver see the following magento documentation:
     * http://www.magentocommerce.com/wiki/1_-_installation_and_configuration/how_to_setup_a_cron_job
     */
    public function cancelExpiredPaybylink()
    {
        // call CancelExpiredPaybylink
        $this->_debugData = Mage::getModel('adyen/processPaybylink')->cancelExpiredPaybylink();
        $this->_debug(null);
    }

    /**
     * Log debug data to file
     *
     * @param $storeId
     * @param mixed $debugData
     */
    protected function _debug($storeId)
    {
        if ($this->_getConfigData('debug', 'adyen_abstract', $storeId)) {
            $file = 'adyen_process_notification_cron.log';
            Mage::getModel('core/log_adapter', $file)->log($this->_debugData);
        }
    }

    /**
     * @param $code
     * @param null $paymentMethodCode
     * @param int|null $storeId
     * @return mixed
     */
    protected function _getConfigData($code, $paymentMethodCode = null, $storeId = null)
    {
        return Mage::helper('adyen')->getConfigData($code, $paymentMethodCode, $storeId);
    }
}
