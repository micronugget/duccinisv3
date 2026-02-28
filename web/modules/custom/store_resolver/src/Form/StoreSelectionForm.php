<?php

namespace Drupal\store_resolver\Form;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\store_resolver\StoreResolver;
use Drupal\store_resolver\StoreHoursValidator;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Form for selecting a store.
 */
class StoreSelectionForm extends FormBase {

  /**
   * The store resolver service.
   *
   * @var \Drupal\store_resolver\StoreResolver
   */
  protected $storeResolver;

  /**
   * The store hours validator service.
   *
   * @var \Drupal\store_resolver\StoreHoursValidator
   */
  protected $hoursValidator;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * Constructs a new StoreSelectionForm.
   *
   * @param \Drupal\store_resolver\StoreResolver $store_resolver
   *   The store resolver service.
   * @param \Drupal\store_resolver\StoreHoursValidator $hours_validator
   *   The store hours validator service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(StoreResolver $store_resolver, StoreHoursValidator $hours_validator, EntityTypeManagerInterface $entity_type_manager) {
    $this->storeResolver = $store_resolver;
    $this->hoursValidator = $hours_validator;
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('store_resolver.current_store'),
      $container->get('store_resolver.hours_validator'),
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'store_resolver_store_selection_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $stores = $this->storeResolver->getAvailableStores();
    $current_store = $this->storeResolver->getCurrentStore();

    if (empty($stores)) {
      $form['message'] = [
        '#markup' => $this->t('No stores are currently available.'),
      ];
      return $form;
    }

    $options = [];
    $descriptions = [];

    foreach ($stores as $store) {
      $store_id = $store->id();
      $store_name = $store->getName();

      // Check if store is currently open.
      $is_open = $this->hoursValidator->isStoreOpen($store);
      $status_text = $is_open ? $this->t('Open') : $this->t('Closed');

      $options[$store_id] = $store_name;

      // Add address information if available.
      if ($store->hasField('address') && !$store->get('address')->isEmpty()) {
        $address = $store->get('address')->first();
        $descriptions[$store_id] = $this->t('@status - @city, @state', [
          '@status' => $status_text,
          '@city' => $address->locality,
          '@state' => $address->administrative_area,
        ]);
      }
      else {
        $descriptions[$store_id] = $status_text;
      }
    }

    $form['store_id'] = [
      '#type' => 'radios',
      '#title' => $this->t('Select a store'),
      '#options' => $options,
      '#default_value' => $current_store ? $current_store->id() : NULL,
      '#required' => TRUE,
    ];

    // Add descriptions to each radio option.
    foreach ($descriptions as $store_id => $description) {
      $form['store_id'][$store_id]['#description'] = $description;
    }

    $form['message'] = [
      '#markup' => '<div class="store-selection-note">' .
      $this->t('Please select your preferred store. If the store is currently closed, you can schedule your order for a future time.') .
      '</div>',
      '#weight' => -10,
    ];

    $form['actions'] = [
      '#type' => 'actions',
    ];

    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Continue'),
      '#button_type' => 'primary',
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $store_id = $form_state->getValue('store_id');

    // Set a cookie to persist the store selection for 30 days.
    // Using PHP setcookie() since the response object does not exist yet in
    // a form submit handler; Drupal's RedirectResponseSubscriber will respect
    // it on the subsequent request.
    setcookie(
      StoreResolver::STORE_COOKIE_NAME,
      (string) $store_id,
      [
        'expires' => strtotime('+30 days'),
        'path' => '/',
        'secure' => FALSE,
        'httponly' => FALSE,
        'samesite' => 'Lax',
      ]
    );

    $store = $this->entityTypeManager->getStorage('commerce_store')->load($store_id);
    if ($store) {
      $this->messenger()->addStatus($this->t('You have selected %store_name.', [
        '%store_name' => $store->getName(),
      ]));
    }

    // Redirect back to the destination if one was provided (e.g. checkout),
    // otherwise fall back to the front page.
    $destination = \Drupal::request()->query->get('destination', '');
    if ($destination && str_starts_with($destination, '/') && !str_starts_with($destination, '//')) {
      $form_state->setRedirectUrl(Url::fromUserInput($destination));
    }
    else {
      $form_state->setRedirect('<front>');
    }
  }

}
