<?php

/**
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * @category  PayIntelligent
 * @package   PayIntelligent_RatePAY
 * @copyright (C) 2011 PayIntelligent GmbH  <http://www.payintelligent.de/>
 * @license	http://www.gnu.org/licenses/  GNU General Public License 3
 */

/**
 * Creation and initialising of RatePAY Requests (shop frontend)
 * @extends oxSuperCfg
 */
class pi_ratepay_RatepayRequest extends oxSuperCfg
{

    /**
     * pi_ratepay_rechnung or pi_ratepay_rate
     * @var string
     */
    private $_paymentType;

    /**
     * RatepayXML Service
     * @var pi_ratepay_xmlService
     */
    private $_xmlService;

    /**
     * RatePAY Data Provider
     * @var pi_ratepay_requestAbstract
     */
    private $_dataProvider;

    /**
     * DE or AT (or CH)
     * @var string
     */
    private $_country;
    /**
     * Profile Id
     * @var string
     */
    private $_profileId;
    /**
     * Security Coce
     * @var string
     */
    private $_securityCode;

    /**
     * Is shop set to UTF8 Mode
     * @var bool
     */
    private $_utfMode;

    /**
     * Class constructor
     * @param string $paymentType
     * @param pi_ratepay_RequestAbstract $dataProvider
     * @param pi_ratepay_xmlService $xmlService
     */
    public function __construct($paymentType, pi_ratepay_RequestAbstract $dataProvider = null, $xmlService = null, $extendedData = array())
    {
        parent::__construct();

        $this->_paymentType = $paymentType;
        $this->_country = ($extendedData['country']) ? $extendedData['country'] : false;
        $this->_profileId = ($extendedData['profileId']) ? $extendedData['profileId'] : false;
        $this->_securityCode = ($extendedData['securityCode']) ? $extendedData['securityCode'] : false;
        $this->_dataProvider = $dataProvider;
        $this->_xmlService = isset($xmlService) ? $xmlService : pi_ratepay_xmlService::getInstance();
        $this->_utfMode = $this->getConfig()->isUtf();
    }

    /**
     * Do init payment request.
     * @return array
     */
    public function initPayment()
    {
        $operation = 'PAYMENT_INIT';

        $ratepay = $this->_getXmlService();
        $request = $ratepay->getXMLObject();

        $head = $this->_setRatepayHead($request, $operation);
        $this->_setRatepayHeadMeta($head);

        $initPayment = array(
            'request'  => $request,
            'response' => $ratepay->paymentOperation($request, $this->_getPaymentMethod())
        );

        return $initPayment;
    }

    /**
     * Do a request payment request.
     * @return array
     */
    public function requestPayment()
    {
        $operation = 'PAYMENT_REQUEST';

        $ratepay = $this->_getXmlService();
        $request = $ratepay->getXMLObject();

        $head = $this->_setRatepayHead($request, $operation);
        $this->_setRatepayHeadExternal($head, $operation);
        $this->_setRatepayHeadCustomerDevice($head);
        $this->_setRatepayHeadMeta($head);

        $content = $request->addChild('content');
        $content = $this->_setRatepayContentCustomer($content);
        $content = $this->_setRatepayContentBasket($content);
        $payment = $this->_setRatepayContentPayment($content);

        if ($this->_getPaymentType() == 'pi_ratepay_rate') {
            $installment = $payment->addChild('installment-details');
            $this->_setRatepayContentPaymentInstallment($installment);
            if ($this->_isRateElv()) {
                $payment->addChild('debit-pay-type', 'DIRECT-DEBIT');
            } else {
                $payment->addChild('debit-pay-type', 'BANK-TRANSFER');
            }
        }

        $requestPayment = array(
            'request'  => $request,
            'response' => $ratepay->paymentOperation($request, $this->_getPaymentMethod())
        );

        return $requestPayment;
    }

    /**
     * Do a confirm payment request.
     * @return array
     */
    public function confirmPayment()
    {
        $operation = 'PAYMENT_CONFIRM';
        $ratepay = $this->_getXmlService();
        $request = $ratepay->getXMLObject();

        $head = $this->_setRatepayHead($request, $operation);
        $this->_setRatepayHeadExternal($head, $operation);
        $this->_setRatepayHeadMeta($head);

        $confirmPayment = array(
            'request'  => $request,
            'response' => $ratepay->paymentOperation($request, $this->_getPaymentMethod())
        );

        return $confirmPayment;
    }

    /**
     * Do a confirm payment request.
     * @return array
     */
    public function configRequest()
    {
        $operation = 'CONFIGURATION_REQUEST';
        $ratepay = $this->_getXmlService();
        $request = $ratepay->getXMLObject();

        $head = $this->_setRatepayHead($request, $operation);
        $this->_setRatepayHeadMeta($head);

        $confirmPayment = array(
            'request'  => $request,
            'response' => $ratepay->paymentOperation($request, $this->_getPaymentMethod())
        );

        return $confirmPayment;
    }

    /**
     * Do a request profile request.
     * @return array
     */
    public function profileRequest($country = null)
    {
        $operation = 'PROFILE_REQUEST';
        $ratepay = $this->_getXmlService();
        $request = $ratepay->getXMLObject();
        $this->_setRatepayHead($request, $operation);
        $requestProfile = array(
            'request' => $request,
            'response' => $ratepay->paymentOperation($request, $this->_getPaymentMethod(), $country)
        );
        return $requestProfile;
    }

    /**
     * Generate head node for request xml
     *
     * @param SimpleXMLExtended $request
     * @param string $operation
     * @param string $subtype
     * @return SimpleXMLExtended
     */
    private function _setRatepayHead($request, $operation)
    {
        $head = $request->addChild('head');
        $head->addChild('system-id', $this->_getRatepaySystemID());

        if ($operation != 'PAYMENT_INIT' && $operation != 'CONFIGURATION_REQUEST' && $operation != 'PROFILE_REQUEST') {
            $head->addChild('transaction-id', $this->_getDataProvider()->getTransactionId());
        }

        $operationNode = $head->addChild('operation', $operation);

        $this->_setRatepayHeadCredentials($head);

        return $head;
    }

    /**
     * Adds credentials to request XML.
     *
     * @param SimpleXMLExtended $head
     * @param string $paymentType
     */
    private function _setRatepayHeadCredentials($head)
    {
        $credential = $head->addChild('credential');
        if ($this->_getProfileId() && $this->_getSecurityCode()) {
            $profileId = $this->_getProfileId();
            $securityCode = $this->_getSecurityCode();
        } else {
            $paymentMethod = strtolower($this->_getPaymentMethod());
            $country = $this->_getCountry($this->getUser()->oxuser__oxcountryid->value);
            $settings = oxNew('pi_ratepay_settings');
            if ($country) {
                $settings->loadByType($paymentMethod, $country);
            } else {
                $settings->loadByType($paymentMethod);
            }
            $profileId = $settings->pi_ratepay_settings__profile_id->rawValue;
            $securityCode = $settings->pi_ratepay_settings__security_code->rawValue;
        }
        $credential->addChild('profile-id', $profileId);
        $credential->addChild('securitycode', $securityCode);
    }

    /**
     * Adds orderid to request XML.
     *
     * @param SimpleXMLExtended $head
     */
    private function _setRatepayHeadExternal($head, $operation)
    {
        $external = $head->addChild('external');

        if ($operation == 'PAYMENT_CONFIRM') {
            $external->addChild('order-id', $this->_getDataProvider()->getOrderId());
        }

        if ($operation == 'PAYMENT_REQUEST') {
            $external->addChild('merchant-consumer-id', $this->_getDataProvider()->getCustomerNumber());
        }
    }

    /**
     * Add shop name and version. Add also module version.
     * <system name=”<shopname>_<edition>” version=”<shopversion>_<moduleversion>”></system>
     *
     * @param SimpleXMLExtended $head
     */
    private function _setRatepayHeadMeta($head)
    {
        $meta = $head->addChild('meta');
        $systems = $meta->addChild('systems');
        $system = $systems->addChild('system');

        $system->addAttribute('name', 'OXID_' . oxRegistry::getConfig()->getEdition());
        $system->addAttribute('version', oxRegistry::getConfig()->getVersion() . '_' . pi_ratepay_util_utilities::PI_MODULE_VERSION);
    }

    /**
     * Adds customer-device information to request XML.
     *
     * @uses function _setRatepayHeadCustomerDeviceHttpHeader
     * @param SimpleXMLExtended $head
     */
    private function _setRatepayHeadCustomerDevice($head)
    {
        $customerDevice = $head->addChild('customer-device');
        $this->_setRatepayHeadCustomerDeviceHttpHeader($customerDevice);
    }

    /**
     * Adds device information to the header of the request. defaults to 'x86' and 'UA-CPU'.
     * Also adds type 'text/xml' and charset 'utf-8'.
     *
     * @param SimpleXMLExtended $customerDevice
     */
    private function _setRatepayHeadCustomerDeviceHttpHeader($customerDevice)
    {
        $httpHeaderList = $customerDevice->addChild('http-header-list');

        $httpHeaderListAttr = $httpHeaderList->addChild('header', 'text/xml');
        $httpHeaderListAttr->addAttribute('name', 'Accept');

        $httpHeaderListAttr = $httpHeaderList->addChild('header', 'utf-8');
        $httpHeaderListAttr->addAttribute('name', 'Accept-Charset');

        $httpHeaderListAttr = $httpHeaderList->addChild('header', 'x86');
        $httpHeaderListAttr->addAttribute('name', 'UA-CPU');
    }

    /**
     * Adds cutomer information (first-name, last-name, date-of-birth etc.) to the request XML.
     *
     * @uses function _setRatepayContentCustomerContacts
     * @uses function _setRatepayContentCustomerAddress
     * @param SimpleXMLExtended $content
     */
    private function _setRatepayContentCustomer($content)
    {
        $customer = $content->addChild('customer');

        $customer->addCDataChild('first-name', $this->_removeSpecialChars($this->_getDataProvider()->getCustomerFirstName()), $this->_utfMode);
        $customer->addCDataChild('last-name', $this->_removeSpecialChars($this->_getDataProvider()->getCustomerLastName()), $this->_utfMode);

        $company = $this->_getDataProvider()->getCustomerCompanyName();
        if ($company) {
            $customer->addCDataChild('company-name', $company, $this->_utfMode);
        }

        $customer->addChild('gender', $this->_getDataProvider()->getGender());
        $customer->addChild('date-of-birth', $this->_getDataProvider()->getCustomerDateOfBirth());
        $customer->addChild('ip-address', $this->_getRatepayCustomerIpAddress());

        $this->_setRatepayContentCustomerContacts($customer);
        $this->_setRatepayContentCustomerAddress($customer);
        if ($this->_getPaymentType() === 'pi_ratepay_elv' || $this->_isRateElv()) {
            $this->_setRatepayContentCustomerBankAccount($customer);
        }

        $customer->addChild('nationality', $this->_getDataProvider()->getCustomerNationality());
        $customer->addChild('customer-allow-credit-inquiry', 'yes');

        $vatId = $this->_getDataProvider()->getCustomerVatId();
        if ($vatId) {
            $customer->addChild('vat-id', $vatId);
        }

        return $content;
    }

    /**
     * Adds customer contact information to request XML.
     *
     * @uses function _setRatepayContentCustomerContactsPhone
     * @uses function _setRatepayContentCustomerContactsFax
     * @uses function _setRatepayContentCustomerContactsMobile
     * @param SimpleXMLExtended $customer
     */
    private function _setRatepayContentCustomerContacts($customer)
    {
        $contacts = $customer->addChild('contacts');
        $contacts->addChild('email', $this->_getDataProvider()->getCustomerEmail());

        $this->_setRatepayContenCustomerContactsPhoneNumbers($contacts, 'phone', $this->_getDataProvider()->getCustomerPhone());
        $this->_setRatepayContenCustomerContactsPhoneNumbers($contacts, 'fax', $this->_getDataProvider()->getCustomerFax());
        $this->_setRatepayContenCustomerContactsPhoneNumbers($contacts, 'mobile', $this->_getDataProvider()->getCustomerMobilePhone());
    }

    /**
     * Add phone numbers to request XML.
     *
     * @param SimpleXMLExtended $contacts
     * @param string $type
     * @param string $number
     */
    private function _setRatepayContenCustomerContactsPhoneNumbers($contacts, $type, $number)
    {
        if ($number) {
            $phoneNode = $contacts->addChild($type);
            $phoneNode->addChild('direct-dial', $number);
        }
    }

    /**
     * Adds customers address (billing and shipping) to request XML.
     *
     * @uses function _setRatepayContentCustomerAddressBilling
     * @uses function _setRatepayContentCustomerAddressShipping
     * @param SimpleXMLExtended $customer
     */
    private function _setRatepayContentCustomerAddress($customer)
    {
        $addresses = $customer->addChild('addresses');

        $customerAddress = $this->_getDataProvider()->getCustomerAddress();
        $deliveryAddress = $this->_getDataProvider()->getDeliveryAddress();

        $this->_setRatepayContentCustomerAddressesBilling($addresses, $customerAddress);
        if ($deliveryAddress) {
            $this->_setRatepayContentCustomerAddressesDelivery($addresses, $deliveryAddress);
        }
    }

    /**
     * Adds customer billing address to request xml
     *
     * @param SimpleXMLExtended $addresses
     */
    private function _setRatepayContentCustomerAddressesBilling($addresses, $address)
    {
        $street = $this->_removeSpecialChars($address['street']);
        $city = $this->_removeSpecialChars($address['city']);

        $billingAddress = $addresses->addChild('address');
        $billingAddress->addAttribute('type', 'BILLING');
        $billingAddress->addCDataChild('street', $street, $this->_utfMode);
        $billingAddress->addChild('street-number', $address['street-number']);
        $billingAddress->addChild('zip-code', $address['zip-code']);
        $billingAddress->addCDataChild('city', $city, $this->_utfMode);
        $billingAddress->addChild('country-code', $address['country-code']);
    }

    /**
     * Adds customer delivery address to request xml
     *
     * @param SimpleXMLExtended $addresses
     */
    private function _setRatepayContentCustomerAddressesDelivery($addresses, $address)
    {
        $firstname = $this->_removeSpecialChars($address['first-name']);
        $lastname  = $this->_removeSpecialChars($address['last-name']);
        $company   = $this->_removeSpecialChars($address['company']);
        $street    = $this->_removeSpecialChars($address['street']);
        $city      = $this->_removeSpecialChars($address['city']);

        $deliveryAddress = $addresses->addChild('address');
        $deliveryAddress->addAttribute('type', 'DELIVERY');
        $deliveryAddress->addCDataChild('first-name', $firstname, $this->_utfMode);
        $deliveryAddress->addCDataChild('last-name', $lastname, $this->_utfMode);
        $deliveryAddress->addCDataChild('company', $company, $this->_utfMode);
        $deliveryAddress->addCDataChild('street', $street, $this->_utfMode);
        $deliveryAddress->addChild('street-number', $address['street-number']);
        $deliveryAddress->addChild('zip-code', $address['zip-code']);
        $deliveryAddress->addCDataChild('city', $city, $this->_utfMode);
        $deliveryAddress->addChild('country-code', $address['country-code']);
    }

    /**
     * Adds customer bank account data to request xml
     * @param SimpleXMLExtended $customer
     */
    private function _setRatepayContentCustomerBankAccount($customer)
    {
        $bankdata = $this->_getDataProvider()->getCustomerBankdata($this->_getPaymentType());

        $bankAccount = $customer->addChild('bank-account');
        $bankAccount->addCDataChild('owner', (!mb_detect_encoding($bankdata['owner'], 'UTF-8', true)) ? utf8_encode($bankdata['owner']) : $bankdata['owner']);
        if (empty($bankdata['bankIban'])) {
            $bankAccount->addChild('bank-account-number', $bankdata['bankAccountNumber']);
            $bankAccount->addChild('bank-code', $bankdata['bankCode']);
        } else {
            $bankAccount->addChild('iban', $bankdata['bankIban']);
            if (!empty($bankdata['bankBic'])) {
                $bankAccount->addChild('bic-swift', $bankdata['bankBic']);
            }
        }

    }

    /**
     * Adds basket contents to request XML.
     *
     * @uses function _setRatepayContentBasket
     * @param SimpleXMLExtended $content
     */
    private function _setRatepayContentBasket($content)
    {
        $shoppingBasket = $content->addChild('shopping-basket');
        $shoppingBasket->addAttribute('amount', number_format($this->_getDataProvider()->getBasketAmount(), 2, ".", ""));
        $shoppingBasket->addAttribute('currency', 'EUR');
        $this->_setRatepayContentBasketItems($shoppingBasket);
        return $content;
    }

    /**
     * Adds child node 'items' to 'shopping-basket' child node of request XML content.
     *
     * @uses function _setRatepayContentBasketItemsItem
     * @param SimpleXMLExtended $shoppingBasket
     */
    private function _setRatepayContentBasketItems($shoppingBasket)
    {
        $items = $shoppingBasket->addChild('items');
        $this->_setRatepayContentBasketItemsItem($items);
    }

    /**
     * Adds items to request xml. 'item' nodes consist of several item specific information: like article-number,
     * quantity, unit-price, tax etc.
     *
     * @param SimpleXMLExtended $items
     */
    private function _setRatepayContentBasketItemsItem($items)
    {
        $articles = $this->_getDataProvider()->getBasketArticles();
        foreach ($articles as $article) {
            $item = $items->addCDataChild('item', $article->getTitle(), $this->_utfMode);
            $item->addAttribute('article-number', $article->getArticleNumber());
            $item->addAttribute('quantity', $article->getQuantity());
            $item->addAttribute('unit-price', number_format($article->getUnitPrice(), 2, ".", ""));
            $item->addAttribute('total-price', number_format($article->getPrice(), 2, ".", ""));
            $item->addAttribute('tax', number_format($article->getVatValue(), 2, ".", ""));
        }

        /**
         * add wrapping costs, delivery costs, etc… if null add 0,00 articles
         **/

        $basket = $this->_getDataProvider()->getSession()->getBasket();

        if (method_exists($basket, 'getWrappingCost') && $basket->getWrappingCost()) {
            $wrappingCosts = $basket->getWrappingCost()->getPrice();
            $wrappingNettoPrice = $basket->getWrappingCost()->getNettoPrice();
            $wrappingVatValue = $basket->getWrappingCost()->getVatValue();
        } elseif (method_exists($basket, 'getFWrappingCosts') && $basket->getFWrappingCosts()) {
            $wrappingCosts = $basket->getFWrappingCosts();
            if ($basket->getWrappCostNet() > 0) {
                $wrappingNettoPrice = $basket->getWrappCostNet();
                $wrappingVatValue = $basket->getWrappCostVat();
            } else {
                $wrappingNettoPrice = $wrappingCosts;
                $wrappingVatValue = 0;
            }
        } else {
            $wrappingCosts = 0;
        }

        if ($wrappingCosts != 0) {
            $item = $items->addChild('item', 'Wrapping Cost');
            $item->addAttribute('article-number', 'oxwrapping');
            $item->addAttribute('quantity', 1);
            $item->addAttribute('unit-price', number_format($wrappingNettoPrice, 2, ".", ""));
            $item->addAttribute('total-price', number_format($wrappingNettoPrice, 2, ".", ""));
            $item->addAttribute('tax', number_format($wrappingVatValue, 2, ".", ""));
        }

        if (method_exists($basket, 'getGiftCardCost') && $basket->getGiftCardCost()) {
            $giftcardCosts = $basket->getGiftCardCost()->getPrice();
            $giftcardNettoPrice = $basket->getGiftCardCost()->getNettoPrice();
            $giftcardVatValue = $basket->getGiftCardCost()->getVatValue();
        } elseif (method_exists($basket, 'getFGiftCardCosts') && $basket->getFGiftCardCosts()) {
            $giftcardCosts = $basket->getFGiftCardCosts();
            if ($basket->getGiftCardCostNet() > 0) {
                $giftcardNettoPrice = $basket->getGiftCardCostNet();
                $giftcardVatValue = $basket->getGiftCardCostVat();
            } else {
                $giftcardNettoPrice = $giftcardCosts;
                $giftcardVatValue = 0;
            }
        } else {
            $giftcardCosts = 0;
        }

        if ($giftcardCosts > 0) {
            $item = $items->addChild('item', 'Giftcard Cost');
            $item->addAttribute('article-number', 'oxwgiftcard');
            $item->addAttribute('quantity', 1);
            $item->addAttribute('unit-price', number_format($giftcardNettoPrice, 2, ".", ""));
            $item->addAttribute('total-price', number_format($giftcardNettoPrice, 2, ".", ""));
            $item->addAttribute('tax', number_format($giftcardVatValue, 2, ".", ""));
        }

        if (method_exists($basket, 'getDeliveryCost') && $basket->getDeliveryCost()) {
            $deliveryCosts = $basket->getDeliveryCost()->getPrice();
            $deliveryNettoPrice = $basket->getDeliveryCost()->getNettoPrice();
            $deliveryVatValue = $basket->getDeliveryCost()->getVatValue();
        } elseif (method_exists($basket, 'getDeliveryCosts') && $basket->getDeliveryCosts()) {
            $deliveryCosts = $basket->getDeliveryCosts();
            if ($basket->getDelCostNet() > 0) {
                $deliveryNettoPrice = $basket->getDelCostNet();
                $deliveryVatValue = $basket->getDelCostVat();
            } else {
                $deliveryNettoPrice = $deliveryCosts;
                $deliveryVatValue = 0;
            }
        } else {
            $deliveryCosts = 0;
        }
        
        if ($deliveryCosts > 0) {
            $item = $items->addChild('item', 'Delivery Cost');
            $item->addAttribute('article-number', 'oxdelivery');
            $item->addAttribute('quantity', 1);
            $item->addAttribute('unit-price', number_format($deliveryNettoPrice, 2, ".", ""));
            $item->addAttribute('total-price', number_format($deliveryNettoPrice, 2, ".", ""));
            $item->addAttribute('tax', number_format($deliveryVatValue, 2, ".", ""));
        }
        
        if (method_exists($basket, 'getPaymentCost') && $basket->getPaymentCost()) {
            $paymentCosts = $basket->getPaymentCost()->getPrice();
            $paymentNettoPrice = $basket->getPaymentCost()->getNettoPrice();
            $paymentVatValue = $basket->getPaymentCost()->getVatValue();
        } elseif (method_exists($basket, 'getPaymentCosts') && $basket->getPaymentCosts()) {
            $paymentCosts = $basket->getPaymentCosts();
            if ($basket->getPayCostNet() > 0) {
                $paymentNettoPrice = $basket->getPayCostNet();
                $paymentVatValue = $basket->getPayCostVat();
            } else {
                $paymentNettoPrice = $paymentCosts;
                $paymentVatValue = 0;
            }
        } else {
            $paymentCosts = 0;
        }            

        if ($paymentCosts > 0) {
            $item = $items->addChild('item', 'Payment Cost');
            $item->addAttribute('article-number', 'oxpayment');
            $item->addAttribute('quantity', 1);
            $item->addAttribute('unit-price', number_format($paymentNettoPrice, 2, ".", ""));
            $item->addAttribute('total-price', number_format($paymentNettoPrice, 2, ".", ""));
            $item->addAttribute('tax', number_format($paymentVatValue, 2, ".", ""));
        }

        if (method_exists($basket, 'getTrustedShopProtectionCost')) {
            $tsItem = $basket->getTrustedShopProtectionCost();
        } elseif (method_exists($basket, 'getTsProtectionCosts')) {
            $tsItem = $basket->getTsProtectionCosts();
        } else {
            $tsItem = false;
        }

        if (method_exists($basket, 'getTrustedShopProtectionCost') && $basket->getTrustedShopProtectionCost()) {
            $tsProtectionCosts = $basket->getTrustedShopProtectionCost()->getPrice();
            $tsProtectionNettoPrice = $basket->getTrustedShopProtectionCost()->getNettoPrice();
            $tsProtectionVatValue = $basket->getTrustedShopProtectionCost()->getVatValue();
        } elseif (method_exists($basket, 'getTsProtectionCosts') && $basket->getTsProtectionCosts()) {
            $tsProtectionCosts = $basket->getTsProtectionCosts();
            if ($basket->getTsProtectionNet() > 0) {
                $tsProtectionNettoPrice = $basket->getTsProtectionNet();
                $tsProtectionVatValue = $basket->getTsProtectionVat();
            } else {
                $tsProtectionNettoPrice = $tsProtectionCosts;
                $tsProtectionVatValue = 0;
            }
        } else {
            $tsProtectionCosts = 0;
        }

        if ($tsProtectionCosts > 0) {
            $item = $items->addChild('item', 'TS Protection Cost');
            $item->addAttribute('article-number', 'oxtsprotection');
            $item->addAttribute('quantity', 1);
            $item->addAttribute('unit-price', number_format($tsProtectionNettoPrice, 2, ".", ""));
            $item->addAttribute('total-price', number_format($tsProtectionNettoPrice, 2, ".", ""));
            $item->addAttribute('tax', number_format($tsProtectionVatValue, 2, ".", ""));
        }

        if (count($basket->getVouchers())) {
            foreach ($basket->getVouchers() as $voucher) {
                $item = $items->addCDataChild('item', $voucher->sVoucherNr, $this->_utfMode);
                $item->addAttribute('article-number', $voucher->sVoucherNr);
                $item->addAttribute('quantity', 1);
                $item->addAttribute('unit-price', "" . number_format($voucher->dVoucherdiscount, 2, ".", ""));
                $item->addAttribute('total-price', "" . number_format($voucher->dVoucherdiscount, 2, ".", ""));
                $item->addAttribute('tax', number_format("0", 2, ".", ""));
            }
        }

        if ($basket->getTotalDiscount() && $basket->getTotalDiscount()->getBruttoPrice() > 0) {
            $item = $items->addChild('item', "Discount");
            $item->addAttribute('article-number', "Discount");
            $item->addAttribute('quantity', 1);
            $item->addAttribute('unit-price', "-" . number_format($basket->getTotalDiscount()->getNettoPrice(), 2, ".", ""));
            $item->addAttribute('total-price', "-" . number_format($basket->getTotalDiscount()->getNettoPrice(), 2, ".", ""));
            $item->addAttribute('tax', number_format("0", 2, ".", ""));
        }
    }

    /**
     * Adds payment method specific information to request XML. Differentiates between Rate (installment) and
     * Rechnung (invoice).
     *
     * @param SimpleXMLExtended $content
     * @param string $paymentType
     */
    private function _setRatepayContentPayment($content)
    {
        $payment = $content->addChild('payment');

        $payment->addAttribute('currency', 'EUR');
        $payment->addAttribute('method', $this->_getPaymentMethod());
        $payment->addChild('amount', number_format($this->_getDataProvider()->getPaymentAmount(), 2, ".", ""));

        return $payment;
    }

    /**
     * Add installment information to request XML.
     *
     * @param SimpleXMLExtended $installment
     */
    private function _setRatepayContentPaymentInstallment($installment)
    {
        $installment->addChild('installment-number', number_format($this->getSession()->getVariable('pi_ratepay_rate_number_of_rates'), 0));
        $installment->addChild('installment-amount', number_format($this->getSession()->getVariable('pi_ratepay_rate_rate'), 2, ".", ""));
        $installment->addChild('last-installment-amount', number_format($this->getSession()->getVariable('pi_ratepay_rate_last_rate'), 2, ".", ""));
        $installment->addChild('interest-rate', number_format($this->getSession()->getVariable('pi_ratepay_rate_interest_rate'), 2, ".", ""));
        $installment->addChild('payment-firstday', $this->getSession()->getVariable('pi_ratepay_rate_payment_firstday'));
    }

    /**
     * Helper Method which removes special characters from strings.
     *
     * @uses function _removeSpecialChar
     * @param string $str
     * @return string
     */
    private function _removeSpecialChars($str)
    {
        $str = html_entity_decode($str);

        $search = array("–", "´", "‹", "›", "‘", "’", "‚", "“", "”", "„", "‟", "•", "‒", "―", "—", "™", "¼", "½", "¾");
        $replace = array("-", "'", "<", ">", "'", "'", ",", '"', '"', '"', '"', "-", "-", "-", "-", "TM", "1/4", "1/2", "3/4");
        return str_replace($search, $replace, $str);
    }

    /**
     * Get server address. (example: http://localhost/eshop/
     *
     * @return string
     */
    private function _getRatepaySystemID()
    {
        $systemId = $_SERVER['SERVER_ADDR'];
        return $systemId;
    }

    /**
     * Get customers IP Address
     *
     * @return string
     */
    private function _getRatepayCustomerIpAddress()
    {
        $customerIp = '';

        if (isset($_SERVER['HTTP_CLIENT_IP']) && !empty($_SERVER['HTTP_CLIENT_IP'])) {
            $customerIp = $_SERVER['HTTP_CLIENT_IP'];
        } else if (isset($_SERVER['HTTP_X_FORWARDED_FOR']) && !empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $customerIp = $_SERVER['HTTP_X_FORWARDED_FOR'];
        } else {
            $customerIp = $_SERVER['REMOTE_ADDR'];
        }

        return $customerIp;
    }

    /**
     * Get RatePAY XML-Service
     * @return pi_ratepay_xmlService
     */
    private function _getXmlService()
    {
        return $this->_xmlService;
    }

    /**
     * Get payment type as registered in oxid.
     * @return string pi_ratepay_rechnung or pi_ratepay_rate
     */
    private function _getPaymentType()
    {
        return $this->_paymentType;
    }

    /**
     * Get payment method, invoice or installment.
     * @create convenience method for all classes
     * @return string
     */
    private function _getPaymentMethod()
    {
        return pi_ratepay_util_utilities::getPaymentMethod($this->_getPaymentType());
    }

    /**
     * Get current country
     * @return string DE or AT (or CH)
     */
    private function _getCountry()
    {
        return $this->_country;
    }
    /**
     * Get Profile Id
     * @return string
     */
    private function _getProfileId()
    {
        return $this->_profileId;
    }
    /**
     * Get Security Code
     * @return string
     */
    private function _getSecurityCode()
    {
        return $this->_securityCode;
    }

    /**
     * Get data provider for request.
     * @return pi_ratepay_requestAbstract
     */
    private function _getDataProvider()
    {
        return $this->_dataProvider;
    }

    private function _isRateElv()
    {
        $isRateElv = false;
        $settings = oxNew('pi_ratepay_settings');
        $settings->loadByType($this->_getPaymentMethod('pi_ratepay_rate'));

        if ($this->getSession()->getVariable('pi_rp_rate_pay_method') === 'pi_ratepay_rate_radio_elv'
            && $settings->pi_ratepay_settings__activate_elv->rawValue == 1
        ) {
            $isRateElv = true;

            $bankDataSessionKeys = array(
                $this->_getPaymentType() . '_bank_owner',
                $this->_getPaymentType() . '_bank_name',
                $this->_getPaymentType() . '_bank_account_number',
                $this->_getPaymentType() . '_bank_code',
                $this->_getPaymentType() . '_bank_iban',
                $this->_getPaymentType() . '_bank_bic'
            );

            foreach ($bankDataSessionKeys as $key) {
                if (!$this->getSession()->hasVariable($key)) {
                    $isRateElv = false;
                    break;
                }
            }
        }

        return $isRateElv;
    }

}