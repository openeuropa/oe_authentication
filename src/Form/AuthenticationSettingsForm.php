<?php

declare(strict_types=1);

namespace Drupal\oe_authentication\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManager;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\user\RoleInterface;

/**
 * Settings form for module.
 */
class AuthenticationSettingsForm extends ConfigFormBase {

  /**
   * Name of the config being edited.
   */
  const CONFIG_NAME = 'oe_authentication.settings';

  /**
   * Configuration form constructor.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   Config factory.
   * @param \Drupal\Core\Entity\EntityTypeManager $entityTypeManager
   *   The entityTypeManager.
   */
  public function __construct(
    ConfigFactoryInterface $configFactory,
    EntityTypeManager $entityTypeManager,
  ) {
    parent::__construct($configFactory);
    $this->entityTypeManager = $entityTypeManager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('entity_type.manager'),
    );
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

    /** @var \Drupal\user\RoleStorageInterface $role_storage */
    $roleStorage = $this->entityTypeManager->getStorage('user_role');
    /** @var \Drupal\user\RoleInterface[] $roles */
    $roles = $roleStorage->loadMultiple();
    $rolesAvailable = [];

    foreach ($roles as $role) {
      if ($role->id() == RoleInterface::ANONYMOUS_ID) {
        continue;
      }
      $rolesAvailable[$role->id()] = $role->label();
    }

    $form['authentication_roles'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Roles that are forced to login with 2FA.'),
      '#description' => $this->t('The roles that will be forced to log in with 2FA.'),
      '#options' => $rolesAvailable,
      '#default_value' => $this->config(static::CONFIG_NAME)->get('authentication_roles') ?? [],
      '#states' => [
        'enabled' => [
          // Enable the roles only if the force 2fa for all is unchecked.
          ':input[name="force_2fa"]' => ['checked' => FALSE],
        ],
      ],
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
      ->set('force_2fa', (bool) $form_state->getValue('force_2fa'))
      ->set('authentication_roles', $form_state->getValue('authentication_roles'))
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
