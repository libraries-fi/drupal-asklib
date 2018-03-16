<?php

namespace Drupal\asklib;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityAccessControlHandler;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;

class AnswerAccessControlHandler extends EntityAccessControlHandler {
  protected function checkAccess(EntityInterface $answer, $operation, AccountInterface $account) {
    if ($operation == 'view' && $answer->getQuestion()->isPublished()) {
      return AccessResult::allowed();
    }

    if ($account->hasPermission('administer asklib')) {
      return AccessResult::allowed();
    } else if ($account->hasPermission('answer questions') && $operation != 'delete') {
      return AccessResult::allowed();
    }

    return AccessResult::neutral();
  }
}
