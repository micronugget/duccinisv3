<?php

declare(strict_types=1);

namespace Drupal\store_fulfillment\EventSubscriber;

use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\state_machine\Event\WorkflowTransitionEvent;
use Drupal\store_fulfillment\DeliveryRadiusValidator;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Event subscriber to validate delivery radius on order placement.
 */
class OrderPlacementDeliveryRadiusValidator implements EventSubscriberInterface {

  /**
   * The delivery radius validator service.
   *
   * @var \Drupal\store_fulfillment\DeliveryRadiusValidator
   */
  protected DeliveryRadiusValidator $validator;

  /**
   * The logger factory.
   *
   * @var \Drupal\Core\Logger\LoggerChannelFactoryInterface
   */
  protected LoggerChannelFactoryInterface $loggerFactory;

  /**
   * Constructs a new OrderPlacementDeliveryRadiusValidator.
   *
   * @param \Drupal\store_fulfillment\DeliveryRadiusValidator $validator
   *   The delivery radius validator service.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   *   The logger factory.
   */
  public function __construct(DeliveryRadiusValidator $validator, LoggerChannelFactoryInterface $logger_factory) {
    $this->validator = $validator;
    $this->loggerFactory = $logger_factory;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      'commerce_order.place.pre_transition' => ['onOrderPlace', -100],
    ];
  }

  /**
   * Validates delivery radius when order is placed.
   *
   * @param \Drupal\state_machine\Event\WorkflowTransitionEvent $event
   *   The workflow transition event.
   *
   * @throws \InvalidArgumentException
   *   Thrown if the delivery address is outside the store's radius.
   */
  public function onOrderPlace(WorkflowTransitionEvent $event): void {
    /** @var \Drupal\commerce_order\Entity\OrderInterface $order */
    $order = $event->getEntity();

    // Check if this order has a delivery fulfillment method selected.
    $fulfillment_method = $order->getData('fulfillment_method');
    if ($fulfillment_method !== 'delivery') {
      // Pickup or other method - no radius validation needed.
      return;
    }

    // Get store associated with the order.
    $store = $order->getStore();
    if (!$store) {
      $this->loggerFactory->get('store_fulfillment')->error('Order @order_id has no store assigned during placement.', [
        '@order_id' => $order->id(),
      ]);
      throw new \InvalidArgumentException('Unable to validate delivery address: No store assigned to order.');
    }

    // Resolve delivery address from shipping profile, billing profile,
    // or customer profile (matching FulfillmentTime::resolveCustomerAddress).
    $address = $this->resolveDeliveryAddress($order);

    if (!$address) {
      $this->loggerFactory->get('store_fulfillment')->warning('Order @order_id has delivery fulfillment but no address could be resolved.', [
        '@order_id' => $order->id(),
      ]);
      throw new \InvalidArgumentException('Unable to validate delivery address: No delivery address provided.');
    }

    // Validate the delivery address.
    $validation_result = $this->validator->validateDeliveryAddress($store, $address);

    if (!$validation_result['valid']) {
      $this->loggerFactory->get('store_fulfillment')->warning('Order @order_id delivery address validation failed: @message', [
        '@order_id' => $order->id(),
        '@message' => $validation_result['message'],
      ]);
      throw new \InvalidArgumentException($validation_result['message']);
    }

    $this->loggerFactory->get('store_fulfillment')->info('Order @order_id delivery address validated successfully (distance: @distance miles).', [
      '@order_id' => $order->id(),
      '@distance' => $validation_result['distance'] ?? 'unknown',
    ]);
  }

  /**
   * Resolves the delivery address from available order profiles.
   *
   * Checks shipping profile (from shipments), billing profile, and the
   * customer's default profile, in that order. This mirrors the resolution
   * logic in FulfillmentTime::resolveCustomerAddress().
   *
   * @param \Drupal\commerce_order\Entity\OrderInterface $order
   *   The order entity.
   *
   * @return \Drupal\address\AddressInterface|null
   *   The resolved address, or NULL if none found.
   */
  protected function resolveDeliveryAddress($order) {
    // 0. Try the dedicated delivery address profile (from DeliveryAddress
    // pane).
    $delivery_profile_id = $order->getData('delivery_address_profile');
    if ($delivery_profile_id) {
      $profile = \Drupal::entityTypeManager()->getStorage('profile')->load($delivery_profile_id);
      if ($profile && $profile->hasField('address') && !$profile->get('address')->isEmpty()) {
        return $profile->get('address')->first();
      }
    }

    // 1. Try shipping profile from shipments (preferred source).
    if ($order->hasField('shipments') && !$order->get('shipments')->isEmpty()) {
      /** @var \Drupal\commerce_shipping\Entity\ShipmentInterface $shipment */
      $shipment = $order->get('shipments')->first()->entity;
      if ($shipment && $shipment->hasField('shipping_profile')) {
        $shipping_profile = $shipment->getShippingProfile();
        if ($shipping_profile && $shipping_profile->hasField('address') && !$shipping_profile->get('address')->isEmpty()) {
          return $shipping_profile->get('address')->first();
        }
      }
    }

    // 2. Fall back to billing profile (collected by payment_information pane).
    $billing_profile = $order->getBillingProfile();
    if ($billing_profile && $billing_profile->hasField('address') && !$billing_profile->get('address')->isEmpty()) {
      return $billing_profile->get('address')->first();
    }

    // 3. Fall back to customer's default profile.
    $customer = $order->getCustomer();
    if ($customer && !$customer->isAnonymous()) {
      $profile_storage = \Drupal::entityTypeManager()->getStorage('profile');
      $profiles = $profile_storage->loadByProperties([
        'uid' => $customer->id(),
        'type' => 'customer',
        'is_default' => TRUE,
        'status' => TRUE,
      ]);
      if ($profiles) {
        $profile = reset($profiles);
        if ($profile->hasField('address') && !$profile->get('address')->isEmpty()) {
          return $profile->get('address')->first();
        }
      }
    }

    return NULL;
  }

}
