<?php

declare(strict_types=1);

namespace Drupal\oe_authentication_corporate_roles;

use Drupal\Core\Config\Entity\ConfigEntityListBuilder;
use Drupal\Core\Entity\EntityInterface;

/**
 * Provides a listing of corporate roles mappings.
 */
final class CorporateRolesMappingListBuilder extends ConfigEntityListBuilder {

  /**
   * {@inheritdoc}
   */
  public function buildHeader(): array {
    $header['label'] = $this->t('Label');
    $header['id'] = $this->t('Machine name');
    $header['matching_value_type'] = $this->t('Matching type');
    $header['value'] = $this->t('Value');
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity): array {
    /** @var \Drupal\oe_authentication_corporate_roles\CorporateRolesMappingInterface $entity */
    $row['label'] = $entity->label();
    $row['id'] = $entity->id();
    $row['matching_value_type'] = $entity->get('matching_value_type');
    $row['value'] = $entity->get('value');
    return $row + parent::buildRow($entity);
  }

}
