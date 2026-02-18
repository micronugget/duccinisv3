<?php

declare(strict_types=1);

namespace Drupal\store_fulfillment\EventSubscriber;

use Drupal\commerce_order\Event\OrderEvent;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\state_machine\Event\WorkflowTransitionEvent;
use Drupal\store_fulfillment\OrderValidator;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Validates order fulfillment time before order placement.
 */
class OrderPlacementValidator implements EventSubscriberInterface {

  /**
   * The order validator service.
   *
   * @var \Drupal\store_fulfillment\OrderValidator
   */
  protected $orderValidator;

  /**
   * The logger factory.
   *
   * @var \Drupal\Core\Logger\LoggerChannelFactoryInterface
   */
  protected $loggerFactory;

  /**
   * Constructs a new OrderPlacementValidator.
   *
   * @param \Drupal\store_fulfillment\OrderValidator $order_validator
   *   The order validator service.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   *   The logger factory.
   */
  public function __construct(
    OrderValidator $order_validator,
    LoggerChannelFactoryInterface $logger_factory
  ) {
    $this->orderValidator = $order_validator;
    $this->loggerFactory = $logger_factory;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    // Subscribe to the order placement workflow transition.
    // Priority 100 to run before other subscribers.
    $events['commerce_order.place.pre_transition'] = ['validateOrderPlacement', 100];
    return $events;
  }

  /**
   * Validates order fulfillment time before placement.
   *
   * @param \Drupal\state_machine\Event\WorkflowTransitionEvent $event
   *   The transition event.
   *
   * @throws \InvalidArgumentException
   *   If fulfillment time validation fails.
   */
  public function validateOrderPlacement(WorkflowTransitionEvent $event) {
    /** @var \Drupal\commerce_order\Entity\OrderInterface $order */
    $order = $event->getEntity();

    // Get fulfillment data from order.
    $fulfillment_type = $order->getData('fulfillment_type');
    $scheduled_time = $order->getData('scheduled_time');

    // Determine requested time based on fulfillment type.
    $requested_time = NULL;
    if ($fulfillment_type === 'scheduled' && $scheduled_time) {
      $requested_time = $scheduled_time;
    }

    // At order placement (post-payment), skip the minimum advance notice
    // check. The scheduled time was already validated during checkout before
    // payment was collected. Re-validating advance notice here would reject
    // orders where the payment gateway round-trip consumed the lead time.
    $validation_result = $this->orderValidator->validateFulfillmentTime(
      $order,
      $requested_time,
      TRUE
    );

    // If validation fails, log and throw exception to block order placement.
    if (!$validation_result['valid']) {
      $logger = $this->loggerFactory->get('commerce_order');
      $logger->error(
        'Order @order_id placement blocked: @message',
        [
          '@order_id' => $order->id(),
          '@message' => $validation_result['message'],
        ]
      );

      throw new \InvalidArgumentException(
        'Order placement failed: ' . $validation_result['message']
      );
    }
  }

}
