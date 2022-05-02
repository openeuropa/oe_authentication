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
class UserSanitizeCommand extends DrushCommands implements SanitizePluginInterface {

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
    \Drupal::database()->update('users_field_data')
      ->fields(['field_oe_firstname' => 'First Name', 'field_oe_lastname' => 'Last Name', 'field_oe_department' => 'Department', 'field_oe_organisation' => 'Organisation'])
      ->execute();
    $this->logger->success('User fields have been sanitised.');
  }

  /**
   * Sets the output message.
   *
   * @hook on-event sql-sanitize-confirms
   *
   * @inheritdoc
   */
  public function messages(&$messages, InputInterface $input) {
    $messages[] = dt('Sanitise user fields.');
  }

}
