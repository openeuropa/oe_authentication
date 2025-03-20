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
   */
  protected FilteredPluginManagerInterface $conditionManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $instance = parent::create($container);

    $instance->conditionManager = $container->get('plugin.manager.condition');
    $instance->messenger = $container->get('messenger');

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
    $config = $this->config(static::CONFIG_NAME);
    $form['protocol'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Application authentication protocol'),
      '#default_value' => $config->get('protocol'),
    ];
    $form['register_path'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Application register path'),
      '#default_value' => $config->get('register_path'),
    ];
    $form['validation_path'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Application validation path'),
      '#default_value' => $config->get('validation_path'),
    ];
    $form['assurance_level'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Application assurance levels'),
      '#default_value' => $config->get('assurance_level'),
    ];
    $form['ticket_types'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Application available ticket types'),
      '#default_value' => $config->get('ticket_types'),
    ];

    $require_2fa = match ($config->get('force_2fa')) {
      TRUE => 'always',
      FALSE => empty($config->get('2fa_conditions')) ? 'never' : 'conditions',
    };

    $form['require_2fa'] = [
      '#type' => 'radios',
      '#title' => $this->t('Require two-factor authentication'),
      '#options' => [
        'never' => $this->t('Never'),
        'always' => $this->t('Always'),
        'conditions' => $this->t('Based on conditions'),
      ],
      '#default_value' => $require_2fa,
    ];

    $form['2fa_conditions'] = [
      '#tree' => TRUE,
      '#type' => 'details',
      '#open' => TRUE,
      '#title' => $this->t('Two-factor authentication conditions'),
      '#states' => [
        'visible' => [
          ':input[name="require_2fa"]' => ['value' => 'conditions'],
        ],
      ],
    ];
    $form['2fa_conditions'] = $this->buildTwoFactorConditionsInterface($form['2fa_conditions'], $form_state);

    $form['message_login_2fa_required'] = [
      '#type' => 'textfield',
      '#size' => 128,
      '#maxlength' => 254,
      '#title' => $this->t('Message for login rejected: two-factor authentication required'),
      '#description' => $this->t('This message is displayed when a login attempt is rejected because two-factor authentication is required but has not been used for the login attempt.'),
      '#default_value' => $config->get('message_login_2fa_required'),
    ];

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
    $form['intro'] = [
      '#type' => 'inline_template',
      '#template' => '<p><small>{{ explanation }}</small></p><p><small>{{ warning }}</small></p>',
      '#context' => [
        'explanation' => $this->t('Two-factor authentication will be required to log in <strong>only if at least one condition</strong> is enabled and successfully matches the account that is attempting to log in.'),
        'warning' => $this->t('Conditions are automatically disabled when <strong>no configuration is provided</strong>. This is necessary because conditions in their default configuration always evaluate to TRUE.'),
      ],
    ];

    $defaults = $this->config(static::CONFIG_NAME)->get('2fa_conditions');
    $form['status'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Enabled conditions'),
      '#options' => [],
      '#default_value' => array_keys($defaults ?? []),
    ];
    $form['condition_tabs'] = [
      '#type' => 'vertical_tabs',
      '#title' => $this->t('Condition configuration'),
      '#parents' => ['condition_tabs'],
    ];
    $form['settings'] = [];

    foreach ($this->getUserConditionDefinitions() as $condition_id => $definition) {
      /** @var \Drupal\Core\Condition\ConditionInterface $condition */
      $condition = $this->conditionManager->createInstance($condition_id, $defaults[$condition_id] ?? []);
      $form_state->set(['2fa_conditions', $condition_id], $condition);
      $condition_form = $condition->buildConfigurationForm([], $form_state);
      $condition_form['#type'] = 'details';
      $condition_form['#title'] = $condition->getPluginDefinition()['label'];
      $condition_form['#group'] = 'condition_tabs';

      $form['settings'][$condition_id] = $condition_form;
      $form['status']['#options'][$condition_id] = $condition->getPluginDefinition()['label'];
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
    // required, and plugins with optional context definitions.
    // We need to filter those out manually, and return only conditions that
    // require one single user context.
    return array_filter($definitions, function ($definition): bool {
      // @see \Drupal\Core\Plugin\Context\ContextHandler::getContextDefinitions()
      if ($definition instanceof ContextAwarePluginDefinitionInterface) {
        $context_definitions = $definition->getContextDefinitions();
      }
      elseif (is_array($definition) && isset($definition['context_definitions'])) {
        $context_definitions = $definition['context_definitions'];
      }
      else {
        return FALSE;
      }

      if (empty($context_definitions) || count($context_definitions) > 1) {
        return FALSE;
      }

      // At this point the single context definition available is a user entity
      // one. If it's required, the plugin is allowed.
      return current($context_definitions)->isRequired();
    });
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);

    $enabled_conditions_ids = array_filter($form_state->getValue(['2fa_conditions', 'status']));
    foreach ($enabled_conditions_ids as $condition_id) {
      // Allow the condition to validate the form.
      $condition = $form_state->get(['2fa_conditions', $condition_id]);
      $condition->validateConfigurationForm(
        $form['2fa_conditions']['settings'][$condition_id],
        SubformState::createForSubform($form['2fa_conditions']['settings'][$condition_id], $form, $form_state),
      );
    }

    // When the conditions mode is active, at least one condition must be set.
    if (empty($enabled_conditions_ids) && $form_state->getValue('require_2fa') === 'conditions') {
      $form_state->setError($form['2fa_conditions']['status'], $this->t('At least one condition should be enabled when two-factor authentication is set to conditional.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $force_2fa = FALSE;
    $conditions_configuration = [];
    switch ($form_state->getValue('require_2fa')) {
      case 'always':
        $force_2fa = TRUE;
        break;

      case 'conditions':
        $conditions_configuration = $this->collect2FaConditionsConfiguration($form, $form_state);
        break;
    }

    $this->config(static::CONFIG_NAME)
      ->set('protocol', $form_state->getValue('protocol'))
      ->set('register_path', $form_state->getValue('register_path'))
      ->set('validation_path', $form_state->getValue('validation_path'))
      ->set('assurance_level', $form_state->getValue('assurance_level'))
      ->set('ticket_types', $form_state->getValue('ticket_types'))
      ->set('force_2fa', $force_2fa)
      ->set('2fa_conditions', $conditions_configuration)
      ->set('message_login_2fa_required', $form_state->getValue('message_login_2fa_required'))
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
    $enabled_plugin_labels = [];
    $collection = new ConditionPluginCollection($this->conditionManager);
    $enabled_conditions_ids = array_filter($form_state->getValue(['2fa_conditions', 'status']));
    foreach ($enabled_conditions_ids as $condition_id) {
      // Allow the condition to submit the form.
      $condition = $form_state->get(['2fa_conditions', $condition_id]);
      $condition->submitConfigurationForm(
        $form['2fa_conditions']['settings'][$condition_id],
        SubformState::createForSubform($form['2fa_conditions']['settings'][$condition_id], $form, $form_state),
      );

      $condition_configuration = $condition->getConfiguration();
      $collection->addInstanceId($condition_id, $condition_configuration);

      // Keep the label of the plugin since we have it instantiated already.
      $enabled_plugin_labels[$condition_id] = $condition->getPluginDefinition()['label'];
    }

    $configuration = $collection->getConfiguration();
    // Warn the user for any condition that has been disabled.
    $default_config_plugins = array_diff_key($enabled_plugin_labels, $configuration);
    if (empty($configuration) && !empty($enabled_conditions_ids)) {
      $this->messenger->addWarning('All condition plugins have been disabled, as no configuration was provided. Two-factor authentication has been set to "Never".');
    }
    elseif (!empty($default_config_plugins)) {
      $this->messenger->addWarning($this->t(
        'The following condition plugins have been disabled, as no configuration was provided: %plugins.',
        [
          '%plugins' => implode(', ', $default_config_plugins),
        ],
      ));
    }

    return $configuration;
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['oe_authentication.settings'];
  }

}
