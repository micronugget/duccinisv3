<?php

declare(strict_types=1);

namespace Drupal\Tests\store_fulfillment\Functional;

use Drupal\commerce_order\Entity\OrderInterface;
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

  // ─── Issue #106: #after_build regression tests ──────────────────────────

  /**
   * Tests that saved-card radio inputs exist in the DOM when the user has a stored payment method.
   *
   * This is the primary regression test for issue #106.  The original bug: the
   * module used #process instead of #after_build.  Using #process overwrote
   * Drupal's default #process array (including Radios::processRadios()) and
   * produced ZERO child radio elements — the radio list appeared empty.  The
   * fix registers our callback via #after_build so Radios::processRadios()
   * always runs first.
   *
   * If this test fails with "zero radio inputs found", the #after_build →
   * #process regression has been reintroduced.
   */
  public function testSavedCardRadioInputsExistInDom(): void {
    $this->createSavedPaymentMethodForAdminUser();
    $order = $this->createOrder();
    $this->drupalGet('/checkout/' . $order->id() . '/order_information');
    $this->assertSession()->statusCodeEquals(200);

    $radios = $this->getSession()->getPage()->findAll(
      'css',
      '#edit-payment-information input[type="radio"]',
    );
    $this->assertNotEmpty(
      $radios,
      'At least one radio input must be rendered inside #edit-payment-information. '
      . 'Zero radios means Radios::processRadios() did not run — a sign that #process '
      . 'was used instead of #after_build (issue #106 regression).',
    );
  }

  /**
   * Tests that the payment_information wrapper receives the has-saved-cards class when the user has a stored payment method.
   *
   * This class is added by store_fulfillment_form_alter() when the saved-card
   * detection loop finds at least one eligible payment method.  Its presence
   * proves the loop ran and that #after_build was registered.
   */
  public function testPaymentPaneHasSavedCardsClassWhenSavedMethodExists(): void {
    $this->createSavedPaymentMethodForAdminUser();
    $order = $this->createOrder();
    $this->drupalGet('/checkout/' . $order->id() . '/order_information');
    $this->assertSession()->statusCodeEquals(200);

    $this->assertSession()->elementExists(
      'css',
      '#edit-payment-information.has-saved-cards',
    );
  }

  /**
   * Tests that saved-card radio inputs carry the visually-hidden CSS class.
   *
   * The #after_build callback adds this class to hide the native
   * input[type="radio"] so the styled SDC label row is the visual affordance.
   * Its presence confirms the callback ran after Radios::processRadios().
   */
  public function testSavedCardRadioInputsHaveVisuallyHiddenClass(): void {
    $this->createSavedPaymentMethodForAdminUser();
    $order = $this->createOrder();
    $this->drupalGet('/checkout/' . $order->id() . '/order_information');
    $this->assertSession()->statusCodeEquals(200);

    $this->assertSession()->elementExists(
      'css',
      '#edit-payment-information input[type="radio"].visually-hidden',
    );
  }

  /**
   * Tests the payment pane lacks has-saved-cards class with no stored methods.
   *
   * Control case: without saved methods the #after_build callback is never
   * registered, so the class must not appear.
   */
  public function testPaymentPaneLacksSavedCardsClassWithNoSavedMethods(): void {
    // No saved payment method created.
    $order = $this->createOrder();
    $this->drupalGet('/checkout/' . $order->id() . '/order_information');
    $this->assertSession()->statusCodeEquals(200);

    $this->assertSession()->elementNotExists(
      'css',
      '#edit-payment-information.has-saved-cards',
    );
  }

  // ─── Helpers ────────────────────────────────────────────────────────────

  /**
   * Creates a reusable credit-card payment method owned by the admin user.
   *
   * Uses the 'example' gateway (example_onsite plugin) from setUp().  The
   * card_type field triggers the Commerce-core branch in store_fulfillment
   * form_alter so that #saved_card_data is populated and #after_build is
   * registered on the radios element.
   *
   * A billing profile with a US address is required because the test store has
   * billing_countries = ['US'] and Commerce's loadReusable() filters out any
   * payment method whose billing profile country does not match.
   */
  protected function createSavedPaymentMethodForAdminUser(): void {
    // Create a billing profile with a US address to satisfy the store's
    // billing_countries filter in PaymentMethodStorage::loadReusable().
    $billing_profile = $this->createEntity('profile', [
      'type' => 'customer',
      'uid' => $this->adminUser->id(),
      'address' => [
        'country_code' => 'US',
        'administrative_area' => 'DC',
        'locality' => 'Washington',
        'postal_code' => '20009',
        'address_line1' => '1800 Adams Mill Rd NW',
        'given_name' => 'Test',
        'family_name' => 'User',
      ],
    ]);

    $payment_method = $this->createEntity('commerce_payment_method', [
      'type' => 'credit_card',
      'uid' => $this->adminUser->id(),
      'payment_gateway' => 'example',
      'card_type' => 'visa',
      'card_number' => '1111',
      'billing_profile' => $billing_profile,
      'remote_id' => 789,
      'reusable' => TRUE,
      'expires' => strtotime('+5 years'),
    ]);
    $payment_method->setBillingProfile($billing_profile);
    $payment_method->save();
  }

  /**
   * Creates a minimal draft order with one item for checkout testing.
   *
   * @return \Drupal\commerce_order\Entity\OrderInterface
   *   A saved draft order belonging to the current user that has items so the
   *   checkout route allows access.
   */
  protected function createOrder(): OrderInterface {
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
