<?php

declare(strict_types=1);

namespace Drupal\Tests\store_fulfillment\Kernel;

use Drupal\Tests\commerce\Kernel\CommerceKernelTestBase;
use Drupal\store_fulfillment\DeliveryRadiusCalculator;
use Drupal\store_fulfillment\DeliveryRadiusValidator;
use Drupal\store_fulfillment\OrderValidator;

/**
 * Verifies that all three store_fulfillment services are registered.
 *
 * Closes issue #108 — "Verify store_fulfillment.services.yml parity with V4".
 *
 * V4's services.yml defines three services with the following constructor
 * injection (verified identical to V3):
 *
 *   store_fulfillment.delivery_radius_calculator:
 *     arguments: ['@entity_type.manager', '@geocoder', '@logger.factory']
 *
 *   store_fulfillment.delivery_radius_validator:
 *     arguments:
 *       - '@store_fulfillment.delivery_radius_calculator'
 *       - '@string_translation'
 *       - '@geocoder'
 *       - '@logger.factory'
 *       - '@entity_type.manager'
 *
 *   store_fulfillment.order_validator:
 *     arguments: ['@store_resolver.hours_validator', '@datetime.time',
 *                 '@config.factory']
 *
 * @group store_fulfillment
 * @group services
 */
class ServiceContainerTest extends CommerceKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'profile',
    'state_machine',
    'entity_reference_revisions',
    'geofield',
    'geocoder',
    'store_resolver',
    'store_fulfillment',
  ];

  /**
   * Tests that store_fulfillment.order_validator is registered.
   *
   * @covers \Drupal\store_fulfillment\OrderValidator
   */
  public function testOrderValidatorServiceRegistered(): void {
    $service = $this->container->get('store_fulfillment.order_validator');
    $this->assertInstanceOf(OrderValidator::class, $service);
  }

  /**
   * Tests that store_fulfillment.delivery_radius_calculator is registered.
   *
   * @covers \Drupal\store_fulfillment\DeliveryRadiusCalculator
   */
  public function testDeliveryRadiusCalculatorServiceRegistered(): void {
    $service = $this->container->get('store_fulfillment.delivery_radius_calculator');
    $this->assertInstanceOf(DeliveryRadiusCalculator::class, $service);
  }

  /**
   * Tests that store_fulfillment.delivery_radius_validator is registered.
   *
   * @covers \Drupal\store_fulfillment\DeliveryRadiusValidator
   */
  public function testDeliveryRadiusValidatorServiceRegistered(): void {
    $service = $this->container->get('store_fulfillment.delivery_radius_validator');
    $this->assertInstanceOf(DeliveryRadiusValidator::class, $service);
  }

  /**
   * Tests that all three services resolve without error in a single request.
   *
   * This mirrors the acceptance criterion from issue #108:
   * `ddev drush php-eval "print_r(\Drupal::service(...));"` must work.
   */
  public function testAllThreeServicesResolve(): void {
    $services = [
      'store_fulfillment.order_validator' => OrderValidator::class,
      'store_fulfillment.delivery_radius_calculator' => DeliveryRadiusCalculator::class,
      'store_fulfillment.delivery_radius_validator' => DeliveryRadiusValidator::class,
    ];

    foreach ($services as $service_id => $expected_class) {
      $service = $this->container->get($service_id);
      $this->assertInstanceOf(
        $expected_class,
        $service,
        sprintf('Service "%s" must resolve as %s.', $service_id, $expected_class),
      );
    }
  }

}
