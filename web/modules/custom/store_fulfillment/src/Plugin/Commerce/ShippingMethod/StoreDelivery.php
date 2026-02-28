<?php

namespace Drupal\store_fulfillment\Plugin\Commerce\ShippingMethod;

use Drupal\commerce_price\Price;
use Drupal\commerce_shipping\Entity\ShipmentInterface;
use Drupal\commerce_shipping\Plugin\Commerce\ShippingMethod\ShippingMethodBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides the Store Delivery shipping method.
 *
 * @CommerceShippingMethod(
 *   id = "store_delivery",
 *   label = @Translation("Store Delivery"),
 *   description = @Translation("Delivery from the selected store location."),
 * )
 */
class StoreDelivery extends ShippingMethodBase {

  /**
   * The delivery radius calculator.
   *
   * @var \Drupal\store_fulfillment\DeliveryRadiusCalculator
   */
  protected $radiusCalculator;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->radiusCalculator = $container->get('store_fulfillment.delivery_radius_calculator');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'rate_label' => 'Delivery',
      'rate_amount' => [
        'number' => '5.00',
        'currency_code' => 'USD',
      ],
      'free_delivery_minimum' => [
        'number' => '50.00',
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
      '#title' => $this->t('Delivery fee'),
      '#description' => $this->t('The delivery fee charged to customers.'),
      '#default_value' => $this->configuration['rate_amount'],
      '#required' => TRUE,
    ];

    $form['free_delivery_minimum'] = [
      '#type' => 'commerce_price',
      '#title' => $this->t('Free delivery minimum'),
      '#description' => $this->t('Orders over this amount qualify for free delivery. Leave at 0 to disable.'),
      '#default_value' => $this->configuration['free_delivery_minimum'],
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
      $this->configuration['free_delivery_minimum'] = $values['free_delivery_minimum'];
    }
  }

  /**
   * {@inheritdoc}
   */
  public function calculateRates(ShipmentInterface $shipment) {
    $rates = [];

    // Get shipping profile to check delivery address.
    $shipping_profile = $shipment->getShippingProfile();
    if (!$shipping_profile || !$shipping_profile->hasField('address')) {
      return $rates;
    }

    $delivery_address = $shipping_profile->get('address')->first();
    if (!$delivery_address) {
      return $rates;
    }

    // Get current store from resolver.
    $store_resolver = \Drupal::service('store_resolver.current_store');
    $store = $store_resolver->getCurrentStore();
    if (!$store) {
      return $rates;
    }

    // Check if address is within delivery radius.
    if (!$this->radiusCalculator->isWithinRadius($store, $delivery_address)) {
      // Address is outside delivery area.
      return $rates;
    }

    // Calculate delivery fee based on order total.
    $order = $shipment->getOrder();
    $order_total = $order->getTotalPrice();

    $rate_amount = $this->configuration['rate_amount'];
    $free_minimum = $this->configuration['free_delivery_minimum'];

    // Check if order qualifies for free delivery.
    if ($free_minimum['number'] > 0 && $order_total->greaterThanOrEqual(new Price($free_minimum['number'], $free_minimum['currency_code']))) {
      $amount = new Price('0', $rate_amount['currency_code']);
      $label = $this->configuration['rate_label'] . ' (Free)';
    }
    else {
      $amount = new Price($rate_amount['number'], $rate_amount['currency_code']);
      $label = $this->configuration['rate_label'];
    }

    $rates[] = [
      'shipping_method_id' => $this->parentEntity->id(),
      'service' => [
        'id' => 'default',
        'label' => $label,
      ],
      'amount' => $amount,
    ];

    return $rates;
  }

}
