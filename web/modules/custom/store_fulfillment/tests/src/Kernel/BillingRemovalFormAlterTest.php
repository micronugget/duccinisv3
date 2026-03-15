<?php

declare(strict_types=1);

namespace Drupal\Tests\store_fulfillment\Kernel;

use Drupal\commerce_order\Entity\Order;
use Drupal\Core\Form\FormInterface;
use Drupal\Core\Form\FormState;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Tests\commerce\Kernel\CommerceKernelTestBase;

/**
 * Tests the billing_information removal in store_fulfillment_form_alter().
 *
 * Covers issue #107: port hook_form_alter billing-removal logic to V4.
 *
 * When fulfillment_method is 'delivery', the DeliveryAddress pane owns billing.
 * store_fulfillment_form_alter() must remove billing_information from the
 * PaymentInformation pane to prevent a duplicate billing form.
 *
 * Tests both detection paths:
 *   1. Fulfillment method read from $form_state->getValue().
 *   2. Fallback: read from $order->getData() via the form object.
 *
 * @group store_fulfillment
 * @group payment
 */
class BillingRemovalFormAlterTest extends CommerceKernelTestBase {

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
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('commerce_order');
    $this->installEntitySchema('commerce_order_item');
    $this->installConfig(['commerce_order']);
  }

  // ── Path 1: form_state value detection ───────────────────────────────────

  /**
   * Tests that billing_information is removed when form_state says 'delivery'.
   *
   * This covers the primary detection path: the FulfillmentTime AJAX callback
   * has already stored the selected method in form_state values before the
   * hook runs.
   *
   * @covers store_fulfillment_form_alter
   */
  public function testBillingRemovedWhenFormStateDelivery(): void {
    $form = $this->buildCheckoutForm();
    $form_state = new FormState();
    $form_state->setValue(['fulfillment_time', 'fulfillment_method'], 'delivery');

    store_fulfillment_form_alter($form, $form_state, 'commerce_checkout_flow_multistep_default');

    $this->assertArrayNotHasKey(
      'billing_information',
      $form['payment_information'],
      'billing_information must be removed from payment_information for delivery.',
    );
  }

  /**
   * Tests that billing_information is retained when form_state says 'pickup'.
   *
   * @covers store_fulfillment_form_alter
   */
  public function testBillingRetainedWhenFormStatePickup(): void {
    $form = $this->buildCheckoutForm();
    $form_state = new FormState();
    $form_state->setValue(['fulfillment_time', 'fulfillment_method'], 'pickup');

    store_fulfillment_form_alter($form, $form_state, 'commerce_checkout_flow_multistep_default');

    $this->assertArrayHasKey(
      'billing_information',
      $form['payment_information'],
      'billing_information must remain in payment_information for pickup.',
    );
  }

  // ── Path 2: order data fallback ───────────────────────────────────────────

  /**
   * Tests billing removal via order data fallback when form_state has no value.
   *
   * On initial page load, $form_state has no fulfillment_time values yet.
   * The hook falls back to $order->getData('fulfillment_method').
   *
   * @covers store_fulfillment_form_alter
   */
  public function testBillingRemovedViaOrderDataFallbackForDelivery(): void {
    $order = Order::create([
      'type' => 'default',
      'state' => 'draft',
      'uid' => 0,
      'store_id' => $this->store->id(),
    ]);
    $order->setData('fulfillment_method', 'delivery');
    $order->save();

    $form_state = new FormState();
    $form_state->setFormObject($this->buildFormObjectWithOrder($order));

    $form = $this->buildCheckoutForm();
    store_fulfillment_form_alter($form, $form_state, 'commerce_checkout_flow_multistep_default');

    $this->assertArrayNotHasKey(
      'billing_information',
      $form['payment_information'],
      'billing_information must be removed when order data has fulfillment_method=delivery.',
    );
  }

  /**
   * Tests billing is retained via order data fallback for pickup.
   *
   * @covers store_fulfillment_form_alter
   */
  public function testBillingRetainedViaOrderDataFallbackForPickup(): void {
    $order = Order::create([
      'type' => 'default',
      'state' => 'draft',
      'uid' => 0,
      'store_id' => $this->store->id(),
    ]);
    $order->setData('fulfillment_method', 'pickup');
    $order->save();

    $form_state = new FormState();
    $form_state->setFormObject($this->buildFormObjectWithOrder($order));

    $form = $this->buildCheckoutForm();
    store_fulfillment_form_alter($form, $form_state, 'commerce_checkout_flow_multistep_default');

    $this->assertArrayHasKey(
      'billing_information',
      $form['payment_information'],
      'billing_information must remain when order data has fulfillment_method=pickup.',
    );
  }

  // ── Edge case: non-checkout form ──────────────────────────────────────────

  /**
   * Tests that non-checkout form IDs are not processed.
   *
   * The hook early-returns if form_id does not start with
   * 'commerce_checkout_flow', leaving billing_information untouched.
   *
   * @covers store_fulfillment_form_alter
   */
  public function testNonCheckoutFormIsUnchanged(): void {
    $form = $this->buildCheckoutForm();
    $form_state = new FormState();
    $form_state->setValue(['fulfillment_time', 'fulfillment_method'], 'delivery');

    store_fulfillment_form_alter($form, $form_state, 'some_other_form_id');

    $this->assertArrayHasKey(
      'billing_information',
      $form['payment_information'],
      'billing_information must not be changed for non-checkout forms.',
    );
  }

  // ── Helpers ───────────────────────────────────────────────────────────────

  /**
   * Builds a minimal checkout form array that includes billing_information.
   *
   * Contains only the payment_information element plus billing_information —
   * sufficient to exercise the billing-removal branch of the hook without
   * triggering the saved-card or payment_method sections.
   *
   * @return array
   *   A form array mimicking the PaymentInformation pane structure.
   */
  protected function buildCheckoutForm(): array {
    return [
      'payment_information' => [
        '#type' => 'fieldset',
        '#attributes' => [],
        'billing_information' => [
          '#type' => 'container',
          '#markup' => 'Billing address fields placeholder',
        ],
      ],
    ];
  }

  /**
   * Builds a FormInterface stub whose getOrder() returns the given order.
   *
   * The hook checks method_exists($form_object, 'getOrder') and calls it
   * when form_state has no fulfillment_time value. This anonymous class
   * provides the minimal interface required by FormState::setFormObject().
   *
   * @param \Drupal\commerce_order\Entity\Order $order
   *   The order to return from getOrder().
   *
   * @return \Drupal\Core\Form\FormInterface
   *   A form object stub with a getOrder() method.
   */
  protected function buildFormObjectWithOrder(Order $order): FormInterface {
    return new class($order) implements FormInterface {

      public function __construct(private readonly Order $order) {}

      /**
       * {@inheritdoc}
       */
      public function getFormId(): string {
        return 'test_checkout_form';
      }

      /**
       * {@inheritdoc}
       */
      public function buildForm(array $form, FormStateInterface $form_state): array {
        return $form;
      }

      /**
       * {@inheritdoc}
       */
      public function validateForm(array &$form, FormStateInterface $form_state): void {}

      /**
       * {@inheritdoc}
       */
      public function submitForm(array &$form, FormStateInterface $form_state): void {}

      /**
       * Returns the order; used by the hook order-data fallback path.
       *
       * @return \Drupal\commerce_order\Entity\Order
       *   The test order.
       */
      public function getOrder(): Order {
        return $this->order;
      }

    };
  }

}
