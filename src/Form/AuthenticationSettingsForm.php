<?php

declare(strict_types=1);

namespace Drupal\oe_authentication\Form;

use Drupal\Component\Plugin\Definition\ContextAwarePluginDefinitionInterface;
use Drupal\Core\Condition\ConditionPluginCollection;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Form\SubformState;
use Drupal\Core\Plugin\Context\EntityContext;
use Drupal\Core\Plugin\FilteredPluginManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Settings form for module.
 */
class AuthenticationSettingsForm extends ConfigFormBase {

  /**
   * Name of the config being edited.
   */
  const CONFIG_NAME = 'oe_authentication.settings';

  /**
   * The condition manager.
   *
   * @var \Drupal\Core\Plugin\FilteredPluginManagerInterface
   * @phpstan-ignore property.uninitializedReadonly
   */
  protected readonly FilteredPluginManagerInterface $conditionManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $instance = parent::create($container);

    $instance->conditionManager = $container->get('plugin.manager.condition');

    return $instance;
  }

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
    $form['force_2fa'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Force two factor authentication'),
      '#default_value' => $this->config(static::CONFIG_NAME)->get('force_2fa'),
    ];

    $form['2fa_conditions'] = $this->buildTwoFactorConditionsInterface([], $form_state);

    return parent::buildForm($form, $form_state);
  }

  /**
   * Builds the 2FA conditions interface.
   *
   * @param array $form
   *   The pre-existing form structure array where the interface is placed.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   *
   * @return array
   *   The interface sub-form array.
   */
  protected function buildTwoFactorConditionsInterface(array $form, FormStateInterface $form_state): array {
    $form['#tree'] = TRUE;
    $form['condition_tabs'] = [
      '#type' => 'vertical_tabs',
      '#title' => $this->t('Two-factor authentication conditions'),
      '#description' => $this->t('Two-factor authentication will be required to log in <strong>only if at least one condition</strong> successfully matches the account that is attempting to log in. Conditions apply only if two-factor authentication is enabled.'),
      '#parents' => ['condition_tabs'],
      '#states' => [
        'visible' => [
          ':input[name="force_2fa"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $defaults = $this->config(static::CONFIG_NAME)->get('2fa_conditions') ?? [];
    foreach ($this->getUserConditionDefinitions() as $condition_id => $definition) {
      /** @var \Drupal\Core\Condition\ConditionInterface $condition */
      $condition = $this->conditionManager->createInstance($condition_id, $defaults[$condition_id] ?? []);
      $form_state->set(['2fa_conditions', $condition_id], $condition);
      $condition_form = $condition->buildConfigurationForm([], $form_state);
      $condition_form['#type'] = 'details';
      $condition_form['#title'] = $condition->getPluginDefinition()['label'];
      $condition_form['#group'] = 'condition_tabs';
      $form[$condition_id] = $condition_form;
    }

    return $form;
  }

  /**
   * Retrieves all condition plugins that require a user entity context.
   *
   * @return \Drupal\Core\Condition\ConditionInterface[]
   *   A list of condition plugins.
   */
  protected function getUserConditionDefinitions(): array {
    $context = EntityContext::fromEntityTypeId('user');

    $definitions = $this->conditionManager->getFilteredDefinitions('oe_authentication_2fa', [$context]);
    // The ::getFilteredDefinitions() call above filters out plugins that match
    // the context definition, as well as plugins without any context definition
    // required. We need to filter those out manually.
    return array_filter($definitions, function ($definition) {
      // @see \Drupal\Core\Plugin\Context\ContextHandler::getContextDefinitions()
      if ($definition instanceof ContextAwarePluginDefinitionInterface) {
        return !empty($definition->getContextDefinitions());
      }
      if (is_array($definition) && isset($definition['context_definitions'])) {
        return !empty($definition['context_definitions']);
      }

      return FALSE;
    });
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);

    foreach ($form_state->getValue('2fa_conditions') as $condition_id => $values) {
      // Allow the condition to validate the form.
      $condition = $form_state->get(['2fa_conditions', $condition_id]);
      $condition->validateConfigurationForm(
        $form['2fa_conditions'][$condition_id],
        SubformState::createForSubform($form['2fa_conditions'][$condition_id], $form, $form_state),
      );
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $force_2fa = (bool) $form_state->getValue('force_2fa');
    $this->config(static::CONFIG_NAME)
      ->set('protocol', $form_state->getValue('protocol'))
      ->set('register_path', $form_state->getValue('register_path'))
      ->set('validation_path', $form_state->getValue('validation_path'))
      ->set('assurance_level', $form_state->getValue('assurance_level'))
      ->set('ticket_types', $form_state->getValue('ticket_types'))
      ->set('force_2fa', $force_2fa)
      // If 2FA is disabled, clear up any existing condition configuration.
      ->set('2fa_conditions', $force_2fa ? $this->collect2FaConditionsConfiguration($form, $form_state) : [])
      ->save();

    parent::submitForm($form, $form_state);
  }

  /**
   * Collects the configuration from the 2FA condition plugins.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   *
   * @return array
   *   The condition plugins configuration.
   */
  protected function collect2FaConditionsConfiguration(array $form, FormStateInterface $form_state): array {
    $collection = new ConditionPluginCollection($this->conditionManager);
    foreach ($form_state->getValue('2fa_conditions') as $condition_id => $values) {
      // Allow the condition to submit the form.
      $condition = $form_state->get(['2fa_conditions', $condition_id]);
      $condition->submitConfigurationForm(
        $form['2fa_conditions'][$condition_id],
        SubformState::createForSubform($form['2fa_conditions'][$condition_id], $form, $form_state),
      );

      $condition_configuration = $condition->getConfiguration();
      $collection->addInstanceId($condition_id, $condition_configuration);
    }

    return $collection->getConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['oe_authentication.settings'];
  }

}
