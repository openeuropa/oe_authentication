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

    $form['notice'] = [
      '#plain_text' => $this->t('You are about to be logged out of EU Login.'),
    ];

    $form['container'] = [
      '#type' => 'container',
    ];
    $form['container']['actions'] = ['#type' => 'actions'];
    $form['container']['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Log me out'),
      '#button_type' => 'primary',
    ];
    $form['container']['actions']['cancel'] = [
      '#title' => $this->t('No, stay logged in!'),
      '#type' => 'link',
      '#attributes' => ['class' => ['button']],
      '#url' => Url::fromUri($url),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $this->messenger()->addStatus($this->t('You have logged out from EU Login.'));
    $url = $form_state->getValue('url');
    $form_state->setRedirectUrl(Url::fromUri($url));
  }

}
