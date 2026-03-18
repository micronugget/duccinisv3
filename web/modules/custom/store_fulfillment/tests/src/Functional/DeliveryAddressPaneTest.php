<?php

declare(strict_types=1);

namespace Drupal\Tests\store_fulfillment\Functional;

use Drupal\commerce_order\Entity\Order;
use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_order\Entity\OrderItem;
use Drupal\commerce_price\Price;
use Drupal\commerce_product\Entity\Product;
use Drupal\commerce_product\Entity\ProductVariation;
use Drupal\Tests\commerce\Functional\CommerceBrowserTestBase;

/**
 * Tests the DeliveryAddress checkout pane renders and behaves correctly.
 *
 * Covers:
 * - Pane renders as a hidden placeholder when pickup is selected.
 * - Pane renders as a full address form when delivery is selected.
 * - "Billing same as delivery" checkbox is present and checked by default.
 *
 * These are non-JS functional tests. AJAX toggling is simulated by
 * pre-setting order data before loading the page, because isDeliverySelected()
 * falls back to $order->getData('fulfillment_method') on initial GET requests.
 *
 * @group store_fulfillment
 */
class DeliveryAddressPaneTest extends CommerceBrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'commerce_cart',
    'commerce_checkout',
    'commerce_product',
    'commerce_shipping',
    'store_fulfillment',
    'store_resolver',
  ];

  /**
   * Default theme — must be stark for functional tests.
   *
   * @var string
   */
  protected $defaultTheme = 'stark';

  /**
   * Reusable product variation for order items.
   *
   * @var \Drupal\commerce_product\Entity\ProductVariation
   */
  protected ProductVariation $variation;

  /**
   * {@inheritdoc}
   */
  protected function getAdministratorPermissions(): array {
    return array_merge([
      'access checkout',
      'administer commerce_order',
    ], parent::getAdministratorPermissions());
  }

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Give the parent-created store a delivery radius so the FulfillmentTime
    // pane can read it.
    $this->store->set('delivery_radius', 10.0);
    $this->store->save();

    $this->variation = ProductVariation::create([
      'type' => 'default',
      'sku' => 'DA-TEST-SKU',
      'price' => new Price('12.00', 'USD'),
    ]);
    $this->variation->save();

    Product::create([
      'type' => 'default',
      'title' => 'Test Product',
      'stores' => [$this->store],
      'variations' => [$this->variation],
    ])->save();

    // Disable the billing_information pane so it cannot block form rendering
    // with its own required address fields.
    /** @var \Drupal\commerce_checkout\Entity\CheckoutFlowInterface $checkout_flow */
    $checkout_flow = \Drupal::entityTypeManager()
      ->getStorage('commerce_checkout_flow')
      ->load('default');
    if ($checkout_flow) {
      /** @var \Drupal\commerce_checkout\Plugin\Commerce\CheckoutFlow\CheckoutFlowWithPanesBase $plugin */
      $plugin = $checkout_flow->getPlugin();
      $configuration = $plugin->getConfiguration();
      $configuration['panes']['billing_information']['step'] = '_disabled';
      $plugin->setConfiguration($configuration);
      $checkout_flow->save();
    }
  }

  /**
   * Tests delivery address wrapper is in DOM but collapsed for pickup.
   *
   * The wrapper must remain in the DOM for AJAX targeting even when hidden.
   * When pickup is selected, the pane renders an empty placeholder, the
   * wrapper carries no 'open' class, and aria-expanded is "false".
   */
  public function testDeliveryAddressPaneHiddenForPickup(): void {
    $order = $this->createTestOrder();
    // No fulfillment_method set — isDeliverySelected() returns FALSE (defaults
    // to pickup).
    $this->drupalGet('/checkout/' . $order->id() . '/order_information');
    $this->assertSession()->statusCodeEquals(200);

    // The AJAX wrapper must always be present in the DOM.
    $this->assertSession()->elementExists('css', '#delivery-address-wrapper');

    // Wrapper must NOT have the 'open' class when pickup is selected.
    $this->assertSession()->elementNotExists('css', '#delivery-address-wrapper.open');

    // aria-expanded must be "false" for accessibility.
    $page = $this->getSession()->getPage();
    $wrapper = $page->find('css', '#delivery-address-wrapper');
    $this->assertNotNull($wrapper, 'Delivery address wrapper must exist.');
    $this->assertEquals(
      'false',
      $wrapper->getAttribute('aria-expanded'),
      'aria-expanded must be "false" when pickup is selected.',
    );

    // No address profile form inputs should be rendered in the collapsed pane.
    $address_inputs = $wrapper->findAll(
      'css',
      'input[name*="delivery_address[profile]"]',
    );
    $this->assertEmpty(
      $address_inputs,
      'No address profile inputs should render when pane is collapsed.',
    );
  }

  /**
   * Tests delivery address wrapper is expanded and contains form for delivery.
   *
   * When the order already has 'delivery' stored as fulfillment_method,
   * isDeliverySelected() returns TRUE on the initial GET request, and the
   * pane renders the full address form with the 'open' CSS class.
   */
  public function testDeliveryAddressPaneShowsForDelivery(): void {
    $order = $this->createTestOrder();
    // Pre-set the fulfillment method to delivery to simulate the AJAX state
    // where the user has selected delivery.
    $order->setData('fulfillment_method', 'delivery');
    $order->save();

    $this->drupalGet('/checkout/' . $order->id() . '/order_information');
    $this->assertSession()->statusCodeEquals(200);

    // Wrapper must exist.
    $this->assertSession()->elementExists('css', '#delivery-address-wrapper');

    // Wrapper must have the 'open' class when delivery is selected.
    $this->assertSession()->elementExists('css', '#delivery-address-wrapper.open');

    // aria-expanded must be "true" for accessibility.
    $page = $this->getSession()->getPage();
    $wrapper = $page->find('css', '#delivery-address-wrapper');
    $this->assertNotNull($wrapper, 'Delivery address wrapper must exist.');
    $this->assertEquals(
      'true',
      $wrapper->getAttribute('aria-expanded'),
      'aria-expanded must be "true" when delivery is selected.',
    );

    // The fulfillment_method radio must show delivery as selected in the
    // FulfillmentTime pane (driven by the same order data).
    $delivery_radio = $page->find(
      'css',
      'input[type="radio"][name="fulfillment_time[fulfillment_method]"][value="delivery"]',
    );
    $this->assertNotNull($delivery_radio, 'Delivery radio must exist.');
    $this->assertTrue(
      $delivery_radio->isChecked(),
      'Delivery radio must be pre-checked when fulfillment_method is delivery.',
    );

    // The pane renders the copy_to_billing checkbox in the full form,
    // confirming the address form section was built.
    $this->assertSession()->fieldExists('delivery_address[copy_to_billing]');
  }

  /**
   * Tests "billing same as delivery" checkbox default state.
   *
   * When delivery is selected, the pane renders a "Same address for billing"
   * checkbox. Its default value is TRUE (checked) — meaning the delivery
   * address will be copied to billing unless the user unchecks it.
   */
  public function testBillingSameAsDeliveryCheckboxDefault(): void {
    $order = $this->createTestOrder();
    $order->setData('fulfillment_method', 'delivery');
    $order->save();

    $this->drupalGet('/checkout/' . $order->id() . '/order_information');
    $this->assertSession()->statusCodeEquals(200);

    // The checkbox must exist within the delivery address pane.
    $this->assertSession()->fieldExists('delivery_address[copy_to_billing]');

    $page = $this->getSession()->getPage();
    $checkbox = $page->findField('delivery_address[copy_to_billing]');
    $this->assertNotNull($checkbox, '"Same address for billing" checkbox must exist.');

    // The checkbox must be checked by default so billing is synced unless
    // the user explicitly opts out.
    $this->assertTrue(
      $checkbox->isChecked(),
      '"Same address for billing" checkbox must be checked by default.',
    );

    // The separate billing address form must not be visible when the
    // "same as billing" checkbox is checked. Drupal #states hides
    // billing_profile when copy_to_billing is TRUE.
    // The container is rendered in the DOM (for JS #states toggling), but
    // the #states declaration drives its CSS visibility — we assert the
    // container exists in the markup.
    $this->assertSession()->elementExists(
      'css',
      '#delivery-address-wrapper [data-drupal-states]',
    );
  }

  /**
   * Tests that the delivery address wrapper id is stable for AJAX re-targeting.
   *
   * The FulfillmentTime pane's AJAX callback issues a ReplaceCommand targeting
   * '#delivery-address-wrapper'. The id must be present both when the pane is
   * collapsed (pickup) and when it is expanded (delivery) so that AJAX can
   * swap the content on any toggling event.
   */
  public function testDeliveryAddressWrapperIdStableAcrossStates(): void {
    $order = $this->createTestOrder();

    // Pickup state.
    $this->drupalGet('/checkout/' . $order->id() . '/order_information');
    $this->assertSession()->elementAttributeContains(
      'css',
      '#delivery-address-wrapper',
      'id',
      'delivery-address-wrapper',
    );

    // Delivery state (simulate via order data).
    // Reload from DB: the drupalGet() may have bumped the order version.
    $order = Order::load($order->id());
    $order->setData('fulfillment_method', 'delivery');
    $order->save();
    $this->drupalGet('/checkout/' . $order->id() . '/order_information');
    $this->assertSession()->elementAttributeContains(
      'css',
      '#delivery-address-wrapper',
      'id',
      'delivery-address-wrapper',
    );
  }

  /**
   * Creates a minimal draft order with one item belonging to the admin user.
   *
   * @return \Drupal\commerce_order\Entity\OrderInterface
   *   A saved draft order ready for checkout.
   */
  protected function createTestOrder(): OrderInterface {
    $order_item = OrderItem::create([
      'type' => 'default',
      'purchased_entity' => $this->variation,
      'quantity' => 1,
      'unit_price' => new Price('12.00', 'USD'),
    ]);
    $order_item->save();

    $order = Order::create([
      'type' => 'default',
      'state' => 'draft',
      'mail' => $this->adminUser->getEmail(),
      'uid' => $this->adminUser->id(),
      'store_id' => $this->store->id(),
      'order_items' => [$order_item],
    ]);
    $order->save();

    return $order;
  }

}
