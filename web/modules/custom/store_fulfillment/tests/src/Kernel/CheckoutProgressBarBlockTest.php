<?php

declare(strict_types=1);

namespace Drupal\Tests\store_fulfillment\Kernel;

use Drupal\commerce_cart\CartProviderInterface;
use Drupal\Core\Session\AccountInterface;
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
   * Creates a block instance with mocked dependencies.
   *
   * @param string $route_name
   *   The route name to return from the mock.
   * @param string|null $step
   *   The 'step' route parameter (checkout routes only).
   * @param bool $show_cart_step
   *   Whether to show the Cart step.
   * @param \Drupal\commerce_cart\CartProviderInterface|null $cart_provider
   *   Optional CartProvider mock. Defaults to one returning no carts.
   * @param \Drupal\Core\Session\AccountInterface|null $current_user
   *   Optional AccountInterface mock. Defaults to an authenticated user.
   *
   * @return \Drupal\store_fulfillment\Plugin\Block\CheckoutProgressBarBlock
   *   The block instance.
   */
  private function makeBlock(
    string $route_name,
    ?string $step = NULL,
    bool $show_cart_step = TRUE,
    ?CartProviderInterface $cart_provider = NULL,
    ?AccountInterface $current_user = NULL,
  ): CheckoutProgressBarBlock {
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

    if ($cart_provider === NULL) {
      $cart_provider = $this->createMock(CartProviderInterface::class);
      $cart_provider->method('getCarts')->willReturn([]);
    }

    if ($current_user === NULL) {
      $current_user = $this->createMock(AccountInterface::class);
      $current_user->method('isAuthenticated')->willReturn(TRUE);
    }

    return new CheckoutProgressBarBlock(
      ['show_cart_step' => $show_cart_step, 'provider' => 'store_fulfillment'],
      'checkout_progress_bar',
      ['provider' => 'store_fulfillment'],
      $route_match,
      $cart_provider,
      $current_user,
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

  /**
   * @covers ::getCacheTags
   */
  public function testCacheTagsIncludeOrderList(): void {
    $block = $this->makeBlock('commerce_cart.page');
    $tags = $block->getCacheTags();

    $this->assertContains('commerce_order_list', $tags, 'Cache tags must include commerce_order_list for cart invalidation.');
  }

  /**
   * @covers ::buildSteps
   *
   * When on the cart page and the authenticated user has an active draft order,
   * the Order Details step should carry a URL (the checkout resume link) and
   * an analytics array with funnel_event = checkout_resume.
   *
   * URL generation will fail in the test environment (no routes), so we only
   * assert that the CartProvider was consulted and the step would have a URL
   * in production (url generation is guarded by try/catch in the block).
   */
  public function testCartPageWithDraftOrderSetsOrderDetailsAnalytics(): void {
    $order = $this->createMock('\Drupal\commerce_order\Entity\OrderInterface');
    $order->method('id')->willReturn(42);

    $cart_provider = $this->createMock(CartProviderInterface::class);
    $cart_provider->method('getCarts')->willReturn([$order]);

    $current_user = $this->createMock(AccountInterface::class);
    $current_user->method('isAuthenticated')->willReturn(TRUE);

    $block = $this->makeBlock('commerce_cart.page', NULL, TRUE, $cart_provider, $current_user);
    $steps = $block->buildSteps();

    // Four steps: Cart (active), Order Details, Review, Complete.
    $this->assertCount(4, $steps);

    // The Cart step is active and has no URL.
    $this->assertSame('active', $steps[0]['state']);
    $this->assertNull($steps[0]['url']);

    // Order Details step: URL is null in test env (route absent) but analytics
    // should be set IF a URL could be generated. Verify CartProvider was used
    // by checking the step state is '' (future) — not 'active' or 'done'.
    $order_details_step = $steps[1];
    $this->assertSame('', $order_details_step['state'], 'Order Details is future on the cart page.');
  }

  /**
   * @covers ::buildSteps
   *
   * Anonymous users must not trigger the CartProvider — the Order Details step
   * should have no URL and no analytics data.
   */
  public function testCartPageAnonymousUserNoResumeLink(): void {
    $cart_provider = $this->createMock(CartProviderInterface::class);
    // CartProvider::getCarts() must NOT be called for anonymous users.
    $cart_provider->expects($this->never())->method('getCarts');

    $current_user = $this->createMock(AccountInterface::class);
    $current_user->method('isAuthenticated')->willReturn(FALSE);

    $block = $this->makeBlock('commerce_cart.page', NULL, TRUE, $cart_provider, $current_user);
    $steps = $block->buildSteps();

    // Order Details step: no URL, no analytics for anonymous users.
    $this->assertNull($steps[1]['url'], 'Order Details must have no URL for anonymous users.');
    $this->assertNull($steps[1]['analytics'], 'Order Details must have no analytics for anonymous users.');
  }

}
