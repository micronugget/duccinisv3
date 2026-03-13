<?php

declare(strict_types=1);

namespace Drupal\Tests\store_fulfillment\Kernel;

use Drupal\Core\Form\FormState;
use Drupal\Tests\commerce\Kernel\CommerceKernelTestBase;

/**
 * Unit-style tests for store_fulfillment_payment_radios_after_build().
 *
 * Issue #106: the original bug was that #process was used instead of
 * #after_build, which overwrote Drupal's default #process array (including
 * Radios::processRadios()) and produced zero child radio elements.
 *
 * These tests call the global callback function directly with synthetic element
 * arrays so they run fast without Commerce entity overhead.
 *
 * @group store_fulfillment
 * @group payment
 */
class PaymentRadiosAfterBuildTest extends CommerceKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'profile',
    'state_machine',
    'entity_reference_revisions',
    'geofield',
    'geocoder',
    'store_resolver',
    'store_fulfillment',
  ];

  // ─── Helpers ──────────────────────────────────────────────────────────────

  /**
   * Builds a minimal radios element mimicking what Radios::processRadios() produces.
   *
   * Creates a parent element with keyed child elements for each option.
   *
   * @param array $saved_cards
   *   The value to set as $element['#saved_card_data'].
   * @param array $options
   *   The #options array (same keys used to create child elements).
   *
   * @return array
   *   A synthetic radios element ready to pass to the after_build callback.
   */
  private function buildElement(array $saved_cards, array $options): array {
    $element = [
      '#type' => 'radios',
      '#options' => $options,
      '#saved_card_data' => $saved_cards,
    ];
    // Mimic the per-option children Radios::processRadios() would create.
    foreach (array_keys($options) as $key) {
      $element[$key] = [
        '#type' => 'radio',
        '#attributes' => ['class' => []],
      ];
    }
    return $element;
  }

  // ─── Callback behaviour ───────────────────────────────────────────────────

  /**
   * Tests that #card_data is stamped onto a numeric child element.
   */
  public function testCallbackStampsCardDataOnNumericChild(): void {
    $card = ['brand' => 'visa', 'last4' => '4242', 'expMonth' => '12', 'expYear' => '2030'];
    $element = $this->buildElement(
      ['1' => $card],
      ['1' => 'Visa ending in 4242'],
    );

    $result = store_fulfillment_payment_radios_after_build($element, new FormState());

    $this->assertSame(
      $card,
      $result['1']['#card_data'],
      '#card_data must be set on the child radio element matching the saved-card ID.',
    );
  }

  /**
   * Tests that the visually-hidden class is added to saved-card radio inputs.
   */
  public function testCallbackAddsVisuallyHiddenClassToSavedCardRadio(): void {
    $element = $this->buildElement(
      ['2' => ['brand' => 'mastercard', 'last4' => '0000', 'expMonth' => '01', 'expYear' => '2029']],
      ['2' => 'Mastercard ending in 0000'],
    );

    $result = store_fulfillment_payment_radios_after_build($element, new FormState());

    $this->assertContains(
      'visually-hidden',
      $result['2']['#attributes']['class'],
      'visually-hidden CSS class must be added to the saved-card radio input.',
    );
  }

  /**
   * Tests that the saved-card__radio class is added to saved-card radio inputs.
   */
  public function testCallbackAddsSavedCardRadioClass(): void {
    $element = $this->buildElement(
      ['3' => ['brand' => 'amex', 'last4' => '1234', 'expMonth' => '06', 'expYear' => '2028']],
      ['3' => 'Amex ending in 1234'],
    );

    $result = store_fulfillment_payment_radios_after_build($element, new FormState());

    $this->assertContains(
      'saved-card__radio',
      $result['3']['#attributes']['class'],
      'saved-card__radio CSS class must be added to the saved-card radio input.',
    );
  }

  /**
   * Tests that a non-numeric option key is marked as the "add new card" option.
   *
   * Commerce uses the string key 'add_new' (or the payment gateway ID) for the
   * "Use a different card" option.  The callback must detect non-numeric keys
   * and stamp #is_new_card_option = TRUE plus the two CSS classes.
   */
  public function testCallbackMarksNonNumericOptionAsNewCard(): void {
    $element = $this->buildElement(
      [],
      ['add_new' => '+ Use a different card'],
    );

    $result = store_fulfillment_payment_radios_after_build($element, new FormState());

    $this->assertTrue(
      $result['add_new']['#is_new_card_option'],
      '#is_new_card_option must be TRUE for non-numeric option keys.',
    );
    $this->assertContains('visually-hidden', $result['add_new']['#attributes']['class']);
    $this->assertContains('saved-card__radio', $result['add_new']['#attributes']['class']);
  }

  /**
   * Tests that numeric option keys are NOT marked as new-card options.
   */
  public function testCallbackDoesNotMarkNumericOptionAsNewCard(): void {
    $element = $this->buildElement(
      ['1' => ['brand' => 'visa', 'last4' => '4242', 'expMonth' => '12', 'expYear' => '2030']],
      ['1' => 'Visa ending in 4242'],
    );

    $result = store_fulfillment_payment_radios_after_build($element, new FormState());

    $this->assertArrayNotHasKey(
      '#is_new_card_option',
      $result['1'],
      'Numeric option keys must never receive #is_new_card_option.',
    );
  }

  /**
   * Tests mixed options: saved cards + "add new" — both processed correctly.
   *
   * This is the real-world scenario: Commerce renders numeric saved payment
   * method IDs alongside the gateway ID as the "add new" string key.
   */
  public function testCallbackProcessesMixedOptions(): void {
    $card = ['brand' => 'visa', 'last4' => '4242', 'expMonth' => '12', 'expYear' => '2030'];
    $element = $this->buildElement(
      ['5' => $card],
      ['5' => 'Visa ending in 4242', 'example' => '+ Use a different card'],
    );

    $result = store_fulfillment_payment_radios_after_build($element, new FormState());

    // Saved card child gets card data + classes.
    $this->assertSame($card, $result['5']['#card_data']);
    $this->assertContains('visually-hidden', $result['5']['#attributes']['class']);
    $this->assertContains('saved-card__radio', $result['5']['#attributes']['class']);

    // "Add new" child gets is_new_card_option + classes.
    $this->assertTrue($result['example']['#is_new_card_option']);
    $this->assertContains('visually-hidden', $result['example']['#attributes']['class']);
    $this->assertContains('saved-card__radio', $result['example']['#attributes']['class']);

    // "Add new" child must NOT have card data.
    $this->assertArrayNotHasKey('#card_data', $result['example']);
  }

  /**
   * Tests multiple saved cards — all children stamped correctly.
   */
  public function testCallbackHandlesMultipleSavedCards(): void {
    $card1 = ['brand' => 'visa', 'last4' => '4242', 'expMonth' => '12', 'expYear' => '2030'];
    $card2 = ['brand' => 'mastercard', 'last4' => '0000', 'expMonth' => '03', 'expYear' => '2027'];
    $element = $this->buildElement(
      ['10' => $card1, '11' => $card2],
      ['10' => 'Visa', '11' => 'Mastercard', 'example' => '+ Use a different card'],
    );

    $result = store_fulfillment_payment_radios_after_build($element, new FormState());

    $this->assertSame($card1, $result['10']['#card_data']);
    $this->assertSame($card2, $result['11']['#card_data']);
    $this->assertContains('visually-hidden', $result['10']['#attributes']['class']);
    $this->assertContains('visually-hidden', $result['11']['#attributes']['class']);
  }

  /**
   * Tests that empty #saved_card_data leaves child elements untouched.
   */
  public function testCallbackLeavesChildrenIntactWhenNoSavedCards(): void {
    $element = $this->buildElement(
      [],
      ['1' => 'Visa ending in 4242'],
    );

    $result = store_fulfillment_payment_radios_after_build($element, new FormState());

    $this->assertArrayNotHasKey('#card_data', $result['1'], 'Child must not gain #card_data when saved_card_data is empty.');
    $this->assertEmpty($result['1']['#attributes']['class'], 'No CSS classes should be added when saved_card_data is empty.');
  }

  /**
   * Tests that missing #saved_card_data key does not cause a PHP error.
   */
  public function testCallbackHandlesMissingSavedCardDataKey(): void {
    // Deliberately omit '#saved_card_data' — simulates an older form_alter
    // that did not set the key (defensive: callback must be resilient).
    $element = [
      '#type' => 'radios',
      '#options' => ['1' => 'Visa'],
      '1' => ['#type' => 'radio', '#attributes' => ['class' => []]],
    ];

    $result = store_fulfillment_payment_radios_after_build($element, new FormState());

    $this->assertIsArray($result, 'Callback must return an array even when #saved_card_data is absent.');
    $this->assertArrayNotHasKey('#card_data', $result['1']);
  }

  /**
   * Tests that #saved_card_data IDs with no matching child element are ignored.
   *
   * Simulates a race-condition where a saved payment method is in
   * #saved_card_data but was not turned into a radio child (e.g., filtered
   * out by Commerce before the after_build runs).
   */
  public function testCallbackIgnoresSavedCardDataWithNoMatchingChild(): void {
    $element = [
      '#type' => 'radios',
      '#options' => ['1' => 'Visa'],
      '#saved_card_data' => [
        // ID 99 exists in saved_card_data but NOT as a child element.
        '99' => ['brand' => 'visa', 'last4' => '4242', 'expMonth' => '12', 'expYear' => '2030'],
      ],
      '1' => ['#type' => 'radio', '#attributes' => ['class' => []]],
    ];

    // Must not PHP-error; child '1' is unaffected.
    $result = store_fulfillment_payment_radios_after_build($element, new FormState());

    $this->assertArrayNotHasKey('#card_data', $result['1']);
    $this->assertEmpty($result['1']['#attributes']['class']);
  }

  /**
   * Tests that the callback preserves pre-existing CSS classes on children.
   *
   * Other modules or preprocess hooks may add classes before after_build runs.
   * The callback must append (not replace) the CSS class array.
   */
  public function testCallbackPreservesExistingCssClasses(): void {
    $element = [
      '#type' => 'radios',
      '#options' => ['1' => 'Visa'],
      '#saved_card_data' => [
        '1' => ['brand' => 'visa', 'last4' => '4242', 'expMonth' => '12', 'expYear' => '2030'],
      ],
      '1' => [
        '#type' => 'radio',
        '#attributes' => ['class' => ['pre-existing-class']],
      ],
    ];

    $result = store_fulfillment_payment_radios_after_build($element, new FormState());

    $classes = $result['1']['#attributes']['class'];
    $this->assertContains('pre-existing-class', $classes, 'Pre-existing CSS classes must not be removed.');
    $this->assertContains('visually-hidden', $classes, 'visually-hidden must also be present.');
    $this->assertContains('saved-card__radio', $classes, 'saved-card__radio must also be present.');
  }

  /**
   * Tests that the element is returned (not modified in-place, not NULL).
   *
   * #after_build callbacks must always return the element array.
   */
  public function testCallbackAlwaysReturnsElement(): void {
    $element = $this->buildElement([], []);

    $result = store_fulfillment_payment_radios_after_build($element, new FormState());

    $this->assertIsArray($result);
    $this->assertSame($element['#type'], $result['#type']);
  }

}
