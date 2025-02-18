<?php

declare(strict_types=1);

namespace Drupal\oe_authentication_test\Plugin\Condition;

use Drupal\Core\Condition\Attribute\Condition;
use Drupal\Core\Condition\ConditionPluginBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\Context\EntityContextDefinition;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Provides a test condition that requires a user as context.
 */
#[Condition(
  id: "oe_authentication_user_test",
  label: new TranslatableMarkup("User test condition"),
  context_definitions: [
    "user" => new EntityContextDefinition(
      data_type: "entity:user",
      label: new TranslatableMarkup("User"),
    ),
  ],
)]
class UserTestCondition extends ConditionPluginBase {

  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'example' => FALSE,
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function summary() {
    return $this->t('No summary for test plugin.');
  }

  /**
   * {@inheritdoc}
   */
  public function evaluate() {
    return (bool) $this->configuration['example'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    // A configuration entry, used only to test the saving of condition
    // configuration.
    $form['example'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Example configuration option'),
      '#default_value' => $this->configuration['example'],
    ];

    $form['invalid'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Do not click this'),
    ];

    return parent::buildConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state) {
    parent::validateConfigurationForm($form, $form_state);

    if ($form_state->getValue('invalid')) {
      $form_state->setError($form['invalid'], $this->t('You cannot select this field.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    $this->configuration['example'] = $form_state->getValue('example');

    parent::submitConfigurationForm($form, $form_state);
  }

}
