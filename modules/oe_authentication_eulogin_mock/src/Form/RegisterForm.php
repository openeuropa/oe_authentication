<?php

declare(strict_types = 1);

namespace Drupal\oe_authentication_eulogin_mock\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Provides a CAS mock server form for registering.
 */
class RegisterForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'cas_mock_server_register';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $form['registering'] = [
      '#type' => 'inline_template',
      '#template' => '<h2>{{ title }}</h2><p>{{ message }}</p>',
      '#context' => [
        'title' => $this->t('Create an account'),
        'message' => $this->t('This page is part of the EU login mock.'),
      ],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
  }

}
