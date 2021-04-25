<?php

declare(strict_types = 1);

namespace Drupal\oe_authentication_eulogin_mock\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;

/**
 * Provides a CAS mock server form for logout.
 */
class LogoutForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'cas_mock_server_logout';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $url = $this->getRequest()->get('service') ?? $this->requestStack->getCurrentRequest()->get('url');
    $form['url'] = [
      '#type' => 'value',
      '#value' => $url,
    ];
    $form['actions'] = [
      '#type' => 'submit',
      '#value' => $this->t('Log me out'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $url = $form_state->getValue('url');
    $form_state->setRedirectUrl(Url::fromUri($url));
  }

}
