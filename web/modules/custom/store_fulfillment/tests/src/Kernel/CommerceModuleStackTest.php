<?php

declare(strict_types=1);

namespace Drupal\Tests\store_fulfillment\Kernel;

use Drupal\Tests\commerce\Kernel\CommerceKernelTestBase;
use Symfony\Component\Yaml\Yaml;

/**
 * Regression guard for issue #160: Commerce module stack + checkout flow.
 *
 * Verifies that:
 *  - The required Commerce sub-modules are present in the module handler.
 *  - The Duccinis custom checkout flow panes exist in config.
 *  - The Stripe payment gateway config entry exists with the correct plugin.
 *
 * @group store_fulfillment
 */
class CommerceModuleStackTest extends CommerceKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'profile',
    'state_machine',
    'entity_reference_revisions',
    'commerce_number_pattern',
    'commerce_order',
    'commerce_checkout',
    'commerce_payment',
    'commerce_promotion',
    'commerce_shipping',
    'physical',
    'commerce_stripe',
    'geocoder',
    'store_resolver',
    'store_fulfillment',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('commerce_order');
    $this->installEntitySchema('profile');
    $this->installConfig([
      'commerce_order',
      'commerce_checkout',
      'commerce_payment',
      'store_fulfillment',
    ]);
  }

  /**
   * Asserts required Commerce sub-modules are present in the module handler.
   *
   * This is a regression guard: if a future `cim` accidentally removes these
   * modules from core.extension.yml the test runner will catch it here.
   */
  public function testCommerceCheckoutModulesEnabled(): void {
    $module_handler = $this->container->get('module_handler');

    foreach (['commerce_checkout', 'commerce_payment', 'commerce_order', 'commerce_stripe'] as $module) {
      $this->assertTrue(
        $module_handler->moduleExists($module),
        "Module '{$module}' must be enabled."
      );
    }
  }

  /**
   * Asserts the Duccinis custom checkout panes are present in active config.
   *
   * Reads the config/sync YAML file directly to guard against accidental
   * overwrites by a plain `cim` that would reset the flow to Commerce defaults.
   */
  public function testCheckoutFlowCustomPanesPresent(): void {
    $sync_file = DRUPAL_ROOT . '/../config/sync/commerce_checkout.commerce_checkout_flow.default.yml';
    $this->assertFileExists($sync_file, 'Checkout flow sync file must exist.');

    $data = Yaml::parse(file_get_contents($sync_file));

    $this->assertSame('default', $data['id']);

    $panes = $data['configuration']['panes'] ?? [];
    $this->assertIsArray($panes, 'Panes must be an array.');

    $required_panes = [
      'fulfillment_time',
      'delivery_address',
      'payment_information',
      'stripe_review',
    ];

    foreach ($required_panes as $pane_id) {
      $this->assertArrayHasKey(
        $pane_id,
        $panes,
        "Checkout flow must contain the '{$pane_id}' pane."
      );
    }

    // Verify pane steps are ordered as designed.
    $this->assertSame('order_information', $panes['fulfillment_time']['step']);
    $this->assertSame('order_information', $panes['delivery_address']['step']);
    $this->assertSame('order_information', $panes['payment_information']['step']);
    $this->assertSame('review', $panes['stripe_review']['step']);
  }

  /**
   * Asserts the Stripe payment gateway config uses the correct plugin.
   *
   * Reads the config/sync YAML file directly.
   */
  public function testStripeGatewayConfigured(): void {
    $sync_file = DRUPAL_ROOT . '/../config/sync/commerce_payment.commerce_payment_gateway.stripe.yml';
    $this->assertFileExists($sync_file, 'Stripe gateway sync file must exist.');

    $data = Yaml::parse(file_get_contents($sync_file));

    $this->assertSame('stripe', $data['id'], 'Stripe gateway id must be "stripe".');
    $this->assertSame(
      'stripe_payment_element',
      $data['plugin'],
      'Stripe gateway must use the stripe_payment_element plugin.'
    );
  }

}
