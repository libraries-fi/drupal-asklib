<?php

namespace Drupal\asklib;

// use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Database\Connection as Database;
use Drupal\Core\Entity\EntityTypeManagerInterface;

class UserMailGroupHelper {
  protected $entityManager;
  protected $database;

  public function __construct(EntityTypeManagerInterface $entity_manager, Database $database) {
    $this->entityManager = $entity_manager;
    $this->database = $database;
  }

  public function getGroupsForUser($user_id, $active_only = FALSE) {
    return $this->getGroupsForUsers([$user_id], $active_only);
  }

  public function getGroupsForUsers(array $uids, $active_only = FALSE) {
    if (empty($uids)) {
      return [];
    }

    $storage = $this->entityManager->getStorage('taxonomy_term');

    $query = $storage->getQuery()
      ->condition('field_asklib_subscribers', $uids)
      ->sort('vid', 'DESC');

    if ($active_only) {
      $query->condition('field_asklib_active', 1);
    }

    $tids = $query->execute();

    $phs = implode(',', array_fill(0, count($uids), '?'));
    $query = sprintf('
      SELECT entity_id tid, field_asklib_subscribers_target_id uid
      FROM {taxonomy_term__field_asklib_subscribers}
      WHERE field_asklib_subscribers_target_id IN (%s)
    ', $phs);
    $smt = $this->database->prepareQuery($query);
    $smt->execute(array_values($uids));

    $tids = [];
    $this->user_cache = [];
    foreach ($smt as $row) {
      $this->user_cache[$row->uid][] = $row->tid;
      $tids[] = $row->tid;
    }

    $result = $this->entityManager->getStorage('taxonomy_term')->loadMultiple($tids);
    return $result;
  }

  public function setGroupsForUser($uid, array $gids) {
    $storage = $this->entityManager->getStorage('taxonomy_term');
    $tids = $storage->getQuery()
      ->condition('field_asklib_subscribers', $uid)
      ->execute();

    // Filter user from terms that the user is not subscribed to anymore.
    foreach ($storage->loadMultiple($tids) as $term) {
      $term->get('field_asklib_subscribers')->filter(function($field) use ($term, $uid, $gids) {
        return $field->target_id != $uid || in_array($term->id(), $gids);
      });
      $term->save();
    }

    // Process new terms that users should be subscribed to.
    $new_tids = array_diff($gids, $tids);
    foreach ($storage->loadMultiple($new_tids) as $term) {
      $term->get('field_asklib_subscribers')->appendItem($uid);
      $term->save();
    }
  }

  public function getUserMainGroup($user_id) {
    $user = $this->entityManager->getStorage('user')->load($user_id);

    if ($library = $user->get('field_asklib_library')->entity) {
      return $library;
    }

    $groups = $this->getGroupsForUser($user_id, TRUE);

    foreach ($groups as $i => $term) {
      if ($term->getVocabularyId() == 'asklib_municipalities') {
        return $term;
      }
    }

    return reset($groups);
  }
}
