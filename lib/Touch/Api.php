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
        $response = $this->_touchClient->setOrderStatusShipped($refNr);
        return $response;
    }

    /**
     *
     * @param Touch_Order $order
     * @return type
     */
    public function generateOrder(Touch_Order $order)
    {
         $response = $this->_touchClient->generateOrder($order);
         return $response;
    }
    /**
     *
     * @param float $grandTotal
     * @return type
     */
    public function getFee($grandTotal)
    {
        $response = $this->_touchClient->getFee($grandTotal);
        return $response;
    }

    /**
     *
     * @return type
     */
    public function getMaximumCheckoutValue()
    {
        $response = $this->_touchClient->getMaximumCheckoutValue();
        return $response;
    }

    public function getOrderByTokenStatus($token){
        $response = $this->_touchClient->getOrderStatusFromToken($token);
        return $response;
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
        $response = $this->_touchClient->approveOrderByToken($token, $refNumber, $grandTotal);
        return $response;
    }


}
