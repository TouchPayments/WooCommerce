<?php
require_once __DIR__ . '/Client.php';

class Touch_Api
{
    /**
     * @var string
     */
    private $_redirectUrl;
    /**
     * @var Touch_Client
     */
    private $_touchClient;



    public function __construct($apiUrl, $apiKey, $redirectUrl)
    {
        $this->_redirectUrl = $redirectUrl;
        $this->_touchClient = new Touch_Client($apiKey, $apiUrl);

    }
    /**
     *
     * @param mixed $articleLines
     */
    public function setOrderItemsShipped($refNr)
    {
        return $this->_touchClient->setOrderStatusShipped($refNr);
    }

    /**
     *
     * @param Touch_Order $order
     * @return type
     */
    public function generateOrder(Touch_Order $order)
    {
         return $this->_touchClient->generateOrder($order);
    }
    /**
     *
     * @param float $grandTotal
     * @return type
     */
    public function getFee($grandTotal)
    {
        return $this->_touchClient->getFee($grandTotal);
    }

    /**
     *
     * @return type
     */
    public function getMaximumCheckoutValue()
    {
        return $this->_touchClient->getMaximumCheckoutValue();
    }

    /**
     *
     * @return type
     */
    public function isApiActive()
    {
        return $this->_touchClient->isApiActive();
    }

    public function getOrderByTokenStatus($token){
        return $this->_touchClient->getOrderStatusFromToken($token);
    }

    /**
     *
     * @param type $token
     * @param type $refNumber
     * @param type $grandTotal
     * @return type
     */
    public function approveOrder($token, $refNumber, $grandTotal)
    {
        return $this->_touchClient->approveOrderByToken($token, $refNumber, $grandTotal);
    }

    public function approveOrderBySmsCode($token, $refNumber, $grandTotal, $smsCode)
    {
        return $this->_touchClient->approveOrderBySmsCode($token, $refNumber, $grandTotal, $smsCode);
    }


}
