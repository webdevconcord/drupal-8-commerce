<?php

namespace Drupal\commerce_concordpay\api;

class ConcordpayAPI{

    const ORDER_APPROVED = 'Approved';

    const ORDER_PENDING = 'Pending';
 
    const SIGNATURE_SEPARATOR = ';';

    const URL = "https://pay.concord.ua/api/";

    protected $keysForResponseSignature = array(
        'merchantAccount',
        'orderReference',
        'amount',
        'currency',

    );

    /** @var array */
    protected $keysForSignature = array(
        'merchant_id',
        'order_id',
        'amount',
        'currency_iso',
        'description',

    );

    /**
     * @param $option
     * @param $keys
     * @return string
     */
    public function getSignature($option, $keys)
    {
        $hash = array();
        foreach ($keys as $dataKey) {
            if (!isset($option[$dataKey])) {
                continue;
            }
            if (is_array($option[$dataKey])) {
                foreach ($option[$dataKey] as $v) {
                    $hash[] = $v;
                }
            } else {
                $hash [] = $option[$dataKey];
            }
        }

        $hash = implode(self::SIGNATURE_SEPARATOR, $hash);
        return hash_hmac('md5', $hash, $this->getAPIKey()["secret_key"]);
    }


    /**
     * @param $options
     * @return string
     */
    public function getRequestSignature($options)
    {
        return $this->getSignature($options, $this->keysForSignature);
    }

    /**
     * @param $options
     * @return string
     */
    public function getResponseSignature($options)
    {
        return $this->getSignature($options, $this->keysForResponseSignature);
    }
 
    /**
     * @param $response
     * @return bool|string
     */
    public function isPaymentValid($response)
    {
 
        $sign = $this->getResponseSignature($response);
        if ($sign != $response['merchantSignature']) {
            return 'An error has occurred during payment';
        }

        if ($response['transactionStatus'] == self::ORDER_APPROVED) {
            return true;
        }

        return false;
    }
 
    public function getAPIKey()
    {
        $config = \Drupal::config('commerce_payment.commerce_payment_gateway.concord_pay')->get();
        $settings["merchant_id"] = $config["configuration"]["merchant_id"];
        $settings["secret_key"] = $config["configuration"]["secret_key"];

        return $settings;
    }


}

