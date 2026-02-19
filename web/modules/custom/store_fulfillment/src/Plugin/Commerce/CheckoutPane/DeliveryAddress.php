<?php

declare(strict_types=1);

namespace Drupal\store_fulfillment\Plugin\Commerce\CheckoutPane;

use Drupal\commerce\InlineFormManager;
use Drupal\commerce_checkout\Plugin\Commerce\CheckoutPane\CheckoutPaneBase;
use Drupal\commerce_checkout\Plugin\Commerce\CheckoutPane\CheckoutPaneInterface;
use Drupal\commerce_checkout\Plugin\Commerce\CheckoutFlow\CheckoutFlowInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\store_fulfillment\DeliveryRadiusValidator;
use Drupal\store_resolver\StoreResolver;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a checkout pane for collecting the delivery address.
 *
 * This pane is separate from billing and only shown when the fulfillment
 * method is set to "delivery". It includes a checkbox to copy the delivery
 * address to billing, making the billing address on the payment pane optional.
 *
 * @CommerceCheckoutPane(
 *   id = "delivery_address",
 *   label = @Translation("Delivery Address"),
 *   default_step = "order_information",
 *   wrapper_element = "fieldset",
 * )
 */
class DeliveryAddress extends CheckoutPaneBase implements CheckoutPaneInterface {

  /**
   * The inline form manager.
   *
   * @var \Drupal\commerce\InlineFormManager
   */
  protected InlineFormManager $inlineFormManager;

  /**
   * The delivery radius validator.
   *
   * @var \Drupal\store_fulfillment\DeliveryRadiusValidator
   */
  protected DeliveryRadiusValidator $deliveryRadiusValidator;

  /**
   * The store resolver.
   *
   * @var \Drupal\store_resolver\StoreResolver
   */
  protected StoreResolver $storeResolver;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition, CheckoutFlowInterface $checkout_flow = NULL) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition, $checkout_flow);
    $instance->inlineFormManager = $container->get('plugin.manager.commerce_inline_form');
    $instance->deliveryRadiusValidator = $container->get('store_fulfillment.delivery_radius_validator');
    $instance->storeResolver = $container->get('store_resolver.current_store');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function isVisible(): bool {
    // Always visible so it participates in the form render array.
    // Actual content visibility is controlled in buildPaneForm() because
    // the fulfillment_method may not yet be persisted to order data
    // (it's saved by FulfillmentTime::submitPaneForm on form submit, not AJAX).
    return TRUE;
  }

  /**
   * Determines if delivery is the selected fulfillment method.
   *
   * Checks both persisted order data and current form state values.
   *
   * @param \Drupal\Core\Form\FormStateInterface|null $form_state
   *   The form state, if available.
   *
   * @return bool
   *   TRUE if delivery is selected.
   */
  protected function isDeliverySelected(?FormStateInterface $form_state = NULL): bool {
    // Check form_state first (reflects current user selection during AJAX).
    if ($form_state) {
      $method = $form_state->getValue(['fulfillment_time', 'fulfillment_method']);
      if ($method) {
        return $method === 'delivery';
      }
    }
    // Fall back to persisted order data.
    return $this->order->getData('fulfillment_method') === 'delivery';
  }

  /**
   * {@inheritdoc}
   */
  public function buildPaneSummary() {
    $summary = [];
    $profile = $this->loadDeliveryProfile();
    if ($profile) {
      $profile_view_builder = $this->entityTypeManager->getViewBuilder('profile');
      $summary = $profile_view_builder->view($profile, 'default');
    }
    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function buildPaneForm(array $pane_form, FormStateInterface $form_state, array &$complete_form) {
    $is_delivery = $this->isDeliverySelected($form_state);

    // Wrap the entire pane for AJAX targeting from FulfillmentTime.
    // The .delivery-expand wrapper drives the CSS max-height transition.
    $classes = 'delivery-expand';
    if ($is_delivery) {
      $classes .= ' open';
    }
    $pane_form['#prefix'] = '<div id="delivery-address-wrapper" class="' . $classes . '">';
    $pane_form['#suffix'] = '</div>';

    // Attach expand/collapse transition styles.
    $pane_form['#attached']['library'][] = 'store_fulfillment/delivery_expand';

    // If delivery is not selected, render a hidden placeholder so Commerce
    // doesn't strip the wrapper via #access = FALSE (it checks for visible
    // children in CheckoutFlowWithPanesBase::buildForm).
    if (!$is_delivery) {
      $pane_form['placeholder'] = [
        '#markup' => '',
      ];
      return $pane_form;
    }

    // Section label.
    $pane_form['section_label'] = [
      '#markup' => '<div class="section-label">' . $this->t('Deliver to') . '</div>',
      '#weight' => -10,
    ];

    // Load or create the delivery profile.
    $profile = $this->loadDeliveryProfile();
    if (!$profile) {
      $profile_storage = $this->entityTypeManager->getStorage('profile');
      $profile = $profile_storage->create([
        'type' => 'customer',
        'uid' => $this->order->getCustomerId() ?: 0,
      ]);
    }

    $inline_form = $this->inlineFormManager->createInstance('customer_profile', [
      'profile_scope' => 'shipping',
      'available_countries' => $this->order->getStore()->getBillingCountries(),
      'address_book_uid' => $this->order->getCustomerId(),
      'copy_on_save' => FALSE,
    ], $profile);

    $pane_form['profile'] = [
      '#parents' => array_merge($pane_form['#parents'], ['profile']),
      '#inline_form' => $inline_form,
    ];
    $pane_form['profile'] = $inline_form->buildInlineForm($pane_form['profile'], $form_state);

    // "Same as billing" checkbox — checked by default.
    $same_as_billing = $this->order->getData('delivery_same_as_billing') ?? TRUE;
    $pane_form['copy_to_billing'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Same address for billing'),
      '#default_value' => $same_as_billing,
      '#weight' => 100,
      '#wrapper_attributes' => ['class' => ['delivery-same-billing']],
    ];

    // Build a separate billing address form shown when checkbox is unchecked.
    $billing_profile = $this->order->getBillingProfile();
    if (!$billing_profile) {
      $profile_storage = $this->entityTypeManager->getStorage('profile');
      $billing_profile = $profile_storage->create([
        'type' => 'customer',
        'uid' => $this->order->getCustomerId() ?: 0,
      ]);
    }

    $billing_inline_form = $this->inlineFormManager->createInstance('customer_profile', [
      'profile_scope' => 'billing',
      'available_countries' => $this->order->getStore()->getBillingCountries(),
      'address_book_uid' => $this->order->getCustomerId(),
      'copy_on_save' => FALSE,
    ], $billing_profile);

    $pane_form['billing_profile'] = [
      '#type' => 'container',
      '#title' => $this->t('Billing Address'),
      '#weight' => 101,
      '#states' => [
        'visible' => [
          ':input[name="delivery_address[copy_to_billing]"]' => ['checked' => FALSE],
        ],
      ],
    ];
    $pane_form['billing_profile']['label'] = [
      '#markup' => '<h4>' . $this->t('Billing Address') . '</h4>',
      '#weight' => -1,
    ];
    $pane_form['billing_profile']['profile'] = [
      '#parents' => array_merge($pane_form['#parents'], ['billing_profile', 'profile']),
      '#inline_form' => $billing_inline_form,
    ];
    $pane_form['billing_profile']['profile'] = $billing_inline_form->buildInlineForm($pane_form['billing_profile']['profile'], $form_state);

    return $pane_form;
  }

  /**
   * {@inheritdoc}
   */
  public function validatePaneForm(array &$pane_form, FormStateInterface $form_state, array &$complete_form) {
    // Skip validation entirely if the delivery address profile form was not
    // built. This happens when the pane rendered a hidden placeholder because
    // delivery was not selected at build time. We must check the render array
    // rather than isDeliverySelected() because form_state values during
    // validation may differ from what was available during build.
    if (!isset($pane_form['profile']['#inline_form'])) {
      return;
    }

    // Also skip if delivery is no longer the selected method.
    if (!$this->isDeliverySelected($form_state)) {
      return;
    }

    // The inline form handles its own address validation.
    // Additionally validate the delivery address against the store radius.
    /** @var \Drupal\commerce\Plugin\Commerce\InlineForm\EntityInlineFormInterface $inline_form */
    $inline_form = $pane_form['profile']['#inline_form'];
    /** @var \Drupal\profile\Entity\ProfileInterface $profile */
    $profile = $inline_form->getEntity();

    if (!$profile->hasField('address') || $profile->get('address')->isEmpty()) {
      return;
    }

    /** @var \Drupal\address\AddressInterface $address */
    $address = $profile->get('address')->first();

    $store = $this->order->getStore();
    if (!$store) {
      return;
    }

    $result = $this->deliveryRadiusValidator->validateDeliveryAddress($store, $address);
    if (!$result['valid']) {
      $form_state->setError($pane_form['profile'], $result['message']);
    }

    // When billing is entered separately, validate it has an address.
    $values = $form_state->getValue($pane_form['#parents']);
    $copy_to_billing = !empty($values['copy_to_billing'] ?? FALSE);
    if (!$copy_to_billing && isset($pane_form['billing_profile']['profile']['#inline_form'])) {
      /** @var \Drupal\commerce\Plugin\Commerce\InlineForm\EntityInlineFormInterface $billing_inline_form */
      $billing_inline_form = $pane_form['billing_profile']['profile']['#inline_form'];
      /** @var \Drupal\profile\Entity\ProfileInterface $billing_profile */
      $billing_profile = $billing_inline_form->getEntity();
      if (!$billing_profile->hasField('address') || $billing_profile->get('address')->isEmpty()) {
        $form_state->setError($pane_form['billing_profile']['profile'], $this->t('Please enter a billing address.'));
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitPaneForm(array &$pane_form, FormStateInterface $form_state, array &$complete_form) {
    // Skip if the profile form was not built (pane rendered hidden placeholder).
    if (!isset($pane_form['profile']['#inline_form'])) {
      return;
    }
    // Also skip if delivery is no longer the selected method.
    if (!$this->isDeliverySelected($form_state)) {
      return;
    }

    /** @var \Drupal\commerce\Plugin\Commerce\InlineForm\EntityInlineFormInterface $inline_form */
    $inline_form = $pane_form['profile']['#inline_form'];
    /** @var \Drupal\profile\Entity\ProfileInterface $profile */
    $profile = $inline_form->getEntity();
    $profile->save();

    // Store a reference to the delivery profile on the order.
    $this->order->setData('delivery_address_profile', $profile->id());

    $values = $form_state->getValue($pane_form['#parents']);
    $copy_to_billing = !empty($values['copy_to_billing']);
    $this->order->setData('delivery_same_as_billing', $copy_to_billing);

    if ($copy_to_billing) {
      // Clone the delivery address into the billing profile.
      $this->copyDeliveryToBilling($profile);
    }
    elseif (isset($pane_form['billing_profile']['profile']['#inline_form'])) {
      // Use the separately entered billing address.
      /** @var \Drupal\commerce\Plugin\Commerce\InlineForm\EntityInlineFormInterface $billing_inline_form */
      $billing_inline_form = $pane_form['billing_profile']['profile']['#inline_form'];
      /** @var \Drupal\profile\Entity\ProfileInterface $billing_profile */
      $billing_profile = $billing_inline_form->getEntity();
      $billing_profile->save();
      $this->order->setBillingProfile($billing_profile);
    }
  }

  /**
   * Copies the delivery profile address to the order's billing profile.
   *
   * @param \Drupal\profile\Entity\ProfileInterface $delivery_profile
   *   The delivery profile entity.
   */
  protected function copyDeliveryToBilling($delivery_profile): void {
    if (!$delivery_profile->hasField('address') || $delivery_profile->get('address')->isEmpty()) {
      return;
    }

    $billing_profile = $this->order->getBillingProfile();
    if (!$billing_profile) {
      $profile_storage = $this->entityTypeManager->getStorage('profile');
      $billing_profile = $profile_storage->create([
        'type' => 'customer',
        'uid' => $this->order->getCustomerId() ?: 0,
      ]);
    }

    $billing_profile->set('address', $delivery_profile->get('address')->getValue());
    $billing_profile->save();
    $this->order->setBillingProfile($billing_profile);
  }

  /**
   * Loads the delivery profile from the order data reference.
   *
   * @return \Drupal\profile\Entity\ProfileInterface|null
   *   The delivery profile, or NULL if not yet stored.
   */
  protected function loadDeliveryProfile() {
    $profile_id = $this->order->getData('delivery_address_profile');
    if ($profile_id) {
      return $this->entityTypeManager->getStorage('profile')->load($profile_id);
    }
    return NULL;
  }

}
