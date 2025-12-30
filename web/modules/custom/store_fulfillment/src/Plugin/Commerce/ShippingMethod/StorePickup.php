<?php

namespace Drupal\store_fulfillment\Plugin\Commerce\ShippingMethod;

use Drupal\commerce_price\Price;
use Drupal\commerce_shipping\Entity\ShipmentInterface;
use Drupal\commerce_shipping\PackageTypeManagerInterface;
use Drupal\commerce_shipping\Plugin\Commerce\ShippingMethod\ShippingMethodBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Provides the Store Pickup shipping method.
 *
 * @CommerceShippingMethod(
 *   id = "store_pickup",
 *   label = @Translation("Store Pickup"),
 *   description = @Translation("Pick up your order at the selected store location."),
 * )
 */
class StorePickup extends ShippingMethodBase {

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'rate_label' => 'Pickup at Store',
      'rate_amount' => [
        'number' => '0.00',
        'currency_code' => 'USD',
      ],
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildConfigurationForm($form, $form_state);

    $form['rate_label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Rate label'),
      '#default_value' => $this->configuration['rate_label'],
      '#required' => TRUE,
    ];

    $form['rate_amount'] = [
      '#type' => 'commerce_price',
      '#title' => $this->t('Rate amount'),
      '#default_value' => $this->configuration['rate_amount'],
      '#required' => TRUE,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    parent::submitConfigurationForm($form, $form_state);

    if (!$form_state->getErrors()) {
      $values = $form_state->getValue($form['#parents']);
      $this->configuration['rate_label'] = $values['rate_label'];
      $this->configuration['rate_amount'] = $values['rate_amount'];
    }
  }

  /**
   * {@inheritdoc}
   */
  public function calculateRates(ShipmentInterface $shipment) {
    // Pickup is typically free or a flat rate.
    $rate_amount = $this->configuration['rate_amount'];
    $amount = new Price($rate_amount['number'], $rate_amount['currency_code']);

    $rates = [];
    $rates[] = [
      'shipping_method_id' => $this->parentEntity->id(),
      'service' => [
        'id' => 'default',
        'label' => $this->configuration['rate_label'],
      ],
      'amount' => $amount,
    ];

    return $rates;
  }

}
