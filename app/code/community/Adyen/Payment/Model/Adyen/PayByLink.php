<?php

class Adyen_Payment_Model_Adyen_PayByLink extends Adyen_Payment_Model_Adyen_Abstract
{
    protected $_code = 'adyen_pay_by_link';
    protected $_formBlockType = 'adyen/form_payByLink';
    protected $_infoBlockType = 'adyen/info_payByLink';
    protected $_paymentMethod = 'pay_by_link';
    protected $_isInitializeNeeded = true;


    /**
     * @param $paymentAction
     * @param $stateObject
     */
    public function initialize($paymentAction, $stateObject)
    {
        $state = Mage_Sales_Model_Order::STATE_PENDING_PAYMENT;
        $stateObject->setState($state);
        $stateObject->setStatus($this->_getConfigData('order_status'));
    }

    public function getOrderPlaceRedirectUrl()
    {
        return Mage::getUrl('adyen/process/redirect');
    }

    /**
     * Ability to set the code, for dynamic payment methods.
     * @param $code
     * @return $this
     */
    public function setCode($code)
    {
        $this->_code = $code;
        return $this;
    }

    /**
     * @return string
     */
    public function getFormUrl()
    {
        $order = $this->_initOrder();
        $result = $this->_api()->requestToPaymentLinks($order, $this->_paymentMethod);
        try {
            return $result['url'];
        } catch (Exception $e) {
            $this->_debugData['error'] = 'error redirecting to pay by link: ' . $e->getMessage();
            Mage::logException($e);
        }
    }


    /**
     * @return array
     */
    public function getFormFields()
    {
        return array();
    }
}
