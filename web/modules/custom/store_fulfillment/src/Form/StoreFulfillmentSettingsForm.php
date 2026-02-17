<?php

declare(strict_types=1);

namespace Drupal\store_fulfillment\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Configure store fulfillment settings.
 */
class StoreFulfillmentSettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['store_fulfillment.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'store_fulfillment_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('store_fulfillment.settings');

    $form['scheduling'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Scheduling Settings'),
    ];

    $form['scheduling']['minimum_advance_notice'] = [
      '#type' => 'number',
      '#title' => $this->t('Minimum advance notice'),
      '#description' => $this->t('Minimum time in minutes between order placement and scheduled fulfillment time. Default: 30 minutes.'),
      '#default_value' => $config->get('minimum_advance_notice') ?? 30,
      '#min' => 0,
      '#max' => 1440,
      '#required' => TRUE,
      '#field_suffix' => $this->t('minutes'),
    ];

    $form['scheduling']['maximum_scheduling_window'] = [
      '#type' => 'number',
      '#title' => $this->t('Maximum scheduling window'),
      '#description' => $this->t('Maximum number of days in advance that orders can be scheduled. Default: 14 days.'),
      '#default_value' => $config->get('maximum_scheduling_window') ?? 14,
      '#min' => 1,
      '#max' => 90,
      '#required' => TRUE,
      '#field_suffix' => $this->t('days'),
    ];

    $form['scheduling']['asap_cutoff_before_closing'] = [
      '#type' => 'number',
      '#title' => $this->t('ASAP cutoff before closing'),
      '#description' => $this->t('Stop accepting ASAP orders this many minutes before store closing time. Set to 0 to allow ASAP orders until closing. Default: 15 minutes.'),
      '#default_value' => $config->get('asap_cutoff_before_closing') ?? 15,
      '#min' => 0,
      '#max' => 120,
      '#required' => TRUE,
      '#field_suffix' => $this->t('minutes'),
    ];

    $form['time_slots'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Time Slot Settings'),
    ];

    $form['time_slots']['time_slot_interval'] = [
      '#type' => 'select',
      '#title' => $this->t('Time slot interval'),
      '#description' => $this->t('Interval between available time slots in the schedule picker.'),
      '#options' => [
        15 => $this->t('15 minutes'),
        30 => $this->t('30 minutes'),
        60 => $this->t('1 hour'),
      ],
      '#default_value' => $config->get('time_slot_interval') ?? 15,
      '#required' => TRUE,
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->config('store_fulfillment.settings')
      ->set('minimum_advance_notice', $form_state->getValue('minimum_advance_notice'))
      ->set('maximum_scheduling_window', $form_state->getValue('maximum_scheduling_window'))
      ->set('asap_cutoff_before_closing', $form_state->getValue('asap_cutoff_before_closing'))
      ->set('time_slot_interval', $form_state->getValue('time_slot_interval'))
      ->save();

    parent::submitForm($form, $form_state);
  }

}
