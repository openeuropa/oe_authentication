<?php declare(strict_types = 1);

$ignoreErrors = [];
$ignoreErrors[] = [
  // identifier: class.implementsDeprecatedInterface
  'message' => '#implements deprecated interface Drush\\\\Drupal\\\\Commands\\\\sql\\\\SanitizePluginInterface#',
  'count' => 1,
  'path' => __DIR__ . '/modules/oe_authentication_user_fields/src/Drush/Commands/sql/UserSanitizeCommand.php',
];

return ['parameters' => ['ignoreErrors' => $ignoreErrors]];
