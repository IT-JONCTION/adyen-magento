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
 * @copyright  Copyright (c) 2014 Adyen BV (http://www.adyen.com)
 */
class Adyen_Payment_Model_Observer
{

    /**
     * @param Varien_Event_Observer $observer
     */
    public function salesOrderPaymentCancel(Varien_Event_Observer $observer)
    {
        $adyenHelper = Mage::helper('adyen');

        $payment = $observer->getEvent()->getPayment();

        /** @var Mage_Sales_Model_Order $order */
        $order = $payment->getOrder();

        $autoRefund = $adyenHelper->getConfigData('autorefundoncancel', 'adyen_abstract', $order->getStoreId());

        if ($this->isPaymentMethodAdyen($order) && $autoRefund) {
            $pspReference = Mage::getModel('adyen/event')->getOriginalPspReference($order->getIncrementId());
            $payment->getMethodInstance()->sendCancelRequest($payment, $pspReference);
        }
    }

    /**
     * Determine if the payment method is Adyen
     * @param Mage_Sales_Model_Order $order
     * @return boolean
     */
    public function isPaymentMethodAdyen(Mage_Sales_Model_Order $order)
    {
        return strpos($order->getPayment()->getMethod(), 'adyen') !== false;
    }

    /**
     * Capture the invoice just before the shipment is created
     *
     * @param Varien_Event_Observer $observer
     * @return Adyen_Payment_Model_Observer $this
     * @throws Exception
     */
    public function captureInvoiceOnShipment(Varien_Event_Observer $observer)
    {
        /* @var Mage_Sales_Model_Order_Shipment $shipment */
        $shipment = $observer->getShipment();

        /** @var Mage_Sales_Model_Order $order */
        $order = $shipment->getOrder();

        /** @var Adyen_Payment_Helper_Data $adyenHelper */
        $adyenHelper = Mage::helper('adyen');
        $storeId = $order->getStoreId();

        $captureOnShipment = $adyenHelper->getConfigData('capture_on_shipment', 'adyen_abstract', $storeId);
        $createPendingInvoice = $adyenHelper->getConfigData('create_pending_invoice', 'adyen_abstract', $storeId);

        // validate if payment method is adyen and if capture_on_shipment is enabled
        if ($this->isPaymentMethodAdyen($order) && $captureOnShipment) {
            if ($createPendingInvoice) {
                $transaction = Mage::getModel('core/resource_transaction');
                $transaction->addObject($order);

                foreach ($order->getInvoiceCollection() as $invoice) {
                    /* @var Mage_Sales_Model_Order_Invoice $invoice */
                    if (!$invoice->canCapture()) {
                        throw new Adyen_Payment_Exception($adyenHelper->__("Could not capture the invoice"));
                    }

                    $invoice->capture();
                    $invoice->setCreatedAt(now());
                    $transaction->addObject($invoice);
                }

                $order->setIsInProcess(true);
                $transaction->save();
            } else {
                // create an invoice and do a capture to adyen
                if ($order->canInvoice()) {
                    try {
                        /* @var Mage_Sales_Model_Order_Invoice $invoice */
                        $invoice = $order->prepareInvoice();
                        $invoice->getOrder()->setIsInProcess(true);

                        // set transaction id so you can do a online refund from credit memo
                        $invoice->setTransactionId(1);
                        $invoice->register()->capture();
                        $invoice->save();
                    } catch (Exception $e) {
                        Mage::logException($e);

                        throw new Adyen_Payment_Exception($adyenHelper->__("Could not capture the invoice"));
                    }

                    $invoiceAutoMail = (bool)$adyenHelper->getConfigData(
                        'send_invoice_update_mail', 'adyen_abstract',
                        $storeId
                    );
                    if ($invoiceAutoMail) {
                        $invoice->sendEmail();
                    }
                } else {
                    // If there is already an invoice created, continue shipment
                    if ($order->hasInvoices() == 0) {
                        throw new Adyen_Payment_Exception($adyenHelper->__("Could not create the invoice"));
                    }
                }
            }
        }

        return $this;
    }

    /**
     * Set current invoice to payment when capturing.
     *
     * @param Varien_Event_Observer $observer
     * @return Adyen_Payment_Model_Observer $this
     */
    public function addCurrentInvoiceToPayment(Varien_Event_Observer $observer)
    {
        $invoice = $observer->getInvoice();
        $payment = $observer->getPayment();
        $payment->setCurrentInvoice($invoice);

        return $this;
    }
}
