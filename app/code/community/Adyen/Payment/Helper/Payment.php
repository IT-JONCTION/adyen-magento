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
class Adyen_Payment_Helper_Payment extends Adyen_Payment_Helper_Data
{

    /**
     * @var GUEST_ID , used when order is placed by guests
     */
    const GUEST_ID = 'customer_';

    /**
     * @param $customerId
     * @param $realOrderId
     * @return string
     */
    public function getShopperReference($customerId, $realOrderId)
    {
        if ($customerId) { // there is a logged in customer for this order
            // the following allows to send the 'pretty' customer ID or increment ID to Adyen instead of the entity id
            // used collection here, it's about half the resources of using the load method on the customer opject
            /* var $customer Mage_Customer_Model_Resource_Customer_Collection */
            $collection = Mage::getResourceModel('customer/customer_collection')
                ->addAttributeToSelect('adyen_customer_ref')
                ->addAttributeToSelect('increment_id')
                ->addAttributeToFilter('entity_id', $customerId);
            $collection->getSelect()->limit(1);
            $customer = $collection->getFirstItem();

            if ($customer->getData('adyen_customer_ref')) {
                $customerId = $customer->getData('adyen_customer_ref');
            } elseif ($customer->getData('increment_id')) {
                $customerId = $customer->getData('increment_id');
            } else {
                $customerId = $customer->getId();
            }

            return $customerId;
        } else { // it was a guest order
            $customerId = self::GUEST_ID . $realOrderId;
            return $customerId;
        }
    }

    /**
     * @param $billingAddress
     * @return array
     */
    public function getPaymentLinksBillingAddressDetails($billingAddress)
    {
        $billingAddressRequest = array(
            'street' => 'N/A',
            'houseNumberOrName' => 'N/A',
            'city' => 'N/A',
            'postalCode' => 'N/A',
            'stateOrProvince' => 'N/A',
            'country' => 'N/A'
        );

        $isSeparateHouseNumberRequired = false;

        if (trim($billingAddress->getCountryId()) != "") {
            $billingAddressRequest['country'] = trim($billingAddress->getCountryId());
            $isSeparateHouseNumberRequired = $this->isSeparateHouseNumberRequired($billingAddress->getCountryId());
        }

        if ($isSeparateHouseNumberRequired) {
            if (trim($this->getStreet($billingAddress, true)->getName()) != "") {
                $billingAddressRequest['street'] = trim($this->getStreet($billingAddress,
                    true)->getName());
            }

            if ($this->getStreet($billingAddress, true)->getHouseNumber() != "") {
                $billingAddressRequest['houseNumberOrName'] = trim($this->getStreet($billingAddress,
                    true)->getHouseNumber());
            }
        } else {
            $billingAddressRequest['street'] = implode(' ', $billingAddress->getStreet());
        }

        if (trim($billingAddress->getCity()) != "") {
            $billingAddressRequest['city'] = trim($billingAddress->getCity());
        }

        if (trim($billingAddress->getPostcode()) != "") {
            $billingAddressRequest['postalCode'] = trim($billingAddress->getPostcode());
        }

        if (trim($billingAddress->getRegionCode()) != "") {
            $region = is_numeric($billingAddress->getRegionCode())
                ? $billingAddress->getRegion()
                : $billingAddress->getRegionCode();

            $billingAddressRequest['stateOrProvince'] = trim($region);
        }

        return $billingAddressRequest;
    }


    /**
     * @param $deliveryAddress
     * @return array
     */
    public function getPaymentLinksDeliveryAddressDetails($deliveryAddress)
    {
        // Gift Cards and downloadable products don't have delivery addresses
        if (!is_object($deliveryAddress)) {
            return null;
        }

        $deliveryAddressRequest = array(
            'street' => 'N/A',
            'houseNumberOrName' => 'N/A',
            'city' => 'N/A',
            'postalCode' => 'N/A',
            'stateOrProvince' => 'N/A',
            'country' => 'N/A'
        );

        $isSeparateHouseNumberRequired = false;

        if (trim($deliveryAddress->getCountryId()) != "") {
            $deliveryAddressRequest['country'] = trim($deliveryAddress->getCountryId());
            $isSeparateHouseNumberRequired = $this->isSeparateHouseNumberRequired($deliveryAddress->getCountryId());
        }

        if ($isSeparateHouseNumberRequired) {
            if (trim($this->getStreet($deliveryAddress, true)->getName()) != "") {
                $deliveryAddressRequest['street'] = trim($this->getStreet($deliveryAddress, true)->getName());
            }

            if ($this->getStreet($deliveryAddress, true)->getHouseNumber() != "") {
                $deliveryAddressRequest['houseNumberOrName'] = trim($this->getStreet($deliveryAddress,
                    true)->getHouseNumber());
            }
        } else {
            $deliveryAddressRequest['street'] = implode(' ', $deliveryAddress->getStreet());
        }

        if (trim($deliveryAddress->getCity()) != "") {
            $deliveryAddressRequest['city'] = trim($deliveryAddress->getCity());
        }

        if (trim($deliveryAddress->getPostcode()) != "") {
            $deliveryAddressRequest['postalCode'] = trim($deliveryAddress->getPostcode());
        }

        if (trim($deliveryAddress->getRegionCode()) != "") {
            $deliveryAddressRequest['stateOrProvince'] = trim($deliveryAddress->getRegionCode());
        }


        return $deliveryAddressRequest;
    }

    /**
     * Get openinvoice data lines
     *
     * @param $order
     * @return array
     */
    public function getOpenInvoiceDataPayByLink($order)
    {
        $currency = $order->getOrderCurrencyCode();
        $openInvoiceData = array();

        // loop through items
        foreach ($order->getItemsCollection() as $item) {
            //skip dummies
            if ($item->isDummy()) {
                continue;
            }
            $id = $item->getProductId();
            $product = $this->loadProductById($id);
            $taxRate = $this->getTaxRate($order, $product->getTaxClassId());
            $taxAmount = $item->getPrice() * ($item->getTaxPercent() / 100);
            $formattedTaxAmount = $this->formatAmount($taxAmount, $currency);
            $itemId = $id;
            if (!empty($item->getSku())) {
                $itemId = $item->getSku();
            } elseif (!empty($item->getId())) {
                $itemId = $item->getId();
            }

            $openInvoiceData[] = [
                'id' => $item->getId(),
                'itemId' => $itemId,
                'description' => str_replace("\n", '', trim($item->getName())),
                'amountExcludingTax' => $this->formatAmount($item->getPrice(), $currency),
                'taxAmount' => $formattedTaxAmount,
                'quantity' => (int)$item->getQtyOrdered(),
                'taxCategory' => 'High',
                'taxPercentage' => $this->getMinorUnitTaxPercent($taxRate)
            ];

        }

        //discount cost, it is always negative, if present
        if ($order->getDiscountAmount() < 0) {
            $itemAmount = $this->formatAmount($order->getDiscountAmount(), $currency);
            $openInvoiceData[] = [
                'id' => $this->__('Discount'),
                'amountExcludingTax' => $itemAmount,
                'taxAmount' => "0",
                'description' => $this->__('Discount'),
                'quantity' => 1,
                'taxCategory' => 'None',
                'taxPercentage' => "0"
            ];
        }

        //shipping cost
        if ($order->getShippingAmount() > 0 || $order->getShippingTaxAmount() > 0) {
            // Calculate vat percentage
            $taxClass = Mage::getStoreConfig('tax/classes/shipping_tax_class', $order->getStoreId());
            $taxRate = $this->getTaxRate($order, $taxClass);
            $openInvoiceData[] = [
                'itemId' => 'shippingCost',
                'amountExcludingTax' => $this->formatAmount($order->getShippingAmount(), $currency),
                'taxAmount' => $this->formatAmount($order->getShippingTaxAmount(), $currency),
                'description' => $order->getShippingDescription(),
                'quantity' => 1,
                'taxPercentage' => $this->getMinorUnitTaxPercent($taxRate)
            ];
        }
        return $openInvoiceData;
    }

    public function loadProductById($id)
    {
        return Mage::getModel('catalog/product')->load($id);
    }

    /**
     * Checks if the house number needs to be sent to the Adyen API separately or as it is in the street field
     *
     * @param $country
     * @return bool
     */
    public function isSeparateHouseNumberRequired($country)
    {
        $countryList = ["nl", "de", "se", "no", "at", "fi", "dk"];

        return in_array(strtolower($country), $countryList);
    }
}
