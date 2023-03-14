<?php

namespace Drupal\asklib;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityListBuilder;
use Drupal\asklib\ProvideSearchForm;

class MailUserListBuilder extends EntityListBuilder
{
  use ProvideSearchForm;

  public function render() {
    // Do this first so that the form is initialized and 'submitted' before we use its values.
    $form = $this->searchForm();

    $elements = parent::render();
    $elements['form'] = $form;
    $elements['form']['#weight'] = -100;

    return $elements;
  }

  public function buildHeader()
  {
    return [
      'name' => $this->t('Name'),
      'email' => $this->t('Email'),
      'groups' => $this->t('Groups'),
    ] + parent::buildHeader();
  }

  public function buildRow(EntityInterface $user)
  {
    $row = [];
    $groups = [];
    foreach ($this->groups($user->id()) as $tid) {
      $groups[] = $this->term_cache[$tid]->label();
    }
    asort($groups);

    $row['name'] = $user->label();
    $row['email'] = $user->get('field_asklib_mail')->value ?: $user->getEmail();
    $row['groups'] = implode(', ', $groups);
    return $row + parent::buildRow($user);
  }

  public function load()
  {
    $uids = $this->getEntityIds();
    $users = $this->getStorage()->loadMultiple($uids);
    $this->cacheGroups($uids);
    return $users;
  }

  protected function getEntityIds() {
    $query = $this->getStorage()->getQuery()
      // ->sort($this->entityType->getKey('label'))
      ->sort('name')
      ->sort($this->entityType->getKey('id'))
      ->condition('roles', ['asklib_admin', 'asklib_librarian'], 'IN');

    if ($this->searchQuery) {
      $query->condition('name', '%' . $this->searchQuery . '%', 'LIKE');
    }

    if ($this->limit) {
      $query->pager($this->limit);
    }

    return $query->accessCheck(FALSE)->execute();
  }

  protected function getDefaultOperations(EntityInterface $term) {
    $ops = parent::getDefaultOperations($term);

    $ops['edit'] = [
      'title' => $this->t('Edit'),
      'url' => $term->toUrl('asklib-mail-group-form'),
      'weight' => 0,
    ];

    return $ops;
  }

  private function groups($uid) {
    return empty($this->user_cache[$uid]) ? [] : $this->user_cache[$uid];
  }

  private function cacheGroups($uids) {
    if (empty($uids)) {
      return;
    }
    $phs = implode(',', array_fill(0, is_countable($uids) ? count($uids) : 0, '?'));
    $query = sprintf('
      SELECT entity_id tid, field_asklib_subscribers_target_id uid
      FROM {taxonomy_term__field_asklib_subscribers}
      WHERE field_asklib_subscribers_target_id IN (%s)
    ', $phs);
    $smt = \Drupal::service('database')->prepareQuery($query);
    $smt->execute(array_values($uids));

    $tids = [];
    $this->user_cache = [];
    foreach ($smt as $row) {
      $this->user_cache[$row->uid][] = $row->tid;
      $tids[] = $row->tid;
    }

    $this->term_cache = \Drupal::service('entity_type.manager')->getStorage('taxonomy_term')->loadMultiple($tids);
  }
}
