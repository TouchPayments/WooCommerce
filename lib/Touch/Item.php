<?php
require_once __DIR__ . '/Object.php';

class Touch_Item extends Touch_Object {

    /**
     * @var String
     */
    public $sku;

    /**
     * @var Float
     */
    public $price;

    /**
     * @var String
     */
    public $description;

    /**
     * @var int
     */
    public $quantity;

    /**
     * @var string
     */
    public $image;


}
