<?php

namespace Drupal\commerce_concordpay\PluginForm\Redirect;

use Drupal\commerce_payment\PluginForm\PaymentOffsiteForm as BasePaymentOffsiteForm;
use Drupal\Core\Form\FormStateInterface;
use Drupal\commerce_concordpay\api\ConcordpayAPI;
use Drupal\Core\Url;

/**
 * Generates ConcordPay payment form.
 *
 * Class ConcordpayForm.
 *
 * @package Drupal\commerce_concordpay\PluginForm\Redirect
 */
class ConcordpayForm extends BasePaymentOffsiteForm {

  /**
   * {@inheritdoc}
   *
   * @throws \exception
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {

    $form = parent::buildConfigurationForm($form, $form_state);
    $api = new ConcordpayAPI();

    /** @var \Drupal\commerce_payment\Entity\PaymentInterface $payment */
    $payment = $this->entity;
    if ($payment->getPaymentGateway() === NULL) {
      throw new \exception(
        t("Error: for payment @id not found Payment Gateway!", ["@id" => $payment->id()])
      );
    }
    $payment_gateway_plugin = $payment->getPaymentGateway()->getPlugin();

    // Get ConcordPay module config data.
    $configuration = $payment_gateway_plugin->getConfiguration();
    $redirect_method = 'post';

    $option = [];
    $amount = $payment->getAmount();
    if ($amount === NULL) {
      throw new \exception(
        t('Error receiving the payment amount!')
      );
    }

    $client_first_name = $payment->getOrder()->getBillingProfile()->get('address')->getValue()[0]['given_name'] ?? '';
    $client_last_name  = $payment->getOrder()->getBillingProfile()->get('address')->getValue()[0]['family_name'] ?? '';

    $option['merchant_id']  = $configuration['merchant_id'];
    $option['currency_iso'] = $amount->getCurrencyCode();
    $option['amount']       = number_format($amount->getNumber(), 2, '.', '');
    $option['operation']    = 'Purchase';
    $option['description']  = t('Payment by card on the site') . ' ' . htmlspecialchars($_SERVER["HTTP_HOST"]) .
        ", $client_first_name $client_last_name.";
    $option['add_params']   = [];
    $option['order_id']     = $payment->getOrderId();
    $option['signature']    = $api->getRequestSignature($option);
    $option['approve_url']  = Url::FromRoute(
      'commerce_payment.checkout.return',
      ['step' => 'payment', 'commerce_order' => $payment->getOrderId()],
      ['absolute' => TRUE]
    )->toString();
    $option['decline_url']  = Url::FromRoute(
      'commerce_payment.checkout.cancel',
      ['step' => 'payment', 'commerce_order' => $payment->getOrderId()],
      ['absolute' => TRUE]
    )->toString();
    $option['cancel_url']   = Url::FromRoute(
      'commerce_payment.checkout.cancel',
      ['step' => 'payment', 'commerce_order' => $payment->getOrderId()],
      ['absolute' => TRUE]
    )->toString();
    $option['callback_url'] = $payment_gateway_plugin->getNotifyUrl()->toString();
    // Statistics.
    $option['client_last_name']  = $client_last_name;
    $option['client_first_name'] = $client_first_name;
    $option['email']             = $payment->getOrder()->getEmail() ?? '';
    $option['phone']             = '';

    $form = $this->buildRedirectForm($form, $form_state, $api::URL, $option, $redirect_method);

    return $form;
  }

}
