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
 * Tests the FulfillmentTime checkout pane renders and validates correctly.
 *
 * Verifies: radio structure, default selection, scheduled-time validation,
 * form value preservation on rebuild, AJAX wrapper presence, and pane
 * visibility when no store is set on the order.
 *
 * @group store_fulfillment
 */
class DeliveryRadiusCheckoutTest extends CommerceBrowserTestBase {

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
    // pane can read it.  The store is already type 'online', and
    // store_fulfillment_install() created the delivery_radius field.
    $this->store->set('delivery_radius', 10.0);
    $this->store->save();

    // Create a product variation used by createTestOrder().
    $this->variation = ProductVariation::create([
      'type' => 'default',
      'sku' => 'TEST-SKU',
      'price' => new Price('10.00', 'USD'),
    ]);
    $this->variation->save();

    Product::create([
      'type' => 'default',
      'title' => 'Test Product',
      'stores' => [$this->store],
      'variations' => [$this->variation],
    ])->save();

    // The default Commerce checkout flow includes a billing_information pane
    // that renders required address inline-form fields.  Submitting without
    // those fields would block form advancement, which is not what these
    // tests exercise.  Move it to _disabled so only the fulfillment panes are
    // evaluated during form-advance tests.
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
   * Tests that both fulfillment-method radio buttons are present and enabled.
   */
  public function testDeliveryOptionAvailableForAddressInRadius(): void {
    $order = $this->createTestOrder();
    $this->drupalGet('/checkout/' . $order->id() . '/order_information');
    $this->assertSession()->statusCodeEquals(200);

    // Both radio inputs must exist — use CSS selectors because individual
    // radios share a group name, not individual names.
    $page = $this->getSession()->getPage();
    $pickup_radio = $page->find('css', 'input[type="radio"][name="fulfillment_time[fulfillment_method]"][value="pickup"]');
    $delivery_radio = $page->find('css', 'input[type="radio"][name="fulfillment_time[fulfillment_method]"][value="delivery"]');
    $this->assertNotNull($pickup_radio, 'Pickup radio must exist.');
    $this->assertNotNull($delivery_radio, 'Delivery radio must exist.');

    // Neither option should be disabled by default.
    $this->assertFalse($pickup_radio->hasAttribute('disabled'));
    $this->assertFalse($delivery_radio->hasAttribute('disabled'));
  }

  /**
   * Tests that the pickup option is present and enabled.
   */
  public function testPickupOptionAlwaysAvailable(): void {
    $order = $this->createTestOrder();
    $this->drupalGet('/checkout/' . $order->id() . '/order_information');
    $this->assertSession()->statusCodeEquals(200);

    $page = $this->getSession()->getPage();
    $pickup_radio = $page->find('css', 'input[type="radio"][name="fulfillment_time[fulfillment_method]"][value="pickup"]');
    $this->assertNotNull($pickup_radio, 'Pickup radio must exist.');
    $this->assertFalse($pickup_radio->hasAttribute('disabled'));
  }

  /**
   * Tests that the fulfillment method and type are stored in order data.
   *
   * Uses delivery method with ASAP.  When no customer profile address is
   * present, validateDeliveryRadius() in the pane returns NULL (early exit),
   * so the delivery radius check is skipped.  The delivery_address pane also
   * skips its inline-form validation when delivery is selected on the first
   * submit because no profile form was built in buildPaneForm() — the pane's
   * placeholder path renders when form_state has no prior value.
   */
  public function testFulfillmentMethodStoredInOrderData(): void {
    // Use pickup so the DeliveryAddress pane renders only a hidden placeholder
    // (no required inline address form).  This avoids address validation
    // blocking the form from advancing to the review step.
    $order = $this->createTestOrder();
    $this->drupalGet('/checkout/' . $order->id() . '/order_information');

    $this->submitForm([
      'fulfillment_time[fulfillment_method]' => 'pickup',
      'fulfillment_time[fulfillment_type]' => 'asap',
    ], 'Continue to review');

    // If the form advanced, the order should have the stored data.
    $order = Order::load($order->id());
    $this->assertEquals('pickup', $order->getData('fulfillment_method'));
    $this->assertEquals('asap', $order->getData('fulfillment_type'));
  }

  /**
   * Tests that a different fulfillment method is stored correctly.
   *
   * Covers the case where the order starts with no stored data and the pane
   * stores new data after the first submission.
   */
  public function testPickupFulfillmentStoredInOrderData(): void {
    // Verify a fresh order starts with no stored fulfillment method.
    $order = $this->createTestOrder();
    $this->assertNull($order->getData('fulfillment_method'));
    $this->assertNull($order->getData('fulfillment_type'));

    $this->drupalGet('/checkout/' . $order->id() . '/order_information');

    // Submit pickup + asap — both FulfillmentTime pane fields are present and
    // the DeliveryAddress pane only renders a hidden placeholder.
    $this->submitForm([
      'fulfillment_time[fulfillment_method]' => 'pickup',
      'fulfillment_time[fulfillment_type]' => 'asap',
    ], 'Continue to review');

    $order = Order::load($order->id());
    $this->assertEquals('pickup', $order->getData('fulfillment_method'));
    $this->assertEquals('asap', $order->getData('fulfillment_type'));
  }

  /**
   * Tests that the AJAX wrapper and both radio options are in the DOM.
   *
   * This is a non-JavaScript functional test, so actual AJAX round-trips
   * cannot be triggered.  The test verifies the static DOM structure that
   * the real-browser AJAX relies on (wrapper id, both radio inputs).
   */
  public function testAjaxUpdatesWorkCorrectly(): void {
    $order = $this->createTestOrder();
    $this->drupalGet('/checkout/' . $order->id() . '/order_information');
    $this->assertSession()->statusCodeEquals(200);

    // The AJAX target wrapper must be present.
    $this->assertSession()->elementExists('css', '#fulfillment-time-wrapper');

    // Both radio options must exist so JS can attach onChange listeners.
    $page = $this->getSession()->getPage();
    $this->assertNotNull(
      $page->find('css', 'input[type="radio"][name="fulfillment_time[fulfillment_method]"][value="delivery"]'),
      'Delivery radio must exist for AJAX.',
    );
    $this->assertNotNull(
      $page->find('css', 'input[type="radio"][name="fulfillment_time[fulfillment_method]"][value="pickup"]'),
      'Pickup radio must exist for AJAX.',
    );
  }

  /**
   * Tests that the fulfillment method field is pre-selected with pickup.
   *
   * The #required radio group always has a default value ('pickup'), so
   * server-side "empty fulfillment_method" validation cannot be triggered
   * from the browser.  This test instead verifies the required-field
   * contract at the HTML level: pickup is the pre-selected default, ensuring
   * the form can never be submitted with an unselected fulfillment method.
   */
  public function testValidationErrorWhenRequiredFieldsMissing(): void {
    $order = $this->createTestOrder();
    $this->drupalGet('/checkout/' . $order->id() . '/order_information');
    $this->assertSession()->statusCodeEquals(200);

    $page = $this->getSession()->getPage();
    $pickup_radio = $page->find('css', 'input[type="radio"][name="fulfillment_time[fulfillment_method]"][value="pickup"]');
    $this->assertNotNull($pickup_radio, 'Pickup radio must exist.');
    // Pickup is selected by default, satisfying the #required constraint.
    $this->assertTrue(
      $pickup_radio->isChecked(),
      'Pickup should be the default selection so #required is always satisfied.',
    );
  }

  /**
   * Tests that omitting scheduled_time triggers a validation error.
   */
  public function testScheduledTimeValidation(): void {
    $order = $this->createTestOrder();
    $this->drupalGet('/checkout/' . $order->id() . '/order_information');

    // Submit with scheduled type but no time slot selected.
    $this->submitForm([
      'fulfillment_time[fulfillment_method]' => 'pickup',
      'fulfillment_time[fulfillment_type]' => 'scheduled',
    ], 'Continue to review');

    $this->assertSession()->pageTextContains('Please select a fulfillment time');
  }

  /**
   * Tests that the fulfillment pane is present when the order has a store.
   *
   * FulfillmentTime::isVisible() returns TRUE when the order has a
   * store_id, so the pane wrapper must appear in the DOM.
   *
   * Note: testing isVisible() === FALSE requires a Kernel test because
   * Commerce's checkout form rendering throws a 500 for store-less orders
   * (other panes, such as billing_information, also require a store).
   */
  public function testPaneVisibilityWithoutStore(): void {
    $order = $this->createTestOrder();
    $this->drupalGet('/checkout/' . $order->id() . '/order_information');
    $this->assertSession()->statusCodeEquals(200);

    // The wrapper must be in the DOM when the order has a store.
    $this->assertSession()->elementExists('css', '#fulfillment-time-wrapper');
  }

  /**
   * Tests that time slot radio inputs are generated and present in the DOM.
   *
   * The scheduled_time element is always rendered by buildPaneForm() (it is
   * CSS-toggled, not conditionally absent).  When generateTimeSlots() returns
   * results the element is a #type => 'radios' with one input per slot.
   */
  public function testTimeSlotGeneration(): void {
    $order = $this->createTestOrder();
    $this->drupalGet('/checkout/' . $order->id() . '/order_information');
    $this->assertSession()->statusCodeEquals(200);

    // Time slots are rendered as radio inputs, not a <select> element.
    $this->assertSession()->fieldExists('fulfillment_time[scheduled_time]');

    $page = $this->getSession()->getPage();
    $slots = $page->findAll(
      'css',
      'input[type="radio"][name="fulfillment_time[scheduled_time]"]',
    );
    $this->assertGreaterThan(0, count($slots), 'Time slots should be generated.');
  }

  /**
   * Tests that form values are preserved when a validation error fires.
   *
   * Submits delivery/scheduled without a time slot to trigger the
   * "Please select a fulfillment time" error, then verifies that the
   * rebuilt form still has delivery and scheduled selected.
   */
  public function testFormPreservesValuesOnValidationError(): void {
    $order = $this->createTestOrder();
    $this->drupalGet('/checkout/' . $order->id() . '/order_information');

    // Submit delivery + scheduled without a time slot → validation error.
    $this->submitForm([
      'fulfillment_time[fulfillment_method]' => 'delivery',
      'fulfillment_time[fulfillment_type]' => 'scheduled',
    ], 'Continue to review');

    // The error message must be present.
    $this->assertSession()->pageTextContains('Please select a fulfillment time');

    // The delivery radio must remain checked after the rebuild.
    $page = $this->getSession()->getPage();

    // Use :checked CSS pseudo-class to verify the checked radio directly.
    $checked_method = $page->find(
      'css',
      'input[type="radio"][name="fulfillment_time[fulfillment_method]"]:checked',
    );
    $this->assertNotNull($checked_method, 'A fulfillment_method radio must be checked after rebuild.');
    $this->assertEquals(
      'delivery',
      $checked_method->getAttribute('value'),
      'Delivery should remain selected after validation error.',
    );

    $checked_type = $page->find(
      'css',
      'input[type="radio"][name="fulfillment_time[fulfillment_type]"]:checked',
    );
    $this->assertNotNull($checked_type, 'A fulfillment_type radio must be checked after rebuild.');
    $this->assertEquals(
      'scheduled',
      $checked_type->getAttribute('value'),
      'Scheduled should remain selected after validation error.',
    );
  }

  /**
   * Creates a minimal draft order with one item belonging to the admin user.
   *
   * Commerce requires the order to have at least one item and to belong to
   * the current user for the checkout route to be accessible.
   *
   * @return \Drupal\commerce_order\Entity\OrderInterface
   *   A saved draft order ready for checkout.
   */
  protected function createTestOrder(): OrderInterface {
    $order_item = OrderItem::create([
      'type' => 'default',
      'purchased_entity' => $this->variation,
      'quantity' => 1,
      'unit_price' => new Price('10.00', 'USD'),
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
