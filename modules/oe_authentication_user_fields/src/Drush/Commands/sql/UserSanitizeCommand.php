<?php

declare(strict_types=1);

namespace Drupal\oe_authentication_user_fields\Drush\Commands\sql;

use Consolidation\AnnotatedCommand\CommandData;
use Consolidation\AnnotatedCommand\Hooks\HookManager;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drush\Attributes as CLI;
use Drush\Commands\DrushCommands;
use Drush\Drupal\Commands\sql\SanitizeCommands;
use Drush\Drupal\Commands\sql\SanitizePluginInterface;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Sanitizes the user fields related data.
 */
final class UserSanitizeCommand extends DrushCommands implements SanitizePluginInterface {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The database connection to use.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $connection;

  /**
   * EuLoginUserSanitizeCommand constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\Core\Database\Connection $connection
   *   The database connection to use.
   */
  public function __construct(EntityTypeManagerInterface $entityTypeManager, Connection $connection) {
    parent::__construct();
    $this->entityTypeManager = $entityTypeManager;
    $this->connection = $connection;
  }

  /**
   * Returns a new instance.
   *
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   *   The Drupal container.
   *
   * @return static
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('database')
    );
  }

  /**
   * {@inheritdoc}
   */
  #[CLI\Hook(type: HookManager::POST_COMMAND_HOOK, target: SanitizeCommands::SANITIZE)]
  public function sanitize($result, CommandData $commandData) {
    $this->connection->update('users_field_data')
      ->expression('field_oe_firstname', 'CONCAT(:fn_dummy_string, uid)', [
        ':fn_dummy_string' => 'First Name ',
      ])
      ->expression('field_oe_lastname', 'CONCAT(:ln_dummy_string, uid)', [
        ':ln_dummy_string' => 'Last Name ',
      ])
      ->expression('field_oe_department', 'CONCAT(:dep_dummy_string, uid)', [
        ':dep_dummy_string' => 'Department ',
      ])
      ->expression('field_oe_organisation', 'CONCAT(:org_dummy_string, uid)', [
        ':org_dummy_string' => 'Organisation ',
      ])
      ->execute();

    // Make sure that we don't have sensitive data of users in the cache.
    $this->entityTypeManager->getStorage('user')->resetCache();

    $this->logger->success('User fields have been sanitised.');
  }

  /**
   * {@inheritdoc}
   */
  #[CLI\Hook(type: HookManager::ON_EVENT, target: SanitizeCommands::CONFIRMS)]
  public function messages(&$messages, InputInterface $input) {
    $messages[] = dt('Sanitise user fields.');
  }

}
