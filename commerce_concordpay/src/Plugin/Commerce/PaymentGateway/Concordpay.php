<?php

namespace Drupal\commerce_concordpay\Plugin\Commerce\PaymentGateway;

use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_payment\Plugin\Commerce\PaymentGateway\OffsitePaymentGatewayBase;
use Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException;
use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\HttpFoundation\Request;
use Drupal\commerce_order\Entity\Order;
use Drupal\commerce_concordpay\api\ConcordpayAPI;

/**
 * Provides the ConcordPay offsite Checkout payment gateway.
 *
 * @CommercePaymentGateway(
 *   id = "сoncordpay",
 *   label = @Translation("ConcordPay (Redirect to ConcordPay)"),
 *   display_label = @Translation("ConcordPay"),
 *   modes = {
 *     "live" = "Live"
 *   },
 *   forms = {
 *     "offsite-payment" = "Drupal\commerce_concordpay\PluginForm\Redirect\ConcordpayForm",
 *   },
 *   payment_method_types = {"credit_card"},
 *   credit_card_types = {
 *    "mastercard", "visa",
 *   },
 * )
 */
class Concordpay extends OffsitePaymentGatewayBase {

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return ['merchant_id' => '', 'secret_key' => ''] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildConfigurationForm($form, $form_state);

    $form['merchant_id'] = [
      '#type'          => 'textfield',
      '#title'         => $this->t('Merchant ID'),
      '#description'   => $this->t('Given to Merchant by ConcordPay'),
      '#default_value' => $this->configuration['merchant_id'],
      '#required'      => TRUE,
    ];

    $form['secret_key'] = [
      '#type'          => 'textfield',
      '#title'         => $this->t('Secret Key'),
      '#description'   => $this->t('Given to Merchant by ConcordPay'),
      '#default_value' => $this->configuration['secret_key'],
      '#required'      => TRUE,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    parent::submitConfigurationForm($form, $form_state);
    $values = $form_state->getValue($form['#parents']);

    $this->configuration['merchant_id'] = $values['merchant_id'];
    $this->configuration['secret_key']  = $values['secret_key'];
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function onReturn(OrderInterface $order, Request $request) {
  }

  /**
   * Default payment types have the following payment transaction statuses:
   *
   * State Id: new
   * Label: New
   * Description: The initial state for any new payment.
   *
   * State Id: authorization
   * Label: Authorization
   * Description: If the payment gateway supports authorizations,
   * payments will typically transition from New to Authorization.
   *
   * State Id: completed
   * Label: Completed
   * Description: If the payment gateway supports authorizations,
   * payments will typically transition from Authorization to Completed, once the transaction is "captured".
   * For other payment gateways, payments will typically transition directly from New to Completed.
   * Only amounts for payments that have been completed are added to the order's total paid amount.
   *
   * State Id: authorization_voided
   * Label: Authorization (Voided)
   * Description: If the payment gateway supports voids, a payment can transition
   * from Authorization to Authorization (Voided).
   *
   * State Id: authorization_expired
   * Label: Authorization (Expired)
   * Description: If the payment gateway supports authorizations, a payment can transition
   * from Authorization to Authorization (Expired).
   *
   * State Id: partially_refunded
   * Label: Partially refunded
   * Description: If the payment gateway supports refunds, a payment can transition
   * from Completed to Partially refunded.
   *
   * State Id: refunded
   * Label: Refunded
   * Description: If the payment gateway supports refunds, a payment can transition
   * from either New or Partially refunded to Refunded.
   */

  /**
   * Callback handler function.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request.
   *
   * @return \Symfony\Component\HttpFoundation\Response|bool
   *   The response, or NULL to return an empty HTTP 200 response.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function onNotify(Request $request) {
    $api = new ConcordpayAPI();
    $data = json_decode(file_get_contents("php://input"), TRUE);
    if ($data === NULL || $api->isPaymentValid($data) !== TRUE) {
      return FALSE;
    }

    $order_id = $data['orderReference'];
    $order    = Order::load($order_id);
    if ($order === NULL) {
      return FALSE;
    }

    $payment_storage = $this->entityTypeManager->getStorage('commerce_payment');
    // Failed payment.
    if ($data['transactionStatus'] === ConcordpayAPI::ORDER_DECLINED) {
      $this->messenger->addError($this->t('Invalid Transaction. Please try again'));
      $this->onCancel($order, $request);
      return FALSE;
    }

    // Success payment.
    if ($data['transactionStatus'] === ConcordpayAPI::ORDER_APPROVED && isset($data['type'])) {
      // Ordinary payment.
      if ($data['type'] === ConcordpayAPI::RESPONSE_TYPE_PAYMENT) {
        $payment = $payment_storage->create([
          'state'           => ConcordpayAPI::PAYMENT_STATE_COMPLETED,
          'amount'          => $order->getTotalPrice(),
          'payment_gateway' => $this->parentEntity->id(),
          'order_id'        => $order_id,
          'remote_id'       => $data['transactionId'],
          'remote_state'    => $data['transactionStatus'],
          'authorized'      => $this->time->getRequestTime(),
        ]);
        $payment->save();

        /** @var \Drupal\commerce_order\Entity\OrderInterface $order */
        $order->getState()->applyTransitionById('place');
        $order->save();

        $this->messenger()->addMessage('ConcordPay response for order id: @order. Status: @status.',
          [
            '@order' => $order_id,
            '@status' => $data['transactionStatus'],
          ]);
      }
      // Refunded payment.
      elseif ($data['type'] === ConcordpayAPI::RESPONSE_TYPE_REVERSE) {
        $payment = $payment_storage->loadByRemoteId($data['transactionId']);
        if (!$payment) {
          $this->messenger()->addWarning(
            t('IPN for Order @order_number ignored: the transaction to be refunded does not exist.'),
            ['@order_number' => $data['orderReference']]
          );
          return FALSE;
        }

        if ($payment->getState() === ConcordpayAPI::PAYMENT_STATE_REFUNDED) {
          $this->messenger()->addWarning(
            t('IPN for Order @order_number ignored: the transaction is already refunded.'),
            ['@order_number' => $data['orderReference']]
          );
          return FALSE;
        }

        $payment->set('state', ConcordpayAPI::PAYMENT_STATE_REFUNDED);
        $payment->save();

        /** @var \Drupal\commerce_order\Entity\OrderInterface $order */
        $order->getState()->applyTransitionById('cancel');
        $order->save();
      }
    }
    die('Ok');
  }

}
