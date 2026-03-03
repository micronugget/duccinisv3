<?php

declare(strict_types=1);

namespace Drupal\Tests\store_fulfillment\Kernel;

use Drupal\store_fulfillment\Plugin\Block\CheckoutProgressBarBlock;
use Drupal\Tests\commerce\Kernel\CommerceKernelTestBase;

/**
 * Tests CheckoutProgressBarBlock step-state logic.
 *
 * Instantiates the block directly with a mocked RouteMatchInterface and
 * asserts that the correct done/active/future states are produced for every
 * funnel position. URL generation is not asserted — routes are intentionally
 * absent in this environment and all url-generating code paths are guarded
 * with try/catch in the block.
 *
 * @coversDefaultClass \Drupal\store_fulfillment\Plugin\Block\CheckoutProgressBarBlock
 * @group store_fulfillment
 */
class CheckoutProgressBarBlockTest extends CommerceKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'profile',
    'state_machine',
    'entity_reference_revisions',
    'commerce_number_pattern',
    'commerce_order',
    'store_resolver',
    'geocoder',
    'store_fulfillment',
  ];

  /**
   * Creates a block instance with a mocked route match.
   *
   * @param string $route_name
   *   The route name to return from the mock.
   * @param string|null $step
   *   The 'step' route parameter (checkout routes only).
   * @param bool $show_cart_step
   *   Whether to show the Cart step.
   *
   * @return \Drupal\store_fulfillment\Plugin\Block\CheckoutProgressBarBlock
   *   The block instance.
   */
  private function makeBlock(string $route_name, ?string $step = NULL, bool $show_cart_step = TRUE): CheckoutProgressBarBlock {
    $route_match = $this->createMock('\Drupal\Core\Routing\RouteMatchInterface');
    $route_match->method('getRouteName')->willReturn($route_name);
    $route_match->method('getParameter')->willReturnCallback(
      function (string $param) use ($step): mixed {
        if ($param === 'step') {
          return $step;
        }
        // commerce_order param: return NULL (no order in test env).
        return NULL;
      }
    );

    return new CheckoutProgressBarBlock(
      ['show_cart_step' => $show_cart_step],
      'checkout_progress_bar',
      [],
      $route_match,
    );
  }

  /**
   * Returns the 'state' values from a steps array, keyed by label string.
   *
   * @param array $steps
   *   Steps array from buildSteps().
   *
   * @return array
   *   Map of label => state strings.
   */
  private function statesByLabel(array $steps): array {
    $map = [];
    foreach ($steps as $step) {
      $map[(string) $step['label']] = $step['state'];
    }
    return $map;
  }

  /**
   * @covers ::buildSteps
   */
  public function testNonFunnelRouteReturnsEmptyBuild(): void {
    $block = $this->makeBlock('entity.node.canonical');
    $build = $block->build();
    $this->assertEmpty($build, 'Block should render nothing on non-funnel routes.');
  }

  /**
   * @covers ::buildSteps
   */
  public function testCartPageActiveState(): void {
    $block = $this->makeBlock('commerce_cart.page');
    $steps = $block->buildSteps();

    $this->assertCount(4, $steps, 'Four steps when show_cart_step is TRUE.');
    $states = $this->statesByLabel($steps);

    $this->assertSame('active', $states['Cart'], 'Cart is active on the cart page.');
    $this->assertSame('', $states['Order Details'], 'Order Details is future on the cart page.');
    $this->assertSame('', $states['Review'], 'Review is future on the cart page.');
    $this->assertSame('', $states['Complete'], 'Complete is future on the cart page.');

    // Cart step has no URL when it is the active step.
    $this->assertNull($steps[0]['url'], 'Cart step URL is null when active.');
  }

  /**
   * @covers ::buildSteps
   */
  public function testOrderInformationActiveState(): void {
    $block = $this->makeBlock('commerce_checkout.form', 'order_information');
    $steps = $block->buildSteps();

    $states = $this->statesByLabel($steps);

    $this->assertSame('done', $states['Cart'], 'Cart is done on order_information.');
    $this->assertSame('active', $states['Order Details'], 'Order Details is active on order_information.');
    $this->assertSame('', $states['Review'], 'Review is future on order_information.');
    $this->assertSame('', $states['Complete'], 'Complete is future on order_information.');
  }

  /**
   * @covers ::buildSteps
   */
  public function testReviewActiveState(): void {
    $block = $this->makeBlock('commerce_checkout.form', 'review');
    $steps = $block->buildSteps();

    $states = $this->statesByLabel($steps);

    $this->assertSame('done', $states['Cart'], 'Cart is done on review.');
    $this->assertSame('done', $states['Order Details'], 'Order Details is done on review.');
    $this->assertSame('active', $states['Review'], 'Review is active on review.');
    $this->assertSame('', $states['Complete'], 'Complete is future on review.');
  }

  /**
   * @covers ::buildSteps
   */
  public function testCompleteActiveState(): void {
    $block = $this->makeBlock('commerce_checkout.form', 'complete');
    $steps = $block->buildSteps();

    $states = $this->statesByLabel($steps);

    $this->assertSame('done', $states['Cart'], 'Cart is done on complete.');
    $this->assertSame('done', $states['Order Details'], 'Order Details is done on complete.');
    $this->assertSame('done', $states['Review'], 'Review is done on complete.');
    $this->assertSame('active', $states['Complete'], 'Complete is active on complete step.');
  }

  /**
   * @covers ::buildSteps
   */
  public function testCompleteStepSuppressesBackLinks(): void {
    $block = $this->makeBlock('commerce_checkout.form', 'complete');
    $steps = $block->buildSteps();

    foreach ($steps as $step) {
      $this->assertNull($step['url'], sprintf('Step "%s" must have no back-link on the complete step.', (string) $step['label']));
      $this->assertNull($step['analytics'], sprintf('Step "%s" must have no analytics data on the complete step.', (string) $step['label']));
    }
  }

  /**
   * @covers ::buildSteps
   * @covers ::defaultConfiguration
   */
  public function testShowCartStepFalseOmitsCartStep(): void {
    $block = $this->makeBlock('commerce_checkout.form', 'order_information', FALSE);
    $steps = $block->buildSteps();

    $this->assertCount(3, $steps, 'Three steps when show_cart_step is FALSE.');
    $labels = array_map(fn($s) => (string) $s['label'], $steps);
    $this->assertNotContains('Cart', $labels, 'Cart step must be absent when show_cart_step is FALSE.');

    $states = $this->statesByLabel($steps);
    $this->assertSame('active', $states['Order Details'], 'Order Details is active on order_information without Cart step.');
  }

  /**
   * @covers ::buildSteps
   */
  public function testUnknownCheckoutStepDefaultsToOrderInformation(): void {
    $block = $this->makeBlock('commerce_checkout.form', 'custom_step');
    $steps = $block->buildSteps();

    $states = $this->statesByLabel($steps);

    // Unknown steps default to index 0 (order_information).
    $this->assertSame('active', $states['Order Details'], 'Unknown step defaults to order_information position.');
  }

  /**
   * @covers ::build
   */
  public function testBuildReturnsComponentRenderArray(): void {
    $block = $this->makeBlock('commerce_checkout.form', 'review');
    $build = $block->build();

    $this->assertSame('component', $build['#type']);
    $this->assertSame('duccinis_1984_olympics:checkout-progress', $build['#component']);
    $this->assertArrayHasKey('steps', $build['#props']);
    $this->assertNotEmpty($build['#props']['steps']);
  }

  /**
   * @covers ::getCacheContexts
   */
  public function testCacheContextsIncludeRouteAndUser(): void {
    $block = $this->makeBlock('commerce_checkout.form', 'review');
    $contexts = $block->getCacheContexts();

    $this->assertContains('route', $contexts, 'Cache contexts must include "route".');
    $this->assertContains('user', $contexts, 'Cache contexts must include "user".');
  }

}
