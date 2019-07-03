<?php

namespace Drupal\commerce_concordpay\PluginForm\Redirect;

use Drupal\commerce_payment\PluginForm\PaymentOffsiteForm as BasePaymentOffsiteForm;
use Drupal\Core\Form\FormStateInterface;
use Drupal\commerce_concordpay\api\ConcordpayAPI;
use Drupal\Core\Url;

class ConcordpayForm extends BasePaymentOffsiteForm {

  /**
   * {@inheritdoc}
   */
    public function buildConfigurationForm(array $form, FormStateInterface $form_state) {

        $form = parent::buildConfigurationForm($form, $form_state);
        $api = new ConcordpayAPI();

        /** @var \Drupal\commerce_payment\Entity\PaymentInterface $payment */
        $payment = $this->entity;
        $payment_gateway_plugin = $payment->getPaymentGateway()->getPlugin();

        //получаем данные конфигурации модуля Concord Pay
        $configuration = $payment_gateway_plugin->getConfiguration();
        $redirect_method = 'post';

        $option = array();
        $option['merchant_id'] = $configuration['merchant_id'];
        $option['currency_iso'] = $payment->getAmount()->getCurrencyCode();
        $option['amount'] = number_format($payment->getAmount()->getNumber(), 2, '.', '');
        $option['operation'] = 'Purchase';
        $option['description'] = 'Оплата картой VISA или Mastercard на сайте '.$_SERVER["HTTP_HOST"];
        $option['add_params'] = ['merchantAccount', 'orderReference', 'transactionId', 'transactionStatus', 'reason'];
        $option['order_id'] = $payment->getOrderId();
        $option['signature'] = $api->getRequestSignature($option) ;
        $option['approve_url'] =  Url::FromRoute('commerce_payment.checkout.return',
            [
                'commerce_order' => $payment->getOrderId(),
                'step' => 'payment',
            ], ['absolute' => TRUE])->toString();

        $option['callback_url'] = $payment_gateway_plugin->getNotifyUrl()->toString();
        $option['decline_url'] = Url::FromRoute('commerce_payment.checkout.cancel',
            [   'step' => 'payment',
                'commerce_order' => $payment->getOrderId(),
            ], ['absolute' => TRUE])->toString();


        $option['cancel_url'] = Url::FromRoute('commerce_payment.checkout.cancel',
            [   'step' => 'payment',
                'commerce_order' => $payment->getOrderId(),
            ], ['absolute' => TRUE])->toString();

        $form = $this->buildRedirectForm($form, $form_state, $api::URL, $option, $redirect_method);

        return $form;
    }

}
