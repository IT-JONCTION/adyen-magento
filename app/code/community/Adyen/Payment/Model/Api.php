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
 * @copyright  Copyright (c) 2015 Adyen BV (http://www.adyen.com)
 */
class Adyen_Payment_Model_Api extends Mage_Core_Model_Abstract
{
    const ENDPOINT_TEST = "https://pal-test.adyen.com/pal/adapter/httppost";
    const ENDPOINT_LIVE = "https://pal-live.adyen.com/pal/adapter/httppost";
    const ENDPOINT_TERMINAL_CLOUD_TEST = "https://terminal-api-test.adyen.com/sync";
    const ENDPOINT_TERMINAL_CLOUD_LIVE = "https://terminal-api-live.adyen.com/sync";
    const ENDPOINT_PROTOCOL = "https://";
    const CHECKOUT_ENDPOINT_LIVE_SUFFIX = "-checkout-live.adyenpayments.com/checkout";
    const ENDPOINT_CONNECTED_TERMINALS_TEST = "https://terminal-api-test.adyen.com/connectedTerminals";
    const ENDPOINT_CONNECTED_TERMINALS_LIVE = "https://terminal-api-live.adyen.com/connectedTerminals";
    const ENDPOINT_CHECKOUT_TEST = "https://checkout-test.adyen.com/checkout";
    const GUEST_ID = "customer_";

    /**
     * Do the actual API request
     *
     * @param array $request
     * @param int|Mage_Core_model_Store $storeId
     *
     * @throws Adyen_Payment_Exception
     * @return mixed
     */
    protected function _doRequest(array $request, $storeId)
    {
        if ($storeId instanceof Mage_Core_model_Store) {
            $storeId = $storeId->getId();
        }

        $requestUrl = self::ENDPOINT_LIVE;
        if ($this->_helper()->getConfigDataDemoMode($storeId)) {
            $requestUrl = self::ENDPOINT_TEST;
        }

        $username = $this->_helper()->getConfigDataWsUserName($storeId);
        $password = $this->_helper()->getConfigDataWsPassword($storeId);

        $logRequest = $request;
        $logRequest['additionalData'] = '';
        Mage::log($logRequest, null, 'adyen_api.log');

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $requestUrl);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
        curl_setopt($ch, CURLOPT_USERPWD, $username . ":" . $password);
        curl_setopt($ch, CURLOPT_POST, count($request));
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($request));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $result = curl_exec($ch);
        $error = curl_error($ch);

        $httpStatus = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        curl_close($ch);

        if ($result === false) {
            Adyen_Payment_Exception::throwException($error);
        }

        if ($httpStatus != 200) {
            Adyen_Payment_Exception::throwException(
                Mage::helper('adyen')->__(
                    'HTTP Status code %s received, data %s',
                    $httpStatus, $result
                )
            );
        }

        return $result;
    }

    /**
     * @return Adyen_Payment_Helper_Data
     */
    protected function _helper()
    {
        return Mage::helper('adyen');
    }

    /**
     * Do the API request in json format
     *
     * @param array $request
     * @param $requestUrl
     * @param $apiKey
     * @param $storeId
     * @param null $timeout
     * @return mixed
     */
    protected function doRequestJson(array $request, $requestUrl, $apiKey, $storeId, $timeout = null)
    {
        $ch = curl_init();
        $headers = array(
            'Content-Type: application/json'
        );

        if (empty($apiKey)) {
            $username = $this->_helper()->getConfigDataWsUserName($storeId);
            $password = $this->_helper()->getConfigDataWsPassword($storeId);
            curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
            curl_setopt($ch, CURLOPT_USERPWD, $username . ":" . $password);
        } else {
            $headers[] = 'x-api-key: ' . $apiKey;
        }

        if (!empty($timeout)) {
            curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
        }

        Mage::log($request, null, 'adyen_api.log');

        curl_setopt($ch, CURLOPT_URL, $requestUrl);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($request));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $result = curl_exec($ch);
        $error = curl_error($ch);
        $httpStatus = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $errorCode = curl_errno($ch);
        curl_close($ch);
        if ($result === false) {
            Adyen_Payment_Exception::throwCurlException($error, $errorCode);
        }

        if ($httpStatus == 401 || $httpStatus == 403) {
            Adyen_Payment_Exception::throwException(
                Mage::helper('adyen')->__(
                    'Received Status code %s, please make sure your Checkout API key is correct.',
                    $httpStatus
                )
            );
        } elseif ($httpStatus != 200) {
            Adyen_Payment_Exception::throwException(
                Mage::helper('adyen')->__(
                    'HTTP Status code %s received, data %s',
                    $httpStatus, $result
                )
            );
        }

        Mage::log($result, null, 'adyen_api.log');

        return $result;
    }

    /**
     * Set the timeout and do a sync request to the Terminal API endpoint
     *
     * @param array $request
     * @param int $storeId
     * @return mixed
     */
    public function doRequestSync(array $request, $storeId)
    {
        $requestUrl = self::ENDPOINT_TERMINAL_CLOUD_LIVE;
        if ($this->_helper()->getConfigDataDemoMode($storeId)) {
            $requestUrl = self::ENDPOINT_TERMINAL_CLOUD_TEST;
        }

        $apiKey = $this->_helper()->getPosApiKey($storeId);
        $timeout = $this->_helper()->getConfigData('timeout', 'adyen_pos_cloud', $storeId);
        $response = $this->doRequestJson($request, $requestUrl, $apiKey, $storeId, $timeout);
        return json_decode($response, true);
    }

    /**
     * Do a synchronous request to retrieve the connected terminals
     *
     * @param $storeId
     * @return mixed
     */
    public function retrieveConnectedTerminals($storeId)
    {
        $requestUrl = self::ENDPOINT_CONNECTED_TERMINALS_LIVE;
        if ($this->_helper()->getConfigDataDemoMode($storeId)) {
            $requestUrl = self::ENDPOINT_CONNECTED_TERMINALS_TEST;
        }

        $apiKey = $this->_helper()->getPosApiKey($storeId);
        $merchantAccount = $this->_helper()->getAdyenMerchantAccount("pos_cloud", $storeId);
        $request = array("merchantAccount" => $merchantAccount);

        //If store_code is configured, retrieve only terminals connected to that store
        $storeCode = $this->_helper()->getConfigData('store_code', 'adyen_pos_cloud', $storeId);
        if ($storeCode) {
            $request["store"] = $storeCode;
        }
        $response = $this->doRequestJson($request, $requestUrl, $apiKey, $storeId);
        return $response;
    }

    /**
     * Create a payment request
     *
     * @param $order
     * @return mixed
     */
    public function requestToPaymentLinks($order)
    {
        // configurations
        $orderCurrencyCode = $order->getOrderCurrencyCode();
        $incrementId = $order->getIncrementId();
        $realOrderId = $order->getRealOrderId();
        $customerId = Mage::helper('adyen/payment')->getShopperReference($order->getCustomerId(), $realOrderId);
        $merchantAccount = Mage::helper('adyen')->getAdyenMerchantAccount();
        $customerEmail = $order->getCustomerEmail();
        $billingAddress = Mage::helper('adyen/payment')->getPaymentLinksBillingAddressDetails($order->getBillingAddress());
        $deliveryAddress = Mage::helper('adyen/payment')->getPaymentLinksDeliveryAddressDetails($order->getShippingAddress());
        $storeId = $order->getStoreId();
        if ($this->_helper()->getConfigData('store_payment_method', "adyen_pay_by_link", $storeId)) {
            $storePaymentMethod = "true";
        } else {
            $storePaymentMethod = "false";
        }

        if ($this->_helper()->getConfigDataDemoMode()) {
            $requestUrl = self::ENDPOINT_CHECKOUT_TEST . "/v52/paymentLinks";
        } else {
            $requestUrl = self::ENDPOINT_PROTOCOL . $this->_helper()->getConfigData("live_endpoint_url_prefix") . self::CHECKOUT_ENDPOINT_LIVE_SUFFIX . "/v52/paymentLinks";
        }

        $billingCountryCode = (is_object($order->getBillingAddress()) && $order->getBillingAddress()->getCountry() != "") ? $order->getBillingAddress()->getCountry() : null;

        $apiKey = $this->_helper()->getConfigDataApiKey();
        $request = array();

        if ($billingCountryCode) {
            $request['countryCode'] = $billingCountryCode;
        }

        $request['merchantAccount'] = $merchantAccount;
        $request['returnUrl'] = Mage::getUrl('adyen/process/successpage');
        $request['amount'] = array(
            'currency' => $orderCurrencyCode,
            'value' => Mage::helper('adyen')->formatAmount($order->getGrandTotal(), $orderCurrencyCode)
        );
        $request['reference'] = $incrementId;
        $request['fraudOffset'] = '0';
        $request['shopperEmail'] = $customerEmail;
        $request['shopperIP'] = $order->getXForwardedFor();
        $request['shopperReference'] = !empty($customerId) ? $customerId : self::GUEST_ID . $realOrderId;
        $request['expiresAt'] = date(DATE_ATOM,
            mktime(date("H") + 1, date("i"), date("s"), date("m"), date("j"), date("Y")));
        $request['shopperLocale'] = Mage::helper('adyen')->getCurrentLocaleCode($storeId);
        $request['storePaymentMethod'] = $storePaymentMethod;
        $request['lineItems'] = Mage::helper('adyen/payment')->getOpenInvoiceDataPayByLink($order);

        $request = Mage::helper('adyen')->setApplicationInfo($request, true);
        $request['billingAddress'] = $billingAddress;
        $request['deliveryAddress'] = $deliveryAddress;

        $response = $this->doRequestJson($request, $requestUrl, $apiKey, null);
        return json_decode($response, true);
    }
}
