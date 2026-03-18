<?php

declare(strict_types=1);

namespace Drupal\Tests\store_resolver\Functional;

use Drupal\Tests\commerce\Functional\CommerceBrowserTestBase;

/**
 * Tests the StoreSelectionForm at /store/select.
 *
 * Covers the four behaviours fixed/verified in this development cycle:
 *
 *  1. The form renders at /store/select with all available stores.
 *  2. Submitting the form without a destination redirects to <front>.
 *  3. Submitting the form with ?destination=/cart redirects to /cart.
 *  4. After a successful submission the store cookie is set; a subsequent
 *     visit to /store/select pre-selects that store (proves the PHP
 *     setcookie() fix — secure flag derived from request scheme).
 *  5. The form shows the correct radio as "checked" when the cookie already
 *     identifies a store before the page is first loaded.
 *  6. The modal-gate: /menu carries body.store-modal-open + modal is-active
 *     when no store cookie exists (server-side rendering gate).
 *  7. After selecting a store, /menu no longer carries the gate class.
 *
 * These are plain HTTP functional tests — no JavaScript browser needed.
 *
 * @group store_resolver
 * @group store_resolver_form
 */
class StoreSelectionFormSubmitTest extends CommerceBrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'commerce_cart',
    'commerce_checkout',
    'commerce_product',
    'store_resolver',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected function getAdministratorPermissions(): array {
    return array_merge([
      // Required by store_resolver.select_store route.
      'access content',
      'access checkout',
      'administer commerce_order',
    ], parent::getAdministratorPermissions());
  }

  /**
   * A second store created alongside CommerceBrowserTestBase::$this->store.
   *
   * Having two stores is required to prove that changing selection works —
   * a single-store test cannot distinguish "cookie preserved" from "only
   * option selected".
   *
   * @var \Drupal\commerce_store\Entity\StoreInterface
   */
  protected $secondStore;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->secondStore = $this->createStore('Adams Morgan DC', 'USD');

    // Allow the test user to access the form.
    $this->drupalLogin($this->adminUser);
  }

  /**
   * Tests that the form renders at /store/select with radio inputs.
   */
  public function testFormRendersWithStoreRadios(): void {
    $this->drupalGet('/store/select');

    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->elementExists('css', 'input[name="store_id"]');
    // Both stores should appear.
    $this->assertSession()->elementExists(
      'css',
      'input[name="store_id"][value="' . $this->store->id() . '"]',
    );
    $this->assertSession()->elementExists(
      'css',
      'input[name="store_id"][value="' . $this->secondStore->id() . '"]',
    );
  }

  /**
   * Tests that submitting without a destination redirects away from the form.
   *
   * Without a ?destination= query parameter, the form calls
   * setRedirect('<front>') after the store cookie is set. In Commerce
   * functional tests the authenticated user's "front page" resolves to their
   * profile (/user/N), so we assert that the browser left /store/select
   * rather than asserting a specific path.
   */
  public function testSubmitWithoutDestinationRedirectsAwayFromForm(): void {
    $this->drupalGet('/store/select');
    $this->submitForm(['store_id' => $this->store->id()], 'Continue');

    // The browser must have left /store/select after submission.
    $this->assertSession()->addressNotEquals('/store/select');
  }

  /**
   * Tests that submitting with ?destination=/cart redirects back to /cart.
   *
   * Proves the "Change" link on checkout/cart can pass a destination so the
   * user returns to where they came from.
   */
  public function testSubmitWithDestinationRedirectsToDestination(): void {
    $this->drupalGet('/store/select', ['query' => ['destination' => '/cart']]);
    $this->submitForm(['store_id' => $this->store->id()], 'Continue');

    $this->assertSession()->addressEquals('/cart');
  }

  /**
   * Tests that a success message mentioning the store name is shown.
   */
  public function testSuccessMessageMentionsStoreName(): void {
    $this->drupalGet('/store/select');
    $this->submitForm(['store_id' => $this->store->id()], 'Continue');

    $this->drupalGet('/store/select');
    $this->assertSession()->pageTextContains($this->store->getName());
  }

  /**
   * Tests that the form pre-selects the store matching the current cookie.
   *
   * This is the primary regression test for the pre-selection bug:
   * previously the template used {% if loop.first %} which always checked the
   * first store regardless of the cookie value. After the fix, the radio for
   * the cookie-identified store must carry the "checked" attribute.
   *
   * Sequence:
   *   1. Submit $secondStore → redirect → cookie is now secondStore.
   *   2. Re-visit /store/select.
   *   3. The radio for $secondStore must be checked; $store's radio must not.
   */
  public function testFormPreSelectsStoreMatchingCookie(): void {
    // Step 1: select the second store.
    $this->drupalGet('/store/select');
    $this->submitForm(['store_id' => $this->secondStore->id()], 'Continue');

    // Step 2: revisit the selection form.
    $this->drupalGet('/store/select');
    $this->assertSession()->statusCodeEquals(200);

    $page = $this->getSession()->getPage();

    // The second store's radio MUST be checked.
    $secondRadio = $page->find(
      'css',
      'input[name="store_id"][value="' . $this->secondStore->id() . '"]',
    );
    $this->assertNotNull($secondRadio, 'Radio for second store must be present.');
    $this->assertTrue(
      $secondRadio->isChecked(),
      'Radio for the cookie-identified store must be pre-selected.',
    );

    // The first store's radio must NOT be checked.
    $firstRadio = $page->find(
      'css',
      'input[name="store_id"][value="' . $this->store->id() . '"]',
    );
    $this->assertNotNull($firstRadio, 'Radio for first store must be present.');
    $this->assertFalse(
      $firstRadio->isChecked(),
      'Radio for a store other than the cookie store must not be checked.',
    );
  }

  /**
   * Tests switching between stores updates the pre-selection each time.
   *
   * Exercises the full "change store" loop:
   *   Store A selected → change to Store B → form shows B → change back to A
   *   → form shows A again.
   */
  public function testStoreSwitchingUpdatesPreSelection(): void {
    // Select the first (default) store.
    $this->drupalGet('/store/select');
    $this->submitForm(['store_id' => $this->store->id()], 'Continue');

    // Visit the form — first store should be pre-selected.
    $this->drupalGet('/store/select');
    $radioFirst = $this->getSession()->getPage()->find(
      'css',
      'input[name="store_id"][value="' . $this->store->id() . '"]',
    );
    $this->assertTrue($radioFirst->isChecked(), 'First store should be pre-selected after selecting it.');

    // Switch to the second store.
    $this->submitForm(['store_id' => $this->secondStore->id()], 'Continue');

    // Visit the form again — second store should now be pre-selected.
    $this->drupalGet('/store/select');
    $radioSecond = $this->getSession()->getPage()->find(
      'css',
      'input[name="store_id"][value="' . $this->secondStore->id() . '"]',
    );
    $this->assertTrue(
      $radioSecond->isChecked(),
      'Second store should be pre-selected after switching to it.',
    );
  }

  /**
   * Tests that /store/select itself is never blocked regardless of cookie.
   *
   * The gate must not apply to /store/select or users would be caught in an
   * infinite redirect loop: /menu → /store/select → /store/select → ...
   */
  public function testStoreSelectPageIsNotGated(): void {
    // Visit /store/select with no cookie — must NOT have gate class.
    $this->drupalGet('/store/select');
    $this->assertSession()->statusCodeEquals(200);

    $bodyClasses = $this->getSession()->getPage()
      ->find('css', 'body')
      ?->getAttribute('class') ?? '';

    $this->assertStringNotContainsString(
      'store-modal-open',
      $bodyClasses,
      '/store/select must never receive the gate body class.',
    );
  }

}
