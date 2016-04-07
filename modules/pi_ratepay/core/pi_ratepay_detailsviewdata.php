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
 * Helper Class to generate RatePAY order data.
 */
class pi_ratepay_DetailsViewData
{
    /**
     * oxid of order
     * @var string
     */
    private $_orderId;

    /**
     * Name of order details table
     * @var string
     */
    private $pi_ratepay_order_details = 'pi_ratepay_order_details';

    /**
     * Class constructor
     * @param string $orderId oxid of order
     */
    public function __construct($orderId)
    {
        $this->_orderId = $orderId;
    }

    /**
     * Gets all articles with additional informations
     *
     * @return array
     */
    public function getPreparedOrderArticles()
    {
        $orderId = $this->_orderId;

        $articleList = array();

        # Get order articles
        $articlesSql = "SELECT
              oo.oxcurrency,
              oa.oxid,
              oa.oxartid,
              oa.oxartnum,
              oa.oxvat,
              oa.oxbprice,
              oa.oxnprice,
              oa.oxtitle,
              oa.oxbrutprice,
              oa.oxamount,
              prrod.ordered,
              prrod.cancelled,
              prrod.returned,
              prrod.shipped,
              if(oa.OXSELVARIANT != '',concat(oa.oxtitle,', ',oa.OXSELVARIANT),oa.oxtitle) as title
            FROM
              `oxorder` oo,
              `oxorderarticles` oa,
              $this->pi_ratepay_order_details prrod
            WHERE
              prrod.order_number = '$orderId'
              AND prrod.order_number = oa.oxorderid
              AND oa.oxartid = prrod.article_number
              AND oo.oxid = prrod.order_number";
        $articlesResult = mysql_query($articlesSql);
        $i = 0;

        while ($articlesValues = mysql_fetch_object($articlesResult)) {
            $articleList[$i]['oxid'] = $articlesValues->oxid;
            $articleList[$i]['artid'] = $articlesValues->oxartid;
            $articleList[$i]['arthash'] = md5($articlesValues->oxartid);
            $articleList[$i]['artnum'] = $articlesValues->oxartnum;
            $articleList[$i]['title'] = $articlesValues->title;
            $articleList[$i]['oxtitle'] = $articlesValues->oxtitle;
            $articleList[$i]['vat'] = $this->_getFormattedNumber($articlesValues->oxvat, 2, ',');
            $articleList[$i]['unitprice'] = $this->_getFormattedNumber((float)$articlesValues->oxbprice, 2, ',');
            $articleList[$i]['unitPriceNetto'] = $this->_getFormattedNumber((float)$articlesValues->oxnprice, 2, ',');
            $articleList[$i]['amount'] = $articlesValues->ordered - $articlesValues->shipped - $articlesValues->cancelled;
            $articleList[$i]['ordered'] = $articlesValues->ordered;
            $articleList[$i]['shipped'] = $articlesValues->shipped;
            $articleList[$i]['returned'] = $articlesValues->returned;
            $articleList[$i]['cancelled'] = $articlesValues->cancelled;
            $articleList[$i]['currency'] = $articlesValues->oxcurrency;

            if (($articlesValues->ordered - $articlesValues->returned - $articlesValues->cancelled) > 0) {
                $articleList[$i]['totalprice'] = $this->_getFormattedNumber((float) $articlesValues->oxbrutprice, 2, ',');
            } else {
                $articleList[$i]['totalprice'] = 0;
            }

            $i++;
        }

        $orderSql = "SELECT * from `oxorder` where oxid='$orderId'";

        $orderResult = mysql_query($orderSql);
        $orderValues = mysql_fetch_object($orderResult);


        $rpOrderDetailsWrappingSql = "SELECT oxorder.oxcurrency, prrod.* from $this->pi_ratepay_order_details prrod, oxorder where prrod.order_number='$orderId' and prrod.article_number='oxwrapping' and oxorder.oxid = prrod.order_number ";
        $rpOrderDetailsWrappingResult = mysql_query($rpOrderDetailsWrappingSql);
        $rpOrderDetailsWrappingValues = mysql_fetch_object($rpOrderDetailsWrappingResult);

        if ($rpOrderDetailsWrappingValues->PRICE > 0) {

            $articleList[$i]['oxid']      = "";
            $articleList[$i]['artid']     = "oxwrapping";
            $articleList[$i]['arthash']   = md5("oxwrapping");
            $articleList[$i]['artnum']    = "oxwrapping";
            $articleList[$i]['title']     = "Wrapping Cost";
            $articleList[$i]['oxtitle']   = "Wrapping Cost";
            $articleList[$i]['vat']       = $this->_getFormattedNumber((float) $rpOrderDetailsWrappingValues->VAT, 2, ',');
            $articleList[$i]['unitprice'] = $this->_getFormattedNumber((float) $rpOrderDetailsWrappingValues->PRICE, 2, ',');
            $articleList[$i]['amount']    = 1 - $rpOrderDetailsWrappingValues->SHIPPED - $rpOrderDetailsWrappingValues->CANCELLED;
            $articleList[$i]['ordered']   = $rpOrderDetailsWrappingValues->ORDERED;
            $articleList[$i]['shipped']   = $rpOrderDetailsWrappingValues->SHIPPED;
            $articleList[$i]['returned']  = $rpOrderDetailsWrappingValues->RETURNED;
            $articleList[$i]['cancelled'] = $rpOrderDetailsWrappingValues->CANCELLED;
            $articleList[$i]['currency'] = $rpOrderDetailsWrappingValues->oxcurrency;

            if (($rpOrderDetailsWrappingValues->ORDERED - $rpOrderDetailsWrappingValues->RETURNED - $rpOrderDetailsWrappingValues->CANCELLED) > 0) {
                $articleList[$i]['totalprice'] = $this->_getFormattedNumber((float) $rpOrderDetailsWrappingValues->PRICE, 2, ',');
            } else {
                $articleList[$i]['totalprice'] = 0;
            }

            $i++;
        }

        $rpOrderDetailsGiftcardsSql = "SELECT oxorder.oxcurrency, prrod.* from $this->pi_ratepay_order_details prrod, oxorder where prrod.order_number='$orderId' and prrod.article_number='oxgiftcard' and oxorder.oxid = prrod.order_number ";
        $rpOrderDetailsGiftcardsResult = mysql_query($rpOrderDetailsGiftcardsSql);
        $rpOrderDetailsGiftcardsValues = mysql_fetch_object($rpOrderDetailsGiftcardsResult);

        if ($rpOrderDetailsGiftcardsValues->PRICE > 0) {

            $articleList[$i]['oxid'] = "";
            $articleList[$i]['artid'] = "oxgiftcard";
            $articleList[$i]['arthash'] = md5($orderValues->oxartid);
            $articleList[$i]['artnum'] = "oxgiftcard";
            $articleList[$i]['title'] = "Giftcard Cost";
            $articleList[$i]['oxtitle'] = "Giftcard Cost";
            $articleList[$i]['vat'] = $this->_getFormattedNumber((float) $rpOrderDetailsGiftcardsValues->VAT, 2, ',');
            $articleList[$i]['unitprice'] = $this->_getFormattedNumber((float) $rpOrderDetailsGiftcardsValues->PRICE, 2, ',');
            $articleList[$i]['amount'] = 1 - $rpOrderDetailsGiftcardsValues->SHIPPED - $rpOrderDetailsGiftcardsValues->CANCELLED;
            $articleList[$i]['ordered'] = $rpOrderDetailsGiftcardsValues->ORDERED;
            $articleList[$i]['shipped'] = $rpOrderDetailsGiftcardsValues->SHIPPED;
            $articleList[$i]['returned'] = $rpOrderDetailsGiftcardsValues->RETURNED;
            $articleList[$i]['cancelled'] = $rpOrderDetailsGiftcardsValues->CANCELLED;
            $articleList[$i]['currency'] = $rpOrderDetailsGiftcardsValues->oxcurrency;

            if (($rpOrderDetailsGiftcardsValues->ORDERED - $rpOrderDetailsGiftcardsValues->RETURNED - $rpOrderDetailsGiftcardsValues->CANCELLED) > 0) {
                $articleList[$i]['totalprice'] = $this->_getFormattedNumber((float) $rpOrderDetailsGiftcardsValues->PRICE, 2, ',');
            } else {
                $articleList[$i]['totalprice'] = 0;
            }

            $i++;
        }


        $rpOrderDetailsPaymentSql = "SELECT oxorder.oxcurrency, prrod.* from $this->pi_ratepay_order_details prrod, oxorder where prrod.order_number='$orderId' and prrod.article_number='oxpayment' and oxorder.oxid = prrod.order_number ";
        $rpOrderDetailsPaymentResult = mysql_query($rpOrderDetailsPaymentSql);
        $rpOrderDetailsPaymentValues = mysql_fetch_object($rpOrderDetailsPaymentResult);

        if ($rpOrderDetailsPaymentValues->PRICE > 0) {

            $articleList[$i]['oxid'] = "";
            $articleList[$i]['artid'] = "oxpayment";
            $articleList[$i]['arthash'] = md5($orderValues->oxartid);
            $articleList[$i]['artnum'] = "oxpayment";
            $articleList[$i]['title'] = "Payment Cost";
            $articleList[$i]['oxtitle'] = "Payment Cost";
            $articleList[$i]['vat'] = $this->_getFormattedNumber((float) $rpOrderDetailsPaymentValues->VAT, 2, ',');
            $articleList[$i]['unitprice'] = $this->_getFormattedNumber((float) $rpOrderDetailsPaymentValues->PRICE, 2, ',');
            $articleList[$i]['amount'] = 1 - $rpOrderDetailsPaymentValues->SHIPPED - $rpOrderDetailsPaymentValues->CANCELLED;
            $articleList[$i]['ordered'] = $rpOrderDetailsPaymentValues->ORDERED;
            $articleList[$i]['shipped'] = $rpOrderDetailsPaymentValues->SHIPPED;
            $articleList[$i]['returned'] = $rpOrderDetailsPaymentValues->RETURNED;
            $articleList[$i]['cancelled'] = $rpOrderDetailsPaymentValues->CANCELLED;
            $articleList[$i]['currency'] = $rpOrderDetailsPaymentValues->oxcurrency;

            if (($rpOrderDetailsPaymentValues->ORDERED - $rpOrderDetailsPaymentValues->RETURNED - $rpOrderDetailsPaymentValues->CANCELLED) > 0) {
                $articleList[$i]['totalprice'] = $this->_getFormattedNumber((float) $rpOrderDetailsPaymentValues->PRICE, 2, ',');
            } else {
                $articleList[$i]['totalprice'] = 0;
            }

            $i++;
        }

        $rpOrderDetailsDeliverySql = "SELECT oxorder.oxcurrency, prrod.* from $this->pi_ratepay_order_details prrod, oxorder where prrod.order_number='$orderId' and prrod.article_number='oxdelivery' and oxorder.oxid = prrod.order_number ";
        $rpOrderDetailsDeliveryResult = mysql_query($rpOrderDetailsDeliverySql);
        $rpOrderDetailsDeliveryValues = mysql_fetch_object($rpOrderDetailsDeliveryResult);

        if ($rpOrderDetailsDeliveryValues->PRICE > 0) {

            $articleList[$i]['oxid']      = "";
            $articleList[$i]['artid']     = "oxdelivery";
            $articleList[$i]['arthash']   = md5('oxdelivery');
            $articleList[$i]['artnum']    = "oxdelivery";
            $articleList[$i]['title']     = "Delivery Cost";
            $articleList[$i]['oxtitle']   = "Delivery Cost";
            $articleList[$i]['vat']       = $this->_getFormattedNumber((float) $rpOrderDetailsDeliveryValues->VAT, 2, ',');
            $articleList[$i]['unitprice'] = $this->_getFormattedNumber((float) $rpOrderDetailsDeliveryValues->PRICE, 2, ',');
            $articleList[$i]['amount']    = 1 - $rpOrderDetailsDeliveryValues->SHIPPED - $rpOrderDetailsDeliveryValues->CANCELLED;
            $articleList[$i]['ordered']   = $rpOrderDetailsDeliveryValues->ORDERED;
            $articleList[$i]['shipped']   = $rpOrderDetailsDeliveryValues->SHIPPED;
            $articleList[$i]['returned']  = $rpOrderDetailsDeliveryValues->RETURNED;
            $articleList[$i]['cancelled'] = $rpOrderDetailsDeliveryValues->CANCELLED;
            $articleList[$i]['currency'] = $rpOrderDetailsDeliveryValues->oxcurrency;

            if (($rpOrderDetailsDeliveryValues->ORDERED - $rpOrderDetailsDeliveryValues->RETURNED - $rpOrderDetailsDeliveryValues->CANCELLED) > 0) {
                $articleList[$i]['totalprice'] = $this->_getFormattedNumber((float) $rpOrderDetailsDeliveryValues->PRICE, 2, ',');
            } else {
                $articleList[$i]['totalprice'] = 0;
            }

            $i++;
        }


        $rpOrderDetailsProtectionSql = "SELECT oxorder.oxcurrency, prrod.* from $this->pi_ratepay_order_details prrod, oxorder where prrod.order_number='$orderId' and prrod.article_number='oxtsprotection' and oxorder.oxid = prrod.order_number ";
        $rpOrderDetailsProtectionResult = mysql_query($rpOrderDetailsProtectionSql);
        $rpOrderDetailsProtectionValues = mysql_fetch_object($rpOrderDetailsProtectionResult);

        if ($rpOrderDetailsProtectionValues->PRICE > 0) {

            $articleList[$i]['oxid'] = "";
            $articleList[$i]['artid'] = "oxtsprotection";
            $articleList[$i]['arthash'] = md5('oxtsprotection');
            $articleList[$i]['artnum'] = "oxtsprotection";
            $articleList[$i]['title'] = "TS Protection Cost";
            $articleList[$i]['oxtitle'] = "TS Protection Cost";
            $articleList[$i]['vat'] = $this->_getFormattedNumber((float) $rpOrderDetailsProtectionValues->VAT, 2, ',');
            $articleList[$i]['unitprice'] = $this->_getFormattedNumber((float) $rpOrderDetailsProtectionValues->PRICE, 2, ',');
            $articleList[$i]['amount'] = 1 - $rpOrderDetailsProtectionValues->SHIPPED - $rpOrderDetailsProtectionValues->CANCELLED;
            $articleList[$i]['ordered'] = $rpOrderDetailsProtectionValues->ORDERED;
            $articleList[$i]['shipped'] = $rpOrderDetailsProtectionValues->SHIPPED;
            $articleList[$i]['returned'] = $rpOrderDetailsProtectionValues->RETURNED;
            $articleList[$i]['cancelled'] = $rpOrderDetailsProtectionValues->CANCELLED;
            $articleList[$i]['currency'] = $rpOrderDetailsProtectionValues->oxcurrency;

            if (($rpOrderDetailsProtectionValues->ORDERED - $rpOrderDetailsProtectionValues->RETURNED - $rpOrderDetailsProtectionValues->CANCELLED) > 0) {
                $articleList[$i]['totalprice'] = $this->_getFormattedNumber((float) $rpOrderDetailsProtectionValues->PRICE, 2, ',');
            } else {
                $articleList[$i]['totalprice'] = 0;
            }

            $i++;
        }

        //$rpOrderDetailsDiscountsSql = "SELECT * from `$this->pi_ratepay_order_details` where order_number='$orderId' and article_number='discount'";

        $rpOrderDetailsDiscountsSql = "SELECT
          oo.oxcurrency,
          od.oxid AS ARTID,
          od.oxtitle AS TITLE,
          prrod.price AS PRICE,
          prrod.ordered AS ORDERED,
          prrod.cancelled AS CANCELLED,
          prrod.returned AS RETURNED,
          prrod.shipped AS SHIPPED
		FROM
		  `oxorder` oo,
		  `oxdiscount` od,
		  " . $this->pi_ratepay_order_details . " prrod
		WHERE
		  prrod.order_number = '" . $orderId . "'
		  AND prrod.article_number = od.oxid
          AND oo.oxid = prrod.order_number";
        $rpOrderDetailsDiscountsResult = mysql_query($rpOrderDetailsDiscountsSql);
        $rpOrderDetailsDiscountsValues = mysql_fetch_object($rpOrderDetailsDiscountsResult);

        if ($rpOrderDetailsDiscountsValues->PRICE != 0) {

            $articleList[$i]['oxid'] = "";
            $articleList[$i]['artid'] = $rpOrderDetailsDiscountsValues->ARTID;
            $articleList[$i]['arthash'] = md5($orderValues->oxartid);
            $articleList[$i]['artnum'] = "discount";
            $articleList[$i]['title'] = $rpOrderDetailsDiscountsValues->TITLE;
            $articleList[$i]['oxtitle'] = $rpOrderDetailsDiscountsValues->TITLE;
            $articleList[$i]['vat'] = "0";
            $articleList[$i]['unitprice'] = $this->_getFormattedNumber((float) $rpOrderDetailsDiscountsValues->PRICE, 2, ',');
            $articleList[$i]['unitPriceNetto'] = $this->_getFormattedNumber((float) $rpOrderDetailsDiscountsValues->PRICE, 2, ',');
            $articleList[$i]['amount'] = 1 - $rpOrderDetailsDiscountsValues->SHIPPED - $rpOrderDetailsDiscountsValues->CANCELLED;
            $articleList[$i]['ordered'] = $rpOrderDetailsDiscountsValues->ORDERED;
            $articleList[$i]['shipped'] = $rpOrderDetailsDiscountsValues->SHIPPED;
            $articleList[$i]['returned'] = $rpOrderDetailsDiscountsValues->RETURNED;
            $articleList[$i]['cancelled'] = $rpOrderDetailsDiscountsValues->CANCELLED;
            $articleList[$i]['currency'] = $rpOrderDetailsDiscountsValues->oxcurrency;

            if (($rpOrderDetailsDiscountsValues->ORDERED - $rpOrderDetailsDiscountsValues->RETURNED - $rpOrderDetailsDiscountsValues->CANCELLED) > 0) {
                $articleList[$i]['totalprice'] = $this->_getFormattedNumber($rpOrderDetailsDiscountsValues->PRICE, 2, ',');
            } else {
                $articleList[$i]['totalprice'] = 0;
            }

            $i++;
        }

        $vouchersSql = "SELECT
          oo.oxcurrency,
          ov.oxdiscount AS price,
          prrod.article_number AS artnr,
          ov.oxvouchernr AS title,
          prrod.ordered, prrod.cancelled,
          prrod.returned,
          prrod.shipped,
          ovs.OXSERIENR as seriesTitle,
          ovs.OXSERIEDESCRIPTION as seriesDescription
		FROM
		  `oxorder` oo,
		  `oxvouchers` ov,
		  " . $this->pi_ratepay_order_details . " prrod,
		  oxvoucherseries ovs
		WHERE
		  prrod.order_number = '" . $orderId . "'
		  AND ov.oxorderid = prrod.order_number
		  AND prrod.article_number = ov.oxid
          AND ovs.oxid = ov.OXVOUCHERSERIEID
          AND oo.oxid = prrod.order_number";

        $vouchersResult = mysql_query($vouchersSql);

        while ($vouchersValues = mysql_fetch_object($vouchersResult)) {
            $articleList[$i]['oxid'] = "";
            $articleList[$i]['artid'] = $vouchersValues->artnr;
            $articleList[$i]['arthash'] = md5($vouchersValues->artnr);
            $articleList[$i]['artnum'] = $vouchersValues->title;
            $articleList[$i]['title'] = $vouchersValues->seriesTitle;
            $articleList[$i]['oxtitle'] = $vouchersValues->seriesTitle;
            $articleList[$i]['vat'] = "0";
            $articleList[$i]['unitprice'] = "-" . $this->_getFormattedNumber((float) $vouchersValues->price, 2, ',');
            $articleList[$i]['unitPriceNetto'] = "-" . $this->_getFormattedNumber((float) $vouchersValues->price, 2, ',');
            $articleList[$i]['amount'] = 1 - $vouchersValues->shipped - $vouchersValues->cancelled;
            $articleList[$i]['ordered'] = $vouchersValues->ordered;
            $articleList[$i]['shipped'] = $vouchersValues->shipped;
            $articleList[$i]['returned'] = $vouchersValues->returned;
            $articleList[$i]['cancelled'] = $vouchersValues->cancelled;
            $articleList[$i]['currency'] = $vouchersValues->oxcurrency;

            if (($vouchersValues->ordered - $vouchersValues->returned - $vouchersValues->cancelled) > 0) {
                $articleList[$i]['totalprice'] = $this->_getFormattedNumber((float) $vouchersValues->price * -1, 2, ',');
            } else {
                $articleList[$i]['totalprice'] = 0;
            }

            $i++;
        }

        return $articleList;
    }
    private function _getFormattedNumber($str, $decimal = 2, $dec_point = ".", $thousands_sep = "")
    {
        return pi_ratepay_util_utilities::getFormattedNumber($str, $decimal, $dec_point, $thousands_sep);
    }
}
