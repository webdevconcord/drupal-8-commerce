<?php

namespace Drupal\commerce_concordpay\api;

/**
 * Class ConcordpayAPI. Utility class for interacting with ConcordPay API.
 *
 * @package Drupal\commerce_concordpay\api
 */
class ConcordpayAPI {
  const ORDER_APPROVED = 'Approved';
  const ORDER_DECLINED = 'Declined';
  const ORDER_PENDING  = 'Pending';

  const PAYMENT_STATE_COMPLETED = 'completed';
  const PAYMENT_STATE_REFUNDED  = 'refunded';

  const ORDER_STATE_COMPLETED  = 'Completed';
  const ORDER_STATE_CANCELED   = 'Canceled';
  const ORDER_STATE_VALIDATION = 'Validation';

  const SIGNATURE_SEPARATOR   = ';';
  const RESPONSE_TYPE_PAYMENT = 'payment';
  const RESPONSE_TYPE_REVERSE = 'reverse';

  const URL = "https://pay.concord.ua/api/";

  /**
   * Array keys for generate response signature.
   *
   * @var string[]
   */
  protected $keysForResponseSignature = [
    'merchantAccount',
    'orderReference',
    'amount',
    'currency',
  ];

  /**
   * Array keys for generate request signature.
   *
   * @var string[]
   */
  protected $keysForSignature = [
    'merchant_id',
    'order_id',
    'amount',
    'currency_iso',
    'description',
  ];

  /**
   * Generate signature for operation.
   *
   * @param array $option
   *   Request or response data.
   * @param array $keys
   *   Keys for signature.
   *
   * @return string
   *   Signature of operation
   */
  public function getSignature(array $option, array $keys): string {
    $hash = [];
    foreach ($keys as $dataKey) {
      if (!isset($option[$dataKey])) {
        continue;
      }
      if (is_array($option[$dataKey])) {
        foreach ($option[$dataKey] as $v) {
          $hash[] = $v;
        }
      }
      else {
        $hash[] = $option[$dataKey];
      }
    }

    $hash = implode(self::SIGNATURE_SEPARATOR, $hash);

    return hash_hmac('md5', $hash, $this->getApiKey()["secret_key"]);
  }

  /**
   * Generate request signature.
   *
   * @param array $options
   *   Request data.
   *
   * @return string
   *   Request signature
   */
  public function getRequestSignature(array $options): string {
    return $this->getSignature($options, $this->keysForSignature);
  }

  /**
   * Generate response signature.
   *
   * @param array $options
   *   Response data.
   *
   * @return string
   *   Response signature
   */
  public function getResponseSignature(array $options): string {
    return $this->getSignature($options, $this->keysForResponseSignature);
  }

  /**
   * Checking the validity of the payment.
   *
   * @param array $response
   *   Response data.
   *
   * @return bool|string
   *   Validation result.
   */
  public function isPaymentValid(array $response) {
    $sign = $this->getResponseSignature($response);
    if ($sign !== $response['merchantSignature']) {
      return t('An error has occurred during payment');
    }

    if ($response['transactionStatus'] === self::ORDER_APPROVED) {
      return TRUE;
    }

    return FALSE;
  }

  /**
   * Get saved config data.
   *
   * @return array
   *   Saved config data.
   */
  public function getApiKey(): array {
    $config = \Drupal::service('config.factory')
      ->getEditable('commerce_payment.commerce_payment_gateway.concordpay_payment')
      ->get();
    $settings["merchant_id"] = $config["configuration"]["merchant_id"];
    $settings["secret_key"] = $config["configuration"]["secret_key"];

    return $settings;
  }

}
