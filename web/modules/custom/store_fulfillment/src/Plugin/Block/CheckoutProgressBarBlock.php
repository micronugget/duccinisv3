<?php

declare(strict_types=1);

namespace Drupal\store_fulfillment\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides the checkout funnel progress bar block.
 *
 * Renders the four-step indicator (Cart → Order Details → Review → Complete)
 * using the duccinis_1984_olympics:checkout-progress SDC component. Step
 * states (done / active / future) and back-navigation links are derived from
 * the current route at render time.
 *
 * Compatible with Block UI, Layout Builder, and Drupal Canvas placement.
 *
 * @Block(
 *   id = "checkout_progress_bar",
 *   admin_label = @Translation("Checkout progress bar"),
 *   category = @Translation("Store Fulfillment"),
 * )
 */
class CheckoutProgressBarBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * Canonical checkout step order — indexes map to "current_position" values.
   */
  private const CHECKOUT_STEPS = ['order_information', 'review', 'complete'];

  /**
   * Constructs a CheckoutProgressBarBlock.
   *
   * @param array $configuration
   *   Plugin configuration.
   * @param string $plugin_id
   *   Plugin ID.
   * @param mixed $plugin_definition
   *   Plugin definition.
   * @param \Drupal\Core\Routing\RouteMatchInterface $routeMatch
   *   The current route match service.
   */
  public function __construct(
    array $configuration,
    string $plugin_id,
    mixed $plugin_definition,
    protected readonly RouteMatchInterface $routeMatch,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('current_route_match'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return ['show_cart_step' => TRUE] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function blockForm($form, FormStateInterface $form_state): array {
    $form = parent::blockForm($form, $form_state);
    $form['show_cart_step'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Show the Cart step'),
      '#default_value' => $this->configuration['show_cart_step'],
      '#description' => $this->t('When unchecked, the progress bar starts at Order Details — useful for flows that bypass the cart page.'),
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function blockSubmit($form, FormStateInterface $form_state): void {
    parent::blockSubmit($form, $form_state);
    $this->configuration['show_cart_step'] = (bool) $form_state->getValue('show_cart_step');
  }

  /**
   * {@inheritdoc}
   */
  public function build(): array {
    $steps = $this->buildSteps();
    if (empty($steps)) {
      return [];
    }

    return [
      '#type' => 'component',
      '#component' => 'duccinis_1984_olympics:checkout-progress',
      '#props' => ['steps' => $steps],
      '#attached' => [
        'library' => ['duccinis_1984_olympics/checkout-progress-analytics'],
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheContexts(): array {
    return Cache::mergeContexts(parent::getCacheContexts(), ['route', 'user']);
  }

  /**
   * Builds the ordered steps array for the progress bar SDC component.
   *
   * Each step is an associative array with keys:
   *   - label (string): Translated step name.
   *   - state (string): 'done' | 'active' | '' (future).
   *   - url (string|null): Back-navigation URL for done steps; null
   *     otherwise.
   *   - analytics (array|null): Funnel-tracking data attributes; null when
   *     no link.
   *
   * Returns an empty array when the current route is not a checkout-funnel
   * page, signalling build() to output nothing.
   *
   * @return array
   *   Steps array keyed by integer. Each element has keys:
   *   label, state, url, analytics.
   */
  public function buildSteps(): array {
    $route_name = $this->routeMatch->getRouteName();

    // Determine the current funnel position.
    // -1 = on the cart page; 0..2 = checkout step index.
    $current_position = $this->resolvePosition($route_name);
    if ($current_position === NULL) {
      // Not a checkout-funnel route — render nothing.
      return [];
    }

    $step_id = $this->resolveStepId($route_name, $current_position);
    $order = $this->routeMatch->getParameter('commerce_order');
    $order_id = $order ? $order->id() : NULL;

    // On the complete step the order is already placed; suppress back-links to
    // avoid confusing re-entry attempts.
    $allow_back_nav = ($step_id !== 'complete');

    $step_labels = [
      'order_information' => $this->t('Order Details'),
      'review'            => $this->t('Review'),
      'complete'          => $this->t('Complete'),
    ];

    $steps = [];

    // ── Cart step ─────────────────────────────────────────────────────────
    if ($this->configuration['show_cart_step']) {
      $cart_state = ($current_position === -1) ? 'active' : 'done';
      $cart_url = NULL;
      $cart_analytics = NULL;

      if ($cart_state === 'done') {
        try {
          $cart_url = Url::fromRoute('commerce_cart.page')->toString();
        }
        catch (\Exception $e) {
          // Non-fatal — cart link omitted if route unavailable.
        }
        if ($cart_url !== NULL) {
          $cart_analytics = [
            'funnel_event' => 'checkout_step_back',
            'from_step'    => $step_id,
            'to_step'      => 'cart',
            'order_id'     => (string) ($order_id ?? ''),
          ];
        }
      }

      $steps[] = [
        'label'     => $this->t('Cart'),
        'state'     => $cart_state,
        'url'       => $cart_url,
        'analytics' => $cart_analytics,
      ];
    }

    // ── Checkout steps ────────────────────────────────────────────────────
    foreach (self::CHECKOUT_STEPS as $i => $id) {
      if ($i < $current_position) {
        $state = 'done';
      }
      elseif ($i === $current_position) {
        $state = 'active';
      }
      else {
        $state = '';
      }

      $url = NULL;
      $analytics = NULL;
      if ($state === 'done' && $order_id && $allow_back_nav) {
        try {
          $url = Url::fromRoute('commerce_checkout.form', [
            'commerce_order' => $order_id,
            'step' => $id,
          ])->toString();
        }
        catch (\Exception $e) {
          // Non-fatal — step renders without a link.
        }
        if ($url !== NULL) {
          $analytics = [
            'funnel_event' => 'checkout_step_back',
            'from_step'    => $step_id,
            'to_step'      => $id,
            'order_id'     => (string) $order_id,
          ];
        }
      }

      $steps[] = [
        'label'     => $step_labels[$id],
        'state'     => $state,
        'url'       => $url,
        'analytics' => $analytics,
      ];
    }

    return $steps;
  }

  /**
   * Maps the current route name to a funnel position integer.
   *
   * Returns NULL when the route is not part of the checkout funnel
   * (block should not render). Returns -1 for the cart page; 0–2 for
   * checkout steps indexed against CHECKOUT_STEPS.
   *
   * @param string $route_name
   *   The current route name.
   *
   * @return int|null
   *   Funnel position (-1 for cart, 0–2 for checkout steps) or NULL.
   */
  protected function resolvePosition(string $route_name): ?int {
    if ($route_name === 'commerce_cart.page') {
      return -1;
    }
    if ($route_name === 'commerce_checkout.form') {
      $step_id = $this->routeMatch->getParameter('step') ?? 'order_information';
      $index = array_search($step_id, self::CHECKOUT_STEPS, TRUE);
      return ($index !== FALSE) ? (int) $index : 0;
    }
    return NULL;
  }

  /**
   * Returns the step ID string for the current funnel position.
   *
   * @param string $route_name
   *   Current route name.
   * @param int $current_position
   *   Resolved funnel position.
   *
   * @return string
   *   Step ID ('cart', 'order_information', 'review', or 'complete').
   */
  protected function resolveStepId(string $route_name, int $current_position): string {
    if ($route_name === 'commerce_cart.page') {
      return 'cart';
    }
    return self::CHECKOUT_STEPS[$current_position] ?? 'order_information';
  }

}
