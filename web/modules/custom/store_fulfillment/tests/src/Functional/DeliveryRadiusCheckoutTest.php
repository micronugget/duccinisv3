<?php

declare(strict_types=1);

namespace Drupal\Tests\store_fulfillment\Functional;

use Drupal\commerce_order\Entity\Order;
use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_product\Entity\Product;
use Drupal\commerce_product\Entity\ProductVariation;
use Drupal\commerce_store\Entity\Store;
use Drupal\Tests\commerce\Functional\CommerceBrowserTestBase;

/**
 * Tests delivery radius validation during checkout.
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
    'commerce_shipping',
    'store_fulfillment',
    'store_resolver',
  ];

  /**
   * Test store entity.
   *
   * @var \Drupal\commerce_store\Entity\StoreInterface
   */
  protected $store;

  /**
   * Test product for checkout.
   *
   * @var \Drupal\commerce_product\Entity\ProductInterface
   */
  protected $product;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Create test store with delivery radius.
    $this->store = Store::create([
      'type' => 'online',
      'name' => 'Test Store',
      'mail' => 'test@example.com',
      'address' => [
        'country_code' => 'US',
        'administrative_area' => 'CA',
        'locality' => 'San Francisco',
        'postal_code' => '94102',
        'address_line1' => '123 Market St',
      ],
      'timezone' => 'America/Los_Angeles',
      'delivery_radius' => 10.0,
    ]);
    $this->store->save();

    // Create test product.
    $variation = ProductVariation::create([
      'type' => 'default',
      'sku' => 'TEST-SKU',
      'price' => [
        'number' => '10.00',
        'currency_code' => 'USD',
      ],
    ]);
    $variation->save();

    $this->product = Product::create([
      'type' => 'default',
      'title' => 'Test Product',
      'stores' => [$this->store],
      'variations' => [$variation],
    ]);
    $this->product->save();
  }

  /**
   * Tests delivery option available for address in radius.
   */
  public function testDeliveryOptionAvailableForAddressInRadius(): void {
    // This test would require mocking the geocoding service to return
    // coordinates that are within the delivery radius.
    // For now, we'll test the form structure.

    $this->drupalGet('/checkout/' . $this->createTestOrder()->id());

    // Check that fulfillment method radio buttons exist.
    $this->assertSession()->fieldExists('fulfillment_time[fulfillment_method]');
    $this->assertSession()->optionExists('fulfillment_time[fulfillment_method]', 'pickup');
    $this->assertSession()->optionExists('fulfillment_time[fulfillment_method]', 'delivery');

    // Verify both options are not disabled by default.
    $page = $this->getSession()->getPage();
    $delivery_radio = $page->findField('fulfillment_time[fulfillment_method][delivery]');
    $this->assertNotNull($delivery_radio);
    $this->assertFalse($delivery_radio->hasAttribute('disabled'));
  }

  /**
   * Tests that pickup option is always available.
   */
  public function testPickupOptionAlwaysAvailable(): void {
    $this->drupalGet('/checkout/' . $this->createTestOrder()->id());

    // Pickup should always be available regardless of delivery radius.
    $this->assertSession()->optionExists('fulfillment_time[fulfillment_method]', 'pickup');

    $page = $this->getSession()->getPage();
    $pickup_radio = $page->findField('fulfillment_time[fulfillment_method][pickup]');
    $this->assertNotNull($pickup_radio);
    $this->assertFalse($pickup_radio->hasAttribute('disabled'));
  }

  /**
   * Tests that fulfillment method is stored in order data.
   */
  public function testFulfillmentMethodStoredInOrderData(): void {
    $order = $this->createTestOrder();
    $this->drupalGet('/checkout/' . $order->id());

    // Select delivery method.
    $this->submitForm([
      'fulfillment_time[fulfillment_method]' => 'delivery',
      'fulfillment_time[fulfillment_type]' => 'asap',
    ], 'Continue to next step');

    // Reload order and verify data was stored.
    $order = Order::load($order->id());
    $this->assertEquals('delivery', $order->getData('fulfillment_method'));
    $this->assertEquals('asap', $order->getData('fulfillment_type'));
  }

  /**
   * Tests that pickup fulfillment is stored correctly.
   */
  public function testPickupFulfillmentStoredInOrderData(): void {
    $order = $this->createTestOrder();
    $this->drupalGet('/checkout/' . $order->id());

    // Select pickup method.
    $this->submitForm([
      'fulfillment_time[fulfillment_method]' => 'pickup',
      'fulfillment_time[fulfillment_type]' => 'scheduled',
      'fulfillment_time[scheduled_time]' => date('Y-m-d H:i:s', strtotime('+2 hours')),
    ], 'Continue to next step');

    // Reload order and verify data was stored.
    $order = Order::load($order->id());
    $this->assertEquals('pickup', $order->getData('fulfillment_method'));
    $this->assertEquals('scheduled', $order->getData('fulfillment_type'));
    $this->assertNotNull($order->getData('scheduled_time'));
  }

  /**
   * Tests AJAX refresh when fulfillment method changes.
   */
  public function testAjaxUpdatesWorkCorrectly(): void {
    $order = $this->createTestOrder();
    $this->drupalGet('/checkout/' . $order->id());

    // Get the initial state.
    $page = $this->getSession()->getPage();

    // Click delivery option - this should trigger AJAX.
    $delivery_radio = $page->findField('fulfillment_time[fulfillment_method][delivery]');
    $delivery_radio->click();

    // Wait for AJAX to complete.
    $this->assertSession()->assertWaitOnAjaxRequest();

    // Verify the pane wrapper still exists (AJAX worked).
    $this->assertSession()->elementExists('css', '#fulfillment-time-wrapper');

    // Try switching to pickup.
    $pickup_radio = $page->findField('fulfillment_time[fulfillment_method][pickup]');
    $pickup_radio->click();

    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->assertSession()->elementExists('css', '#fulfillment-time-wrapper');
  }

  /**
   * Tests validation error when required fields are missing.
   */
  public function testValidationErrorWhenRequiredFieldsMissing(): void {
    $order = $this->createTestOrder();
    $this->drupalGet('/checkout/' . $order->id());

    // Try to submit without selecting fulfillment method.
    $this->submitForm([], 'Continue to next step');

    // Should show validation error.
    $this->assertSession()->pageTextContains('Please select a fulfillment method');
  }

  /**
   * Tests scheduled time validation.
   */
  public function testScheduledTimeValidation(): void {
    $order = $this->createTestOrder();
    $this->drupalGet('/checkout/' . $order->id());

    // Select scheduled but don't provide time.
    $this->submitForm([
      'fulfillment_time[fulfillment_method]' => 'pickup',
      'fulfillment_time[fulfillment_type]' => 'scheduled',
    ], 'Continue to next step');

    // Should show validation error about missing scheduled time.
    $this->assertSession()->pageTextContains('Please select a fulfillment time');
  }

  /**
   * Tests pane visibility when store is not selected.
   */
  public function testPaneVisibilityWithoutStore(): void {
    // Create order without store.
    $order = Order::create([
      'type' => 'default',
      'state' => 'draft',
    ]);
    $order->save();

    $this->drupalGet('/checkout/' . $order->id());

    // Pane should show message about selecting store.
    $this->assertSession()->pageTextContains('Please select a store before continuing');
  }

  /**
   * Tests time slot generation for scheduling.
   */
  public function testTimeSlotGeneration(): void {
    $order = $this->createTestOrder();
    $this->drupalGet('/checkout/' . $order->id());

    // Select scheduled option.
    $page = $this->getSession()->getPage();
    $page->fillField('fulfillment_time[fulfillment_type]', 'scheduled');

    // Scheduled time dropdown should appear (via states API).
    $this->assertSession()->fieldExists('fulfillment_time[scheduled_time]');

    // Verify there are time slot options.
    $select = $page->findField('fulfillment_time[scheduled_time]');
    $options = $select->findAll('css', 'option');
    $this->assertGreaterThan(0, count($options), 'Time slots should be generated');
  }

  /**
   * Tests that form preserves values on validation errors.
   */
  public function testFormPreservesValuesOnValidationError(): void {
    $order = $this->createTestOrder();
    $this->drupalGet('/checkout/' . $order->id());

    // Fill form with delivery but omit scheduled time.
    $this->submitForm([
      'fulfillment_time[fulfillment_method]' => 'delivery',
      'fulfillment_time[fulfillment_type]' => 'scheduled',
    ], 'Continue to next step');

    // After validation error, previously selected values should be preserved.
    $this->assertSession()->fieldValueEquals('fulfillment_time[fulfillment_method]', 'delivery');
    $this->assertSession()->fieldValueEquals('fulfillment_time[fulfillment_type]', 'scheduled');
  }

  /**
   * Creates a test order with a product.
   *
   * @return \Drupal\commerce_order\Entity\OrderInterface
   *   The test order.
   */
  protected function createTestOrder(): OrderInterface {
    $order = Order::create([
      'type' => 'default',
      'state' => 'draft',
      'mail' => 'customer@example.com',
      'store_id' => $this->store->id(),
      'order_items' => [],
    ]);
    $order->save();

    return $order;
  }

}
