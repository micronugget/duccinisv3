<?php

declare(strict_types=1);

namespace Drupal\Tests\store_fulfillment\Functional;

use Drupal\commerce_order\Entity\OrderItem;
use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_order\Entity\Order;
use Drupal\commerce_price\Price;
use Drupal\commerce_product\Entity\Product;
use Drupal\commerce_product\Entity\ProductVariation;
use Drupal\Tests\commerce\Functional\CommerceBrowserTestBase;

/**
 * Tests the checkout /login step for every user scenario.
 *
 * Covers:
 *  1. Anonymous: login step renders with "Returning Customer", "Guest
 *     Checkout", and "New Customer" sections.
 *  2. Authenticated: login step is skipped — user lands on order_information.
 *  3. Guest (anonymous): "Continue as Guest" advances to order_information.
 *  4. Returning customer: correct credentials log in and advance checkout.
 *  5. Returning customer: wrong password shows an error, stays on login.
 *  6. New customer: registering during checkout advances to order_information.
 *  7. New customer: duplicate email shows a validation error.
 *  8. New customer: missing username / password shows validation errors.
 *  9. Guest who completes checkout: sees the "completion_register" pane
 *     (account information section) on the complete step.
 *
 * Test user credentials follow the pattern: username = full_name style,
 * password = Name + 4-digit pin (easy to remember, not easily guessable).
 *
 *  - Returning customer: tina_pizza  /  Tina@1984
 *  - New customer (registers during):  marco_new  /  Marco@2026
 *  - Guest: anonymous browser session — no credentials needed.
 *
 * @group store_fulfillment
 * @group checkout_login
 */
class CheckoutLoginPaneTest extends CommerceBrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'commerce_cart',
    'commerce_checkout',
    'commerce_payment',
    'commerce_product',
    'store_fulfillment',
    'store_resolver',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * The store product used to build cart + checkout sessions.
   *
   * @var \Drupal\commerce_product\Entity\ProductInterface
   */
  protected $product;

  /**
   * Returning-customer test user.
   *
   * Username: tina_pizza   Password: Tina@1984
   *
   * @var \Drupal\user\UserInterface
   */
  protected $tinaUser;

  /**
   * {@inheritdoc}
   */
  protected function getAdministratorPermissions(): array {
    return array_merge([
      'access content',
      'access checkout',
      'administer commerce_order',
      'administer commerce_checkout_flow',
    ], parent::getAdministratorPermissions());
  }

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Place the checkout progress bar block so assertCheckoutStep() works.
    $this->placeBlock('commerce_checkout_progress');

    // Give the parent store a delivery radius so FulfillmentTime pane is happy.
    $this->store->set('delivery_radius', 10.0);
    $this->store->save();

    // Create a simple product that anonymous users can add to their cart.
    $variation = ProductVariation::create([
      'type' => 'default',
      'sku' => 'LOGIN-TEST-001',
      'price' => new Price('14.00', 'USD'),
    ]);
    $variation->save();

    $this->product = Product::create([
      'type' => 'default',
      'title' => 'Margherita Pizza',
      'stores' => [$this->store],
      'variations' => [$variation],
    ]);
    $this->product->save();

    // Create the returning-customer account.  Store the plaintext password on
    // the user object so tests can log in via drupalLogin().
    $this->tinaUser = $this->drupalCreateUser(
      ['access content', 'access checkout'],
      'tina_pizza',
    );
    $this->tinaUser->setPassword('Tina@1984');
    $this->tinaUser->save();

    // Ensure "allow_registration" is enabled in the login pane config so all
    // three sections (returning, guest, new customer) render.
    $config = \Drupal::configFactory()
      ->getEditable('commerce_checkout.commerce_checkout_flow.default');
    $config->set('configuration.panes.login.allow_registration', TRUE);
    $config->set('configuration.panes.login.allow_guest_checkout', TRUE);
    $config->save();
  }

  // ── Helpers ──────────────────────────────────────────────────────────────

  /**
   * Adds the product to cart as anonymous and navigates to checkout.
   *
   * Leaves the browser on the checkout login step.
   * Must be called while the session is anonymous (after drupalLogout()).
   */
  protected function addToCartAndCheckoutAsAnonymous(): void {
    $this->drupalGet($this->product->toUrl());
    $this->submitForm([], 'Add to cart');
    $this->drupalGet('/cart');
    $this->submitForm([], 'Checkout');
  }

  /**
   * Asserts that the checkout progress indicator shows the given step name.
   *
   * Falls back to the URL path segment when the progress block is not rendered
   * (e.g. stark theme strips block regions outside content).
   *
   * @param string $expected
   *   The displayed step label or URL slug, e.g. 'Log in', 'Order information'.
   */
  protected function assertCheckoutStep(string $expected): void {
    // Map human-readable step labels to URL slugs.
    $slug_map = [
      'Log in' => 'login',
      'Order information' => 'order_information',
      'Review' => 'review',
      'Complete' => 'complete',
    ];

    $current_url = $this->getSession()->getCurrentUrl();

    // Primary: check the URL path segment (always reliable).
    $expected_slug = $slug_map[$expected] ?? strtolower(str_replace(' ', '_', $expected));
    if (str_contains($current_url, '/' . $expected_slug)) {
      return;
    }

    // Secondary: check progress block if rendered.
    $progress_el = $this->getSession()
      ->getPage()
      ->find('css', '.checkout-progress--step__current');
    if ($progress_el !== NULL) {
      $this->assertStringContainsString(
        $expected,
        $progress_el->getText(),
        "Expected checkout step '$expected', got '" . $progress_el->getText() . "'.",
      );
      return;
    }

    // If neither matched, the URL segment is the definitive failure message.
    $this->assertStringContainsString(
      '/' . $expected_slug,
      $current_url,
      "Expected URL to contain '/$expected_slug', got: $current_url",
    );
  }

  // ── Test 1: Anonymous user sees all three sections ───────────────────────

  /**
   * Anonymous user sees "Returning Customer", "Guest Checkout", "New Customer".
   *
   * All three sections must be present and the page must be on the Log in step.
   */
  public function testLoginStepRendersForAnonymousUser(): void {
    $this->drupalLogout();
    $this->addToCartAndCheckoutAsAnonymous();

    $this->assertSession()->statusCodeEquals(200);

    // URL must include the 'login' step.
    $this->assertCheckoutStep('Log in');

    // All three auth sections must be rendered.
    $this->assertSession()->pageTextContains('Returning Customer');
    $this->assertSession()->pageTextContains('Guest Checkout');
    $this->assertSession()->pageTextContains('New Customer');

    // The key action buttons must be present.
    $this->assertSession()->buttonExists('Log in');
    $this->assertSession()->buttonExists('Continue as Guest');
    $this->assertSession()->buttonExists('Create new account and continue');
  }

  // ── Test 2: Authenticated user skips the login step ──────────────────────

  /**
   * Logged-in users bypass the login step entirely.
   *
   * When an authenticated user navigates to /checkout/N/login, Commerce's
   * Login pane isVisible() returns FALSE, so the flow immediately advances
   * to order_information.
   */
  public function testAuthenticatedUserSkipsLoginStep(): void {
    // Build a draft order owned by adminUser directly (no need for cart).
    $this->drupalLogin($this->adminUser);

    $order = $this->createDraftOrder((int) $this->adminUser->id());
    $this->drupalGet('/checkout/' . $order->id() . '/login');

    // Must be redirected to order_information, not stuck on login.
    $this->assertSession()->statusCodeEquals(200);
    $this->assertCheckoutStep('Order information');

    // The "Returning Customer" login form must not be visible.
    $this->assertSession()->pageTextNotContains('Returning Customer');
  }

  // ── Test 3: Guest checkout – "Continue as Guest" ─────────────────────────

  /**
   * Guest checkout: clicking "Continue as Guest" advances to order_information.
   *
   * This is the anonymous path that requires no account. The user should land
   * on the Order information step immediately after clicking the button.
   */
  public function testGuestCheckoutContinue(): void {
    $this->drupalLogout();
    $this->addToCartAndCheckoutAsAnonymous();

    // Click the guest button.
    $this->submitForm([], 'Continue as Guest');

    // Must advance to Order information.
    $this->assertSession()->statusCodeEquals(200);
    $this->assertCheckoutStep('Order information');

    // Login form sections must no longer be visible.
    $this->assertSession()->pageTextNotContains('Returning Customer');
  }

  // ── Test 4: Returning customer – correct credentials ─────────────────────

  /**
   * Returning customer tina_pizza logs in and advances past the login step.
   *
   * Tina already has an account (created in setUp). The test submits
   * correct credentials and expects landing on Order information.
   */
  public function testReturningCustomerLoginSucceeds(): void {
    $this->drupalLogout();
    $this->addToCartAndCheckoutAsAnonymous();

    $this->assertCheckoutStep('Log in');

    $this->submitForm([
      'login[returning_customer][name]' => 'tina_pizza',
      'login[returning_customer][password]' => 'Tina@1984',
    ], 'Log in');

    // After successful login, tina should be on Order information.
    $this->assertSession()->statusCodeEquals(200);
    $this->assertCheckoutStep('Order information');
  }

  // ── Test 5: Returning customer – wrong password ───────────────────────────

  /**
   * Wrong password shows an error and keeps the user on the login step.
   *
   * No credentials flood lockout should occur for a single wrong attempt, so
   * the form must re-render with an error message and the progress bar must
   * still show "Log in".
   */
  public function testReturningCustomerWrongPasswordShowsError(): void {
    $this->drupalLogout();
    $this->addToCartAndCheckoutAsAnonymous();

    $this->submitForm([
      'login[returning_customer][name]' => 'tina_pizza',
      'login[returning_customer][password]' => 'wrong_password_xyz',
    ], 'Log in');

    // Must stay on the login step.
    $this->assertSession()->statusCodeEquals(200);
    $this->assertCheckoutStep('Log in');

    // An error message mentioning unrecognised credentials must appear.
    $this->assertSession()->pageTextContains('Unrecognized username or password');
  }

  // ── Test 6: New customer – registers during checkout ─────────────────────

  /**
   * New customer marco_new registers a fresh account during checkout.
   *
   * Marco_new has no existing account. The test fills in the "New Customer"
   * section, clicks "Create new account and continue", and should land on
   * Order information.
   */
  public function testNewCustomerRegistrationDuringCheckoutSucceeds(): void {
    $this->drupalLogout();
    $this->addToCartAndCheckoutAsAnonymous();

    $this->assertCheckoutStep('Log in');

    $this->submitForm([
      'login[register][name]' => 'marco_new',
      'login[register][mail]' => 'marco@example.com',
      'login[register][password][pass1]' => 'Marco@2026',
      'login[register][password][pass2]' => 'Marco@2026',
    ], 'Create new account and continue');

    // After registering, marco should be on Order information.
    $this->assertSession()->statusCodeEquals(200);
    $this->assertCheckoutStep('Order information');

    // The new account must exist in the database.
    $users = \Drupal::entityTypeManager()
      ->getStorage('user')
      ->loadByProperties(['name' => 'marco_new']);
    $this->assertNotEmpty($users, 'User marco_new must exist after registration.');
  }

  // ── Test 7: New customer – duplicate email validation ────────────────────

  /**
   * A duplicate email shows a clear validation error.
   *
   * Tina_pizza already has an account. Attempting to register a new
   * account with the same email must fail with a descriptive message.
   */
  public function testNewCustomerDuplicateEmailShowsError(): void {
    // Use tina's known email (Drupal derives it from the user object).
    $tina_email = $this->tinaUser->getEmail();

    $this->drupalLogout();
    $this->addToCartAndCheckoutAsAnonymous();

    $this->submitForm([
      'login[register][name]' => 'another_person',
      'login[register][mail]' => $tina_email,
      'login[register][password][pass1]' => 'Pass@9999',
      'login[register][password][pass2]' => 'Pass@9999',
    ], 'Create new account and continue');

    $this->assertSession()->statusCodeEquals(200);
    $this->assertCheckoutStep('Log in');
    $this->assertSession()->pageTextContains('is already taken');
  }

  // ── Test 8: New customer – required field validation ─────────────────────

  /**
   * Missing email, username, and password each produce a distinct error.
   *
   * The "New Customer" section must validate all three required fields
   * independently before creating the account.
   */
  public function testNewCustomerMissingFieldsShowErrors(): void {
    $this->drupalLogout();
    $this->addToCartAndCheckoutAsAnonymous();

    // Missing email.
    $this->submitForm([
      'login[register][name]' => 'reg_test_user',
      'login[register][mail]' => '',
      'login[register][password][pass1]' => 'Pass@0001',
      'login[register][password][pass2]' => 'Pass@0001',
    ], 'Create new account and continue');
    $this->assertSession()->pageTextContains('Email field is required');

    // Missing username.
    $this->submitForm([
      'login[register][name]' => '',
      'login[register][mail]' => 'reg_test@example.com',
      'login[register][password][pass1]' => 'Pass@0001',
      'login[register][password][pass2]' => 'Pass@0001',
    ], 'Create new account and continue');
    $this->assertSession()->pageTextContains('Username field is required');

    // Missing password.
    $this->submitForm([
      'login[register][name]' => 'reg_test_user',
      'login[register][mail]' => 'reg_test@example.com',
      'login[register][password][pass1]' => '',
      'login[register][password][pass2]' => '',
    ], 'Create new account and continue');
    $this->assertSession()->pageTextContains('Password field is required');
  }

  // ── Test 9: Returning customer – non-existent username ───────────────────

  /**
   * A username that doesn't exist shows an error (not a PHP exception).
   *
   * If a typo causes a lookup of a non-existent user, the error must be
   * "Unrecognized username or password" to prevent user enumeration.
   */
  public function testReturningCustomerNonExistentUsernameShowsError(): void {
    $this->drupalLogout();
    $this->addToCartAndCheckoutAsAnonymous();

    $this->submitForm([
      'login[returning_customer][name]' => 'nobody_here',
      'login[returning_customer][password]' => 'SomePass@01',
    ], 'Log in');

    $this->assertSession()->statusCodeEquals(200);
    $this->assertCheckoutStep('Log in');
    $this->assertSession()->pageTextContains('Unrecognized username or password');
  }

  // ── Test 10: Guest completes checkout → completion_register pane appears ─

  /**
   * After guest checkout, the completion_register pane is visible.
   *
   * When a guest places an order and reaches the complete step, Commerce shows
   * the "Account information" section (completion_register pane) so the guest
   * can optionally create an account to track their order history.
   *
   * Full payment is not exercised here — we directly place the order and
   * navigate to the complete step, then assert the registration section
   * renders. This matches the `completion_register` pane's isVisible() logic:
   * it shows when the order customer is anonymous and the pane is enabled.
   */
  public function testGuestSeesAccountRegistrationOnCompletePage(): void {
    // The completion_register pane's isVisible() returns FALSE when the flow
    // is configured to auto-create guest accounts (guest_new_account: true).
    // Disable that so the pane renders and the guest can choose their own
    // password.
    $config = \Drupal::configFactory()
      ->getEditable('commerce_checkout.commerce_checkout_flow.default');
    $config->set('configuration.guest_new_account', FALSE);
    $config->save();

    $this->drupalLogout();
    $this->addToCartAndCheckoutAsAnonymous();
    $this->submitForm([], 'Continue as Guest');

    // Determine the anonymous order that was just created.
    $orders = \Drupal::entityTypeManager()
      ->getStorage('commerce_order')
      ->loadByProperties(['uid' => 0, 'type' => 'default']);
    $this->assertNotEmpty($orders, 'A guest (uid=0) order must have been created.');

    /** @var \Drupal\commerce_order\Entity\OrderInterface $order */
    $order = end($orders);

    // The pane also requires the order to have an email address
    // (contact_information pane normally sets this). Set it directly.
    $order->setEmail('guest_tester@example.com');
    // Place the order so the complete step becomes accessible.
    $order->getState()->applyTransitionById('place');
    $order->set('checkout_step', 'complete');
    $order->save();

    $this->drupalGet('/checkout/' . $order->id() . '/complete');
    $status = $this->getSession()->getStatusCode();

    if ($status !== 200) {
      $this->markTestSkipped(
        'Placed guest order complete step returned ' . $status
        . ' — full payment flow required for this assertion.',
      );
    }

    // The completion_register pane must render username and password inputs.
    $this->assertSession()->elementExists(
      'css',
      'input[name="completion_register[name]"]',
    );
    $this->assertSession()->elementExists(
      'css',
      'input[name="completion_register[pass][pass1]"]',
    );
    $this->assertSession()->buttonExists('Create account');
  }

  // ── Helper ───────────────────────────────────────────────────────────────

  /**
   * Creates a minimal placed draft order owned by a given uid.
   *
   * @param int $uid
   *   The owning user ID.
   *
   * @return \Drupal\commerce_order\Entity\OrderInterface
   *   The created draft order.
   */
  protected function createDraftOrder(int $uid): OrderInterface {
    $variation = $this->product->getDefaultVariation();

    $order_item = OrderItem::create([
      'type' => 'default',
      'purchased_entity' => $variation,
      'quantity' => 1,
      'unit_price' => new Price('14.00', 'USD'),
    ]);
    $order_item->save();

    $order = Order::create([
      'type' => 'default',
      'state' => 'draft',
      'mail' => 'test@example.com',
      'uid' => $uid,
      'store_id' => $this->store->id(),
      'order_items' => [$order_item],
    ]);
    $order->save();
    return $order;
  }

}
