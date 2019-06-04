<?php

declare(strict_types = 1);

namespace Drupal\oe_authentication\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Routing\RouteBuilderInterface;
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
   * The route builder service.
   *
   * @var \Drupal\Core\Routing\RouteBuilderInterface
   */
  protected $routeBuilder;

  /**
   * Constructs a new form instance.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The factory for configuration objects.
   * @param \Drupal\Core\Routing\RouteBuilderInterface $route_builder
   *   The route builder service.
   */
  public function __construct(ConfigFactoryInterface $config_factory, RouteBuilderInterface $route_builder) {
    parent::__construct($config_factory);
    $this->routeBuilder = $route_builder;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('router.builder')
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
    $form['redirect_user_register_route'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Redirect user registration route to EU Login'),
      '#default_value' => $this->config(static::CONFIG_NAME)->get('redirect_user_register_route'),
    ];
    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $original_redirect_user_register_route = $this->config(static::CONFIG_NAME)
      ->get('redirect_user_register_route');

    $this->config(static::CONFIG_NAME)
      ->set('protocol', $form_state->getValue('protocol'))
      ->set('register_path', $form_state->getValue('register_path'))
      ->set('validation_path', $form_state->getValue('validation_path'))
      ->set('assurance_level', $form_state->getValue('assurance_level'))
      ->set('ticket_types', $form_state->getValue('ticket_types'))
      ->set('redirect_user_register_route', $form_state->getValue('redirect_user_register_route'))
      ->save();
    parent::submitForm($form, $form_state);

    // Rebuild the routes if the redirect user register config has changed.
    if ($original_redirect_user_register_route != $form_state->getValue('redirect_user_register_route')) {
      $this->routeBuilder->rebuild();
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['oe_authentication.settings'];
  }

}
