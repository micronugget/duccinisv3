<?php

namespace Drupal\commerce_feeds\Feeds\Target;

use Drupal\Core\Config\Entity\ConfigEntityStorageInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\feeds\FieldTargetDefinition;
use Drupal\feeds\Plugin\Type\Target\ConfigurableTargetInterface;
use Drupal\feeds\Plugin\Type\Target\FieldTargetBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines a commerce_price field mapper.
 *
 * @FeedsTarget(
 *   id = "commerce_feeds_price",
 *   field_types = {"commerce_price"}
 * )
 */
class Price extends FieldTargetBase implements ConfigurableTargetInterface, ContainerFactoryPluginInterface {

  /**
   * The storage for commerce_currency config entities.
   *
   * @var \Drupal\Core\Config\Entity\ConfigEntityStorageInterface
   */
  protected $currencyStorage;

  /**
   * Constructs a new Price object.
   *
   * @param array $configuration
   *   The plugin configuration.
   * @param string $plugin_id
   *   The plugin id.
   * @param array $plugin_definition
   *   The plugin definition.
   * @param \Drupal\Core\Config\Entity\ConfigEntityStorageInterface $currency_storage
   *   The storage for commerce_currency config entities.
   */
  public function __construct(array $configuration, $plugin_id, array $plugin_definition, ConfigEntityStorageInterface $currency_storage) {
    $this->currencyStorage = $currency_storage;

    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager')->getStorage('commerce_currency'),
    );
  }

  /**
   * {@inheritdoc}
   */
  protected static function prepareTarget(FieldDefinitionInterface $field_definition) {
    return FieldTargetDefinition::createFromFieldDefinition($field_definition)
      ->addProperty('number');
  }

  /**
   * {@inheritdoc}
   */
  protected function prepareValue($delta, array &$values) {
    parent::prepareValue($delta, $values);
    $values['currency_code'] = $this->configuration['currency_code'];
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    $currency_code = NULL;
    $currencies = $this->currencyStorage->loadMultiple();
    $currency_codes = array_keys($currencies);
    if (count($currency_codes) == 1) {
      $currency_code = reset($currency_codes);
    }
    return ['currency_code' => $currency_code];
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $currencies = $this->currencyStorage->loadMultiple();
    $currency_codes = array_keys($currencies);

    $form['currency_code'] = [
      '#type' => 'select',
      '#title' => $this->t('Currency'),
      '#options' => array_combine($currency_codes, $currency_codes),
      '#default_value' => $this->configuration['currency_code'],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function getSummary() {
    return [
      'unit' => [
        '#type' => 'item',
        '#markup' => $this->t('Currency: %currency_code', [
          '%currency_code' => $this->configuration['currency_code'],
        ]),
      ],
    ];
  }

}
