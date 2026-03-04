<?php

declare(strict_types=1);

namespace Drupal\Tests\store_fulfillment\Kernel;

use Drupal\store_fulfillment\Plugin\Block\CheckoutHeaderBlock;
use Drupal\Tests\commerce\Kernel\CommerceKernelTestBase;

/**
 * Tests CheckoutHeaderBlock step-title and back-link logic.
 *
 * Instantiates the block directly with a mocked RouteMatchInterface and
 * asserts the correct title and back_url context are produced for every
 * checkout step. URL generation is not asserted — cart routes are absent
 * in the kernel environment.
 *
 * @coversDefaultClass \Drupal\store_fulfillment\Plugin\Block\CheckoutHeaderBlock
 * @group store_fulfillment
 */
class CheckoutHeaderBlockTest extends CommerceKernelTestBase {

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
   * Creates a CheckoutHeaderBlock instance with a mocked route.
   *
   * @param string $route_name
   *   The route name to return from the mock.
   * @param string|null $step
   *   The 'step' route parameter (checkout routes only).
   * @param bool $show_back_link
   *   Whether to show the back link (block config option).
   *
   * @return \Drupal\store_fulfillment\Plugin\Block\CheckoutHeaderBlock
   *   The block instance.
   */
  private function makeBlock(
    string $route_name,
    ?string $step = NULL,
    bool $show_back_link = TRUE,
  ): CheckoutHeaderBlock {
    $route_match = $this->createMock('\Drupal\Core\Routing\RouteMatchInterface');
    $route_match->method('getRouteName')->willReturn($route_name);
    $route_match->method('getParameter')->willReturnCallback(
      function (string $param) use ($step): mixed {
        return ($param === 'step') ? $step : NULL;
      }
    );

    return new CheckoutHeaderBlock(
      ['show_back_link' => $show_back_link, 'provider' => 'store_fulfillment'],
      'checkout_header',
      ['provider' => 'store_fulfillment'],
      $route_match,
    );
  }

  /**
   * @covers ::build
   */
  public function testNonCheckoutRouteReturnsEmptyBuild(): void {
    $block = $this->makeBlock('entity.node.canonical');
    $build = $block->build();
    $this->assertEmpty($build, 'Block renders nothing on non-checkout routes.');
  }

  /**
   * @covers ::build
   */
  public function testOrderInformationStepTitle(): void {
    $block = $this->makeBlock('commerce_checkout.form', 'order_information');
    $build = $block->build();

    $this->assertNotEmpty($build, 'Block renders on order_information step.');
    $this->assertSame('inline_template', $build['#type']);
    $this->assertSame('Checkout', (string) $build['#context']['title']);
  }

  /**
   * @covers ::build
   */
  public function testReviewStepTitle(): void {
    $block = $this->makeBlock('commerce_checkout.form', 'review');
    $build = $block->build();

    $this->assertSame('Review your order', (string) $build['#context']['title']);
  }

  /**
   * @covers ::build
   */
  public function testCompleteStepTitle(): void {
    $block = $this->makeBlock('commerce_checkout.form', 'complete');
    $build = $block->build();

    $this->assertSame('Order confirmed', (string) $build['#context']['title']);
  }

  /**
   * @covers ::build
   */
  public function testBackLinkAbsentOnCompleteStep(): void {
    $block = $this->makeBlock('commerce_checkout.form', 'complete', show_back_link: TRUE);
    $build = $block->build();

    // Back link is always omitted on the complete step, regardless of config.
    $this->assertNull($build['#context']['back_url'], 'back_url must be NULL on the complete step.');
  }

  /**
   * @covers ::build
   */
  public function testBackLinkAbsentWhenConfigDisabled(): void {
    $block = $this->makeBlock('commerce_checkout.form', 'order_information', show_back_link: FALSE);
    $build = $block->build();

    $this->assertNull($build['#context']['back_url'], 'back_url must be NULL when show_back_link is FALSE.');
  }

  /**
   * @covers ::build
   */
  public function testUnknownStepFallsBackToOrderInformation(): void {
    // A step ID not in STEP_TITLES must fall back to the 'Checkout' title.
    $block = $this->makeBlock('commerce_checkout.form', 'unknown_step');
    $build = $block->build();

    $this->assertSame('Checkout', (string) $build['#context']['title']);
  }

  /**
   * @covers ::defaultConfiguration
   */
  public function testDefaultConfigurationShowBackLink(): void {
    $block = $this->makeBlock('commerce_checkout.form', 'order_information');
    $config = $block->defaultConfiguration();

    $this->assertTrue($config['show_back_link'], 'show_back_link defaults to TRUE.');
  }

}
