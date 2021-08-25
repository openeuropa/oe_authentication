<?php

declare(strict_types = 1);

namespace Drupal\oe_authentication_user_fields\Commands\sql;

use Drush\Commands\DrushCommands;
use Drush\Drupal\Commands\sql\SanitizePluginInterface;
use Consolidation\AnnotatedCommand\CommandData;
use Symfony\Component\Console\Input\InputInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;

/**
 * Sanitizes the user fields related data.
 */
class EuLoginUserSanitizeCommand extends DrushCommands implements SanitizePluginInterface {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * EuLoginUserSanitizeCommand constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   */
  public function __construct(EntityTypeManagerInterface $entityTypeManager) {
    parent::__construct();
    $this->entityTypeManager = $entityTypeManager;
  }

  /**
   * Sanitize the user data from the DB.
   *
   * @hook post-command sql-sanitize
   *
   * @inheritdoc
   */
  public function sanitize($result, CommandData $commandData) {
    /** @var \Drupal\user\Entity\User[] $users */
    $users = $this->entityTypeManager->getStorage('user')->loadMultiple();
    foreach ($users as $user) {
      $user->set('field_oe_firstname', 'FirstName' . $user->id());
      $user->set('field_oe_lastname', 'LastName' . $user->id());
      $user->set('field_oe_department', 'Department' . $user->id());
      $user->set('field_oe_organisation', 'Organisation' . $user->id());
      $user->save();
    }
    $this->logger->success('EU login user fields data are sanitized.');
  }

  /**
   * Sets the output message.
   *
   * @hook on-event sql-sanitize-confirms
   *
   * @inheritdoc
   */
  public function messages(&$messages, InputInterface $input) {
    $messages[] = dt('Sanitize EU Login users field values.');
  }

}
