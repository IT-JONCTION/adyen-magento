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
 * Copyright (c) 2020 Adyen B.V.
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
class Adyen_Payment_Model_ProcessPaybylink extends Mage_Core_Model_Abstract
{

    /**
     * Collected debug information
     *
     * @var array
     */
    protected $debugData = array();

    public function cancelExpiredPaybylink()
    {
        $this->debugData = array();

        $this->debugData['processPaybylink begin'] = 'Begin to process cronjob for cancelling expired Pay By Link orders';

        $hours = Mage::helper('adyen')->getConfigData('expires_after', "adyen_pay_by_link");
        if ($hours == '') {
            // Only if no value is set, set it to 1 hour
            $hours = '1';
        }
        $orderPaymentCollection = Mage::getModel('sales/order')->getCollection()
            ->join(array('payment' => 'sales/order_payment'),
                'main_table.entity_id=payment.parent_id',
                array('payment_method' => 'payment.method'))
            ->addFieldToFilter('created_at', array(
                'to' => strtotime('-' . $hours . ' hours', time()),
                'datetime' => true
                ))
            ->addFieldToFilter('payment.method', "adyen_pay_by_link")
            ->addAttributeToFilter('state', array('neq' => 'canceled'))
            ->addAttributeToFilter('adyen_psp_reference', array('null' => true));

        $this->debugData['processPaybylink'] = 'Found ' . $orderPaymentCollection->getSize() . ' orders to cancel';

        foreach ($orderPaymentCollection as $orderPayment) {
            if ($orderPayment->canCancel()) {
                $this->debugData['processPaybylink'] = "Trying to cancel the order: " . $orderPayment->getIncrementId();
                $orderPayment->cancel()->save();
            } else {
                $this->debugData['processPaybylink'] = "Order " . $orderPayment->getIncrementId() . " cannot be cancelled";
            }
        }

        $this->debugData['processPaybylink end'] = 'Cronjob ends';

        return $this->debugData;

    }
}