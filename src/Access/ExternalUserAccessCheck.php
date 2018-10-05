<?php

declare(strict_types = 1);

namespace Drupal\oe_authentication\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Routing\Access\AccessInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\externalauth\AuthMapInterface;

/**
 * Checks whether a user is external or not.
 */
class ExternalUserAccessCheck implements AccessInterface {

  /**
   * The external authentication map.
   *
   * @var \Drupal\externalauth\Authmap
   */
  protected $authMap;

  /**
   * Constructors the ExternalUserAccessCheck.
   *
   * @param \Drupal\externalauth\AuthMapInterface $authMap
   *   The external authentication map.
   */
  public function __construct(AuthMapInterface $authMap) {
    $this->authMap = $authMap;
  }

  /**
   * Checks access.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The logged in user key.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  public function access(AccountInterface $account):AccessResultInterface {
    $uid = $account->id();
    $userMapping = $this->authMap->getAll($uid);
    if (empty($userMapping)) {
      return AccessResult::allowed();
    }
    return AccessResult::forbidden();
  }

}
