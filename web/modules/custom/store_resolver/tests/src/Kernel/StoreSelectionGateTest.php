<?php

declare(strict_types=1);

namespace Drupal\Tests\store_resolver\Kernel;

use Drupal\store_resolver\StoreResolver;
use Drupal\Tests\commerce\Kernel\CommerceKernelTestBase;

/**
 * Tests the /menu gate implemented by store_resolver_preprocess_html().
 *
 * The gate adds 'store-modal-open' to <body> server-side when:
 *   - The current request path is in STORE_RESOLVER_PROTECTED_PATHS, AND
 *   - No store cookie is present on the request.
 *
 * This prevents the page content from being scrollable before the store
 * selection modal renders, matching V3 behaviour.
 *
 * @group store_resolver
 * @group store_resolver_gate
 *
 * @covers ::store_resolver_preprocess_html
 */
class StoreSelectionGateTest extends CommerceKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'profile',
    'state_machine',
    'entity_reference_revisions',
    'store_resolver',
  ];

  /**
   * The StoreResolver service — used to read the cookie constant.
   *
   * @var \Drupal\store_resolver\StoreResolver
   */
  protected StoreResolver $storeResolver;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    \Drupal::moduleHandler()->loadInclude('store_resolver', 'module');
    $this->storeResolver = $this->container->get('store_resolver.current_store');
  }

  /**
   * Invokes store_resolver_preprocess_html() and returns the class attribute.
   *
   * @param string $path
   *   The request path to simulate.
   * @param string|null $cookie_value
   *   Cookie value, or NULL to simulate no cookie.
   *
   * @return array
   *   The 'class' key of $variables['attributes'] after the hook runs,
   *   or an empty array if none were added.
   */
  private function invokeGateHook(string $path, ?string $cookie_value): array {
    $request = $this->container->get('request_stack')->getCurrentRequest();
    $request->server->set('REQUEST_URI', $path);

    // Simulate the pathInfo by overriding the path info on the request.
    // Symfony's getPathInfo() uses the REQUEST_URI + SCRIPT_NAME.
    // Directly override via reflection so we can unit-test without a full
    // HTTP bootstrap.
    $reflection = new \ReflectionObject($request);
    $pathInfoProp = $reflection->getProperty('pathInfo');
    $pathInfoProp->setAccessible(TRUE);
    $pathInfoProp->setValue($request, $path);

    if ($cookie_value !== NULL) {
      $request->cookies->set(StoreResolver::STORE_COOKIE_NAME, $cookie_value);
    }
    else {
      $request->cookies->remove(StoreResolver::STORE_COOKIE_NAME);
    }

    $variables = ['attributes' => []];
    store_resolver_preprocess_html($variables);

    return (array) ($variables['attributes']['class'] ?? []);
  }

  /**
   * Test: /menu without cookie → body gets store-modal-open.
   */
  public function testMenuWithoutCookieGetsBodyClass(): void {
    $classes = $this->invokeGateHook('/menu', NULL);

    $this->assertContains(
      'store-modal-open',
      $classes,
      '/menu without a store cookie must receive the store-modal-open class.',
    );
  }

  /**
   * Test: /menu with a valid cookie → no body class added.
   */
  public function testMenuWithCookieDoesNotGetBodyClass(): void {
    $classes = $this->invokeGateHook('/menu', (string) $this->store->id());

    $this->assertNotContains(
      'store-modal-open',
      $classes,
      '/menu with a store cookie must NOT receive the gate class.',
    );
  }

  /**
   * Test: /menu/category sub-path without cookie → body gets store-modal-open.
   *
   * The gate must apply to any path that starts with /menu/, not just /menu
   * exactly, so that product category and item pages within the menu are also
   * gated.
   */
  public function testMenuSubPathWithoutCookieGetsBodyClass(): void {
    $classes = $this->invokeGateHook('/menu/pizza', NULL);

    $this->assertContains(
      'store-modal-open',
      $classes,
      '/menu/* paths without a store cookie must also receive the gate class.',
    );
  }

  /**
   * Test: /cart without cookie → no body class (cart is not a protected path).
   */
  public function testCartWithoutCookieDoesNotGetBodyClass(): void {
    $classes = $this->invokeGateHook('/cart', NULL);

    $this->assertNotContains(
      'store-modal-open',
      $classes,
      '/cart is not in STORE_RESOLVER_PROTECTED_PATHS and must not be gated.',
    );
  }

  /**
   * Test: /checkout without cookie → no body class.
   */
  public function testCheckoutWithoutCookieDoesNotGetBodyClass(): void {
    $classes = $this->invokeGateHook('/checkout/1/order_information', NULL);

    $this->assertNotContains(
      'store-modal-open',
      $classes,
      '/checkout is not in STORE_RESOLVER_PROTECTED_PATHS and must not be gated.',
    );
  }

  /**
   * Test: /store/select itself is never gated (infinite redirect prevention).
   */
  public function testStoreSelectIsNeverGated(): void {
    $classes = $this->invokeGateHook('/store/select', NULL);

    $this->assertNotContains(
      'store-modal-open',
      $classes,
      '/store/select must never receive the gate class — prevents infinite redirect.',
    );
  }

  /**
   * Test: non-protected page /about without cookie → no body class.
   */
  public function testOtherPageWithoutCookieDoesNotGetBodyClass(): void {
    $classes = $this->invokeGateHook('/about', NULL);

    $this->assertNotContains(
      'store-modal-open',
      $classes,
      'Non-protected paths must not receive the gate class.',
    );
  }

}
