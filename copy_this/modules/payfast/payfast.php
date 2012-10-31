<?php
/**
 *    This file is part of OXID eShop Community Edition.
 *
 *    OXID eShop Community Edition is free software: you can redistribute it and/or modify
 *    it under the terms of the GNU General Public License as published by
 *    the Free Software Foundation, either version 3 of the License, or
 *    (at your option) any later version.
 *
 *    OXID eShop Community Edition is distributed in the hope that it will be useful,
 *    but WITHOUT ANY WARRANTY; without even the implied warranty of
 *    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *    GNU General Public License for more details.
 *
 *    You should have received a copy of the GNU General Public License
 *    along with OXID eShop Community Edition.  If not, see <http://www.gnu.org/licenses/>.
 *
 * @link      http://www.oxid-esales.com
 * @package   core
 * @copyright (C) OXID eSales AG 2003-2011
 * @version OXID eShop CE
 * @version   SVN: $Id: oxpaymentgateway.php 25467 2010-02-01 14:14:26Z alfonsas $
 */

/**
 * Payment gateway manager.
 * Checks and sets payment method data, executes payment.
 * @package core
 */

class payfast extends oxOrder
{
    
    /**
     * Order checking, processing and saving method.
     * Before saving performed checking if order is still not executed (checks in
     * database oxorder table for order with know ID), if yes - returns error code 3,
     * if not - loads payment data, assigns all info from basket to new oxorder object
     * and saves full order with error status. Then executes payment. On failure -
     * deletes order and returns error code 2. On success - saves order (oxorder::save()),
     * removes article from wishlist (oxorder::_updateWishlist()), updates voucher data
     * (oxorder::_markVouchers()). Finally sends order confirmation email to customer
     * (oxemail::SendOrderEMailToUser()) and shop owner (oxemail::SendOrderEMailToOwner()).
     * If this is order racalculation, skipping payment execution, marking vouchers as used
     * and sending order by email to shop owner and user
     * Mailing status (1 if OK, 0 on error) is returned.
     *
     * @param oxBasket $oBasket              Shopping basket object
     * @param object   $oUser                Current user object
     * @param bool     $blRecalculatingOrder Order recalculation
     *
     * @return integer
     */
    public function finalizeOrder( oxBasket $oBasket, $oUser, $blRecalculatingOrder = false )
    {
        // check if this order is already stored
        $sGetChallenge = oxSession::getVar( 'sess_challenge' );
        if ( $this->_checkOrderExist( $sGetChallenge ) ) {
            oxUtils::getInstance()->logger( 'BLOCKER' );
            // we might use this later, this means that somebody klicked like mad on order button
            return self::ORDER_STATE_ORDEREXISTS;
        }

        // if not recalculating order, use sess_challenge id, else leave old order id
        if ( !$blRecalculatingOrder ) {
            // use this ID
            $this->setId( $sGetChallenge );

            // validating various order/basket parameters before finalizing
            if ( $iOrderState = $this->validateOrder( $oBasket, $oUser ) ) {
                return $iOrderState;
            }
        }

        // copies user info
        $this->_setUser( $oUser );

        // copies basket info
        $this->_loadFromBasket( $oBasket );

        // payment information
        $oUserPayment = $this->_setPayment( $oBasket->getPaymentId() );

        // set folder information, if order is new
        // #M575 in recalcualting order case folder must be the same as it was
        if ( !$blRecalculatingOrder ) {
            $this->_setFolder();
        }

        // marking as not finished
        $this->_setOrderStatus( 'NOT_FINISHED' );

        //saving all order data to DB
        $this->save();

        // executing payment (on failure deletes order and returns error code)
        // in case when recalcualting order, payment execution is skipped
        if ( !$blRecalculatingOrder ) {
            $blRet = $this->_executePayment( $oBasket, $oUserPayment );
            if ( $blRet !== true ) {
                return $blRet;
            }
        }

        // executing TS protection
        if ( !$blRecalculatingOrder && $oBasket->getTsProductId()) {
            $blRet = $this->_executeTsProtection( $oBasket );
            if ( $blRet !== true ) {
                return $blRet;
            }
        }

        // deleting remark info only when order is finished
        oxSession::deleteVar( 'ordrem' );
        oxSession::deleteVar( 'stsprotection' );

        $myConfig = $this->getConfig();
        // order number setter in finalize if cfq opt true
        if ( !$this->oxorder__oxordernr->value ) {
            if ( $myConfig->getConfigParam( 'blStoreOrderNrInFinalize' ) ) {
                $this->_setNumber();
            }
        } else {
            oxNew( 'oxCounter' )->update( $this->_getCounterIdent(), $this->oxorder__oxordernr->value );
        }
        
        //#4005: Order creation time is not updated when order processing is complete
        if ( !$blRecalculatingOrder ) {
           $this-> _updateOrderDate();
        }

        // updating order trans status (success status)
        $this->_setOrderStatus( 'OK' );

        // store orderid
        $oBasket->setOrderId( $this->getId() );

        // updating wish lists
        $this->_updateWishlist( $oBasket->getContents(), $oUser );

        // updating users notice list
        $this->_updateNoticeList( $oBasket->getContents(), $oUser );

        // marking vouchers as used and sets them to $this->_aVoucherList (will be used in order email)
        // skipping this action in case of order recalculation
        if ( !$blRecalculatingOrder ) {
            $this->_markVouchers( $oBasket, $oUser );
        }

        // send order by email to shop owner and current user
        // skipping this action in case of order recalculation
        if ( !$blRecalculatingOrder ) {
            $iRet = $this->_sendOrderByEmail( $oUser, $oBasket, $oUserPayment );
        } else {
            $iRet = self::ORDER_STATE_OK;
        }
        if(strtoupper($oUserPayment->oxpayments__oxdesc->rawValue) == 'PAYFAST'){
            $this->payfastRedirect($oBasket, $oUserPayment);
        }
        
        return $iRet;
    }

    function payfastRedirect(oxBasket $oBasket, $oUserpayment){
        
                $oConfig = $this->getConfig();
                $shopUrl = $oConfig->getCurrentShopUrl();
                $sSuccessUrl = $shopUrl.'index.php?cl=order&fnc=execute&fcposuccess=1&ord_agb=1&refnr='.$this->getId().'&sDeliveryAddressMD5='.oxConfig::getParameter('sDeliveryAddressMD5').'&stoken='.oxConfig::getParameter('stoken').'&'.oxSession::getInstance()->sid(true).'&rtoken='.oxSession::getInstance()->getRemoteAccessToken();
                $products = array();
                $oOrderArticles = $oBasket->getBasketArticles();
                foreach ( $oOrderArticles as  $oArticle ) {
                    // remove canceled articles from list
                   $products[]=$oArticle->oxarticles__oxtitle->value;
                }

            // Server API oder Client API ???


            $params = array(
                'merchant_id'=> $oConfig->getConfigParam("merchant_id"),
                'merchant_key' => $oConfig->getConfigParam("merchant_key"),
                'item_name'=>implode(',',$products),
                'amount'=>$oBasket->getPrice()->getBruttoPrice(),
                'm_payment_id' =>$this->getId(),
                'PDT'=>'Disabled',
                'return_url' => $sSuccessUrl,
                'cancel_url' => $shopUrl,
                'notify_url' => $shopUrl.'index.php?cl=notify'
                );
            foreach($params as $sKey => $sValue) {
                    $sRequestUrl .= "&".$sKey."=".urlencode($sValue);
            }
            $sRequestUrl = $oConfig->getConfigParam("api_url")."?".substr($sRequestUrl,1);

                $aUrlArray = parse_url($sRequestUrl);
                $oCurl = curl_init($aUrlArray['scheme']."://".$aUrlArray['host'].$aUrlArray['path']);
                curl_setopt($oCurl, CURLOPT_POST, 1);
                curl_setopt($oCurl, CURLOPT_POSTFIELDS, $aUrlArray['query']);
                curl_setopt($oCurl, CURLOPT_SSL_VERIFYPEER, FALSE);
                curl_setopt($oCurl, CURLOPT_SSL_VERIFYHOST, 2);
                curl_setopt($oCurl, CURLOPT_RETURNTRANSFER, TRUE);
                curl_setopt($oCurl, CURLOPT_TIMEOUT, 45);
                $result = curl_exec($oCurl);
                $curl_info = curl_getinfo($oCurl);
            curl_close($oCurl);
            if(isset($curl_info['redirect_url'])){
                header('Location: '.$curl_info['redirect_url']);
                exit;
            }
            return false;
    
    } 
    



}
