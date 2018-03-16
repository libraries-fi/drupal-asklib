<?php

namespace Drupal\asklib;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityAccessControlHandler;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;

class QuestionAccessControlHandler extends EntityAccessControlHandler {
  protected function checkAccess(EntityInterface $question, $operation, AccountInterface $account) {
    if ($operation == 'view' && $question->isPublished()) {
      return AccessResult::allowed();
    }

    if ($operation == 'email' && (!$question->getAnswer() || $question->getAnswer()->isNew())) {
      return AccessResult::forbidden()->addCacheTags(['asklib_question:' . $question->id()]);
    }

    if ($account->hasPermission('administer asklib')) {
      switch ($operation) {
        case 'reserve':
          if (!$question->isReserved()) {
            return AccessResult::allowed()->addCacheTags(['asklib_question:' . $question->id()]);
          }
          break;

        default:
          return AccessResult::allowed();
      }
    } else if ($account->hasPermission('answer questions')) {
      switch ($operation) {
        // Changing answerer library
        case 'override-library':
          if ($answer = $question->getAnswer()) {
            return AccessResult::allowedIf($answer->get('user')->target_id == $account->id());
          } else {
            // Be vary of recursion...
            return $this->checkAccess($question, 'edit', $account);
          }
          break;

        case 'reserve':
          if (!$question->isReserved()) {
            return AccessResult::allowed()->addCacheTags(['asklib_question:' . $question->id()]);
          }
          break;

        case 'release':
          if ($question->isReservedTo($account)) {
            return AccessResult::allowed()->addCacheTags(['asklib_question:' . $question->id()]);
          }
          break;

        default:
          if ($operation != 'delete') {
            return AccessResult::allowed();
          }
      }
    }

    return AccessResult::neutral();
  }
}
