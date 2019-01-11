<?php

declare(strict_types = 1);

namespace Drupal\oe_authentication\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Settings form for module.
 */
class AuthenticationSettingsForm extends ConfigFormBase {

  /**
   * Name of the config being edited.
   */
  const CONFIG_NAME = 'oe_authentication.settings';

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'oe_authentication_settings';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['protocol'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Application authentication protocol'),
      '#default_value' => $this->config(static::CONFIG_NAME)->get('protocol'),
    ];
    $form['register_path'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Application register path'),
      '#default_value' => $this->config(static::CONFIG_NAME)->get('register_path'),
    ];
    $form['validation_path'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Application validation path'),
      '#default_value' => $this->config(static::CONFIG_NAME)->get('validation_path'),
    ];
    $form['assurance_level'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Application assurance levels'),
      '#default_value' => $this->config(static::CONFIG_NAME)->get('assurance_level'),
    ];
    $form['ticket_types'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Application available ticket types'),
      '#default_value' => $this->config(static::CONFIG_NAME)->get('ticket_types'),
    ];
    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->config(static::CONFIG_NAME)
      ->set('protocol', $form_state->getValue('protocol'))
      ->set('register_path', $form_state->getValue('register_path'))
      ->set('validation_path', $form_state->getValue('validation_path'))
      ->set('assurance_level', $form_state->getValue('assurance_level'))
      ->set('ticket_types', $form_state->getValue('ticket_types'))
      ->save();
    parent::submitForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['oe_authentication.settings'];
  }

}
