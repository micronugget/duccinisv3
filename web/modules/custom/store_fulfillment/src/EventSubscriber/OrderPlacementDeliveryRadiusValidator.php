<?php

declare(strict_types=1);

namespace Drupal\store_fulfillment\EventSubscriber;

use Drupal\commerce_order\Event\OrderEvent;
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

    // Get shipping profile with delivery address.
    $shipping_profile = NULL;
    if ($order->hasField('shipments') && !$order->get('shipments')->isEmpty()) {
      /** @var \Drupal\commerce_shipping\Entity\ShipmentInterface $shipment */
      $shipment = $order->get('shipments')->first()->entity;
      if ($shipment && $shipment->hasField('shipping_profile')) {
        $shipping_profile = $shipment->getShippingProfile();
      }
    }

    if (!$shipping_profile) {
      $this->loggerFactory->get('store_fulfillment')->warning('Order @order_id has delivery fulfillment but no shipping profile.', [
        '@order_id' => $order->id(),
      ]);
      throw new \InvalidArgumentException('Unable to validate delivery address: No shipping information found.');
    }

    // Get delivery address from shipping profile.
    if (!$shipping_profile->hasField('address') || $shipping_profile->get('address')->isEmpty()) {
      $this->loggerFactory->get('store_fulfillment')->warning('Order @order_id shipping profile has no address.', [
        '@order_id' => $order->id(),
      ]);
      throw new \InvalidArgumentException('Unable to validate delivery address: No delivery address provided.');
    }

    /** @var \Drupal\address\AddressInterface $address */
    $address = $shipping_profile->get('address')->first();

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

}
