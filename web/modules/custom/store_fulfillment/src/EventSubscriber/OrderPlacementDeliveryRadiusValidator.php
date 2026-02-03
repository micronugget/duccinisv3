<?php

declare(strict_types=1);

namespace Drupal\store_fulfillment\EventSubscriber;

use Drupal\commerce_order\Event\OrderEvent;
use Drupal\state_machine\Event\WorkflowTransitionEvent;
use Drupal\store_fulfillment\DeliveryRadiusValidator;
use Psr\Log\LoggerInterface;
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
   * The logger service.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected LoggerInterface $logger;

  /**
   * Constructs a new OrderPlacementDeliveryRadiusValidator.
   *
   * @param \Drupal\store_fulfillment\DeliveryRadiusValidator $validator
   *   The delivery radius validator service.
   * @param \Psr\Log\LoggerInterface $logger
   *   The logger service.
   */
  public function __construct(DeliveryRadiusValidator $validator, LoggerInterface $logger) {
    $this->validator = $validator;
    $this->logger = $logger;
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
      $this->logger->error('Order @order_id has no store assigned during placement.', [
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
      $this->logger->warning('Order @order_id has delivery fulfillment but no shipping profile.', [
        '@order_id' => $order->id(),
      ]);
      throw new \InvalidArgumentException('Unable to validate delivery address: No shipping information found.');
    }

    // Get delivery address from shipping profile.
    if (!$shipping_profile->hasField('address') || $shipping_profile->get('address')->isEmpty()) {
      $this->logger->warning('Order @order_id shipping profile has no address.', [
        '@order_id' => $order->id(),
      ]);
      throw new \InvalidArgumentException('Unable to validate delivery address: No delivery address provided.');
    }

    /** @var \Drupal\address\AddressInterface $address */
    $address = $shipping_profile->get('address')->first();

    // Validate the delivery address.
    $validation_result = $this->validator->validateDeliveryAddress($store, $address);

    if (!$validation_result['valid']) {
      $this->logger->warning('Order @order_id delivery address validation failed: @message', [
        '@order_id' => $order->id(),
        '@message' => $validation_result['message'],
      ]);
      throw new \InvalidArgumentException($validation_result['message']);
    }

    $this->logger->info('Order @order_id delivery address validated successfully (distance: @distance miles).', [
      '@order_id' => $order->id(),
      '@distance' => $validation_result['distance'] ?? 'unknown',
    ]);
  }

}
