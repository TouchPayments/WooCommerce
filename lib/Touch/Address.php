<?php
require_once __DIR__ . '/Object.php';

class Touch_Address extends Touch_Object {

    const COUNTRY_AU = 'au';

     /**
     * @var String
     */
    public $firstName;
    /**
     * @var String
     */
    public $lastName;
    /**
     * @var String
     */
    public $middleName;
    /**
     * @var String
     */
    public $number;

    /**
     * @var String
     */
    public $addressOne;

    /**
     * @var String
     */
    public $addressTwo;

    /**
     * @var String
     */
    public $postcode;

    /**
     * @var String
     */
    public $suburb;

    /**
     * @var String
     */
    public $state;

    /**
     * @var String
     */
    public $country;

    public function __construct($country = self::COUNTRY_AU)
    {
        $this->country = $country;
    }

}
