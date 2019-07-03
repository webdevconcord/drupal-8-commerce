<?php

namespace Drupal\commerce_concordpay\Plugin\Commerce\PaymentGateway;

use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_payment\Plugin\Commerce\PaymentGateway\OffsitePaymentGatewayBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\HttpFoundation\Request;
use Drupal\commerce_order\Entity\Order;
use Drupal\commerce_concordpay\api\ConcordpayAPI;

/**
 * Provides the Concord Pay offsite Checkout payment gateway.
 *
 * @CommercePaymentGateway(
 *   id = "сoncordpay",
 *   label = @Translation("Concord Pay (Redirect to Concord Pay)"),
 *   display_label = "Concord Pay",
 *   modes =  {
 *       "live" = "Live"
 *     },
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
	    return [
		'merchant_id' => '',
		'secret_key' => '',
         ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildConfigurationForm($form, $form_state);

   $form['merchant_id'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Merchant ID'),
      '#description' => $this->t('This is the merchant id from the Concord Pay manager.'),
      '#default_value' => $this->configuration['merchant_id'],
      '#required' => TRUE,
    ];

    $form['secret_key'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Secret Key'),
      '#description' => $this->t('The secret key for the same user as used in Agreement ID.'),
      '#default_value' => $this->configuration['secret_key'],
      '#required' => TRUE,
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
    $this->configuration['secret_key'] = $values['secret_key'];
  }

    /**
     * {@inheritdoc}
     */
    public function onReturn(OrderInterface $order, Request $request) {
  	$payment_storage = $this->entityTypeManager->getStorage('commerce_payment');
        $payment = $payment_storage->create(['state' => ConcordpayAPI::ORDER_PENDING, 'amount' => $order->getTotalPrice() , 'payment_gateway' => $this->entityId, 'order_id' => $order->id() , 'remote_id' => $order->id() , 'remote_state' => ConcordpayAPI::ORDER_PENDING, ]);
        $payment->save();
        $this->messenger()->addMessage($this->t('Your payment was successful with Order id : @orderid', ['@orderid' => $order->id() ]));
    }


    /**
     * callback function
     *
     * @param Request $request
    */
    public function onNotify(Request $request) {
    
	$api = new ConcordpayAPI();
  
        $data = json_decode(file_get_contents("php://input"), true);
 
        //делаем проверку на валидность платежа
        if($api->isPaymentValid($data) !== true){
	  return false;
        }

        $order_id = $data["orderReference"];
  
        $order = Order::load($order_id);

        $payment_storage = $this->entityTypeManager->getStorage('commerce_payment');
        if ($data['transactionStatus'] == 'Declined') {
            $order->set('state', 'Declined');
            $order->save();
        }
        $last = $payment_storage->loadByProperties([
            'payment_gateway' => $this->entityId,
            'order_id' => $order_id,
            'remote_id' => $order_id
        ]);
        if (!empty($last)) {
            $payment_storage->delete($last);
        }
        $payment = $payment_storage->create([
            'state' => ConcordpayAPI::ORDER_APPROVED,
            'amount' => $order->getTotalPrice(),
            'payment_gateway' => $this->entityId,
            'order_id' => $order_id,
            'remote_id' => $order_id,
            'remote_state' => $data['transactionStatus'],
        ]);
 
        $payment->save();
        die('Ok');
    }

}
