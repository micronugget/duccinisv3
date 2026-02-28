<?php

declare(strict_types=1);

namespace Drupal\Tests\store_fulfillment\Functional;

use Drupal\commerce_order\Entity\Order;
use Drupal\commerce_order\Entity\OrderItem;
use Drupal\commerce_price\Price;
use Drupal\commerce_product\Entity\Product;
use Drupal\commerce_product\Entity\ProductVariation;
use Drupal\Tests\commerce\Functional\CommerceBrowserTestBase;

/**
 * Tests store_fulfillment form_alter modifications to the payment pane.
 *
 * Covers issue #25: redundant payment headings removed, .checkout-section
 * class added, and "Payment method" sub-label hidden.
 *
 * @group store_fulfillment
 * @group payment
 */
class PaymentPaneFormAlterTest extends CommerceBrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'commerce_cart',
    'commerce_checkout',
    'commerce_payment',
    'commerce_payment_example',
    'commerce_product',
    'store_fulfillment',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * A default product variation for test orders.
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
      'administer commerce_checkout_flow',
      'administer commerce_order',
    ], parent::getAdministratorPermissions());
  }

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Create an onsite payment gateway so the PaymentInformation pane renders.
    $this->createEntity('commerce_payment_gateway', [
      'id' => 'example',
      'label' => 'Example',
      'plugin' => 'example_onsite',
    ]);

    // Create a product variation so orders can have items.
    $this->variation = ProductVariation::create([
      'type' => 'default',
      'sku' => 'TEST-SKU-001',
      'price' => new Price('9.99', 'USD'),
    ]);
    $this->variation->save();

    Product::create([
      'type' => 'default',
      'title' => 'Test Product',
      'variations' => [$this->variation],
      'stores' => [$this->store],
    ])->save();
  }

  /**
   * Tests that the payment_information wrapper gets the checkout-section class.
   *
   * The class is added by store_fulfillment_form_alter() so that the payment
   * pane adopts the same card styling as other checkout panes.
   *
   * @see store_fulfillment_form_alter()
   */
  public function testPaymentPaneHasCheckoutSectionClass(): void {
    // Build the checkout form and inspect the rendered payment pane element.
    $order = $this->createOrder();
    $this->drupalGet('/checkout/' . $order->id() . '/order_information');
    $this->assertSession()->statusCodeEquals(200);

    // The payment_information fieldset must carry the checkout-section class.
    $this->assertSession()->elementExists(
      'css',
      '#edit-payment-information.checkout-section',
    );
  }

  /**
   * Tests that the "Payment information" pane heading is visually hidden.
   *
   * The heading is suppressed via CSS (.checkout-pane > legend), not removed
   * from the DOM, so assistive technology still has access to it.
   */
  public function testPaymentPaneLegendInDom(): void {
    $order = $this->createOrder();
    $this->drupalGet('/checkout/' . $order->id() . '/order_information');
    $this->assertSession()->statusCodeEquals(200);

    // The legend must exist in the DOM (it is visually-hidden by CSS, not
    // removed server-side).
    $this->assertSession()->elementExists(
      'css',
      '#edit-payment-information legend',
    );
  }

  /**
   * Tests that no "Payment method" visible label renders above the radios.
   *
   * The #title_display = 'invisible' setting moves it to a visually-hidden
   * span so the label exists for accessibility but is not presented visually.
   */
  public function testPaymentMethodSubLabelIsInvisible(): void {
    $order = $this->createOrder();
    $this->drupalGet('/checkout/' . $order->id() . '/order_information');
    $this->assertSession()->statusCodeEquals(200);

    // Should NOT be a visible label element with text "Payment method" that
    // sits outside visually-hidden context.
    // The generated markup wraps the label in class="visually-hidden" when
    // #title_display = 'invisible'.
    $page = $this->getSession()->getPage();
    $label = $page->find('css', '.js-form-item-payment-information-payment-method > label:not(.visually-hidden)');
    $this->assertNull(
      $label,
      'A non-visually-hidden "Payment method" label should not exist.',
    );
  }

  /**
   * Creates a minimal draft order with one item for checkout testing.
   *
   * @return \Drupal\commerce_order\Entity\OrderInterface
   *   A saved draft order belonging to the current user that has items so the
   *   checkout route allows access.
   */
  protected function createOrder(): \Drupal\commerce_order\Entity\OrderInterface {
    $order_item = OrderItem::create([
      'type' => 'default',
      'purchased_entity' => $this->variation,
      'quantity' => 1,
      'unit_price' => new Price('9.99', 'USD'),
    ]);
    $order_item->save();

    /** @var \Drupal\commerce_order\Entity\Order $order */
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
