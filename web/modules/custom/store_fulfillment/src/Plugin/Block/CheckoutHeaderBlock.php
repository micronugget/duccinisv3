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
 * Provides the checkout page header block.
 *
 * Renders an <h1> that varies per checkout step and an optional
 * "← Back to cart" link. Placeable in any layout region via Block UI
 * or Layout Builder.
 *
 * Step titles:
 *   order_information → "Checkout"
 *   review            → "Review your order"
 *   complete          → "Order confirmed"
 *
 * @Block(
 *   id = "checkout_header",
 *   admin_label = @Translation("Checkout header"),
 *   category = @Translation("Store Fulfillment"),
 * )
 */
class CheckoutHeaderBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * Step ID to page title mapping.
   *
   * Values are passed through $this->t() at render time.
   */
  private const STEP_TITLES = [
    'order_information' => 'Checkout',
    'review'            => 'Review your order',
    'complete'          => 'Order confirmed',
  ];

  /**
   * Constructs a CheckoutHeaderBlock.
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
    return ['show_back_link' => TRUE] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function blockForm($form, FormStateInterface $form_state): array {
    $form = parent::blockForm($form, $form_state);
    $form['show_back_link'] = [
      '#type'          => 'checkbox',
      '#title'         => $this->t('Show "Back to cart" link'),
      '#default_value' => $this->configuration['show_back_link'],
      '#description'   => $this->t('Displays a back-navigation link on the Order Details and Review steps. Hidden on Order Confirmed.'),
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function blockSubmit($form, FormStateInterface $form_state): void {
    parent::blockSubmit($form, $form_state);
    $this->configuration['show_back_link'] = (bool) $form_state->getValue('show_back_link');
  }

  /**
   * {@inheritdoc}
   */
  public function build(): array {
    $step_id = $this->resolveStepId();
    if ($step_id === NULL) {
      return [];
    }

    $title = match ($step_id) {
      'review'   => $this->t('Review your order'),
      'complete' => $this->t('Order confirmed'),
      default    => $this->t('Checkout'),
    };

    $back_url = NULL;
    if ($this->configuration['show_back_link'] && $step_id !== 'complete') {
      try {
        $back_url = Url::fromRoute('commerce_cart.page')->toString();
      }
      catch (\Exception $e) {
        // Non-fatal — back link is omitted when the cart route is unavailable.
      }
    }

    return [
      '#type'     => 'inline_template',
      '#template' => '<header class="checkout-header"><h1>{{ title }}</h1>{% if back_url %}<a href="{{ back_url }}" class="back-link">&#8592; {{ back_label }}</a>{% endif %}</header>',
      '#context'  => [
        'title'      => $title,
        'back_url'   => $back_url,
        'back_label' => $this->t('Back to cart'),
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheContexts(): array {
    return Cache::mergeContexts(parent::getCacheContexts(), ['route']);
  }

  /**
   * Resolves the current checkout step ID from the route.
   *
   * Returns NULL when the current page is not a checkout page, signalling
   * build() to output nothing.
   *
   * @return string|null
   *   Step ID ('order_information', 'review', or 'complete'), or NULL.
   */
  protected function resolveStepId(): ?string {
    if ($this->routeMatch->getRouteName() !== 'commerce_checkout.form') {
      return NULL;
    }
    $step = $this->routeMatch->getParameter('step') ?? 'order_information';
    return array_key_exists($step, self::STEP_TITLES) ? $step : 'order_information';
  }

}
