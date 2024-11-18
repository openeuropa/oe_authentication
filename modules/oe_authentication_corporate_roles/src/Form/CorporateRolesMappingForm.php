<?php

declare(strict_types=1);

namespace Drupal\oe_authentication_corporate_roles\Form;

use Drupal\Component\Utility\Html;
use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Form\FormStateInterface;
use Drupal\oe_authentication_corporate_roles\CorporateRolesMappingLookup;
use Drupal\oe_authentication_corporate_roles\Entity\CorporateRolesMapping;
use Drupal\user\RoleInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Corporate roles mapping form.
 */
final class CorporateRolesMappingForm extends EntityForm {

  /**
   * The corporate mapping lookup service.
   *
   * @var \Drupal\oe_authentication_corporate_roles\CorporateRolesMappingLookup
   */
  protected $mappingLookup;

  /**
   * Constructs a CorporateRolesMappingForm.
   *
   * @param \Drupal\oe_authentication_corporate_roles\CorporateRolesMappingLookup $mappingLookup
   *   The corporate mapping lookup service.
   */
  public function __construct(CorporateRolesMappingLookup $mappingLookup) {
    $this->mappingLookup = $mappingLookup;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('oe_authentication_corporate_roles.mapping_lookup')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state): array {

    $form = parent::form($form, $form_state);

    $form['label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Label'),
      '#maxlength' => 255,
      '#default_value' => $this->entity->label(),
      '#required' => TRUE,
    ];

    $form['id'] = [
      '#type' => 'machine_name',
      '#default_value' => $this->entity->id(),
      '#machine_name' => [
        'exists' => [CorporateRolesMapping::class, 'load'],
      ],
      '#disabled' => !$this->entity->isNew(),
    ];

    $form['matching_value_type'] = [
      '#type' => 'select',
      '#title' => $this->t('Matching type'),
      '#options' => [
        CorporateRolesMapping::DEPARTMENT => $this->t('Department'),
        CorporateRolesMapping::LDAP_GROUP => $this->t('LDAP group'),
      ],
      '#required' => TRUE,
      '#default_value' => $this->entity->get('matching_value_type'),
    ];

    $form['value'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Value'),
      '#maxlength' => 255,
      '#default_value' => $this->entity->get('value'),
      '#description' => $this->t('The value to match against: either the LDAP group or the department. If department, you can broaden the match by using parts of the department. For example "COMM.B.3" or "COMM.B".'),
      '#required' => TRUE,
    ];

    $roles = $this->entityTypeManager->getStorage('user_role')->loadMultiple();
    unset($roles[RoleInterface::ANONYMOUS_ID]);
    unset($roles[RoleInterface::AUTHENTICATED_ID]);
    $roles = array_map(fn(RoleInterface $role) => Html::escape($role->label()), $roles);

    $form['account']['roles'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Roles'),
      '#default_value' => $this->entity->get('roles'),
      '#options' => $roles,
      '#required' => TRUE,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $roles = $form_state->getValue('roles');
    $roles = array_filter($roles);
    $form_state->setValue('roles', $roles);
    parent::submitForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state): int {
    $result = parent::save($form, $form_state);
    $message_args = ['%label' => $this->entity->label()];
    $this->messenger()->addStatus(
      match($result) {
        \SAVED_NEW => $this->t('Created new %label.', $message_args),
        \SAVED_UPDATED => $this->t('Updated %label.', $message_args),
      }
    );
    $form_state->setRedirectUrl($this->entity->toUrl('collection'));

    return $result;
  }

}
