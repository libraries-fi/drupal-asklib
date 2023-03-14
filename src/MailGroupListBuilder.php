<?php

namespace Drupal\asklib;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityListBuilder;
use Drupal\asklib\Entity\Question;
use Drupal\asklib\ProvideSearchForm;

class MailGroupListBuilder extends EntityListBuilder {
  use ProvideSearchForm;

  public function render() {
    // Do this first so that the form is initialized and 'submitted' before we use its values.
    $form = $this->searchForm();

    $elements = parent::render();
    $elements['form'] = $form;
    $elements['form']['#weight'] = -100;

    return $elements;
  }

  public function buildHeader() {
    return [
      'name' => $this->t('Name'),
      'type' => $this->t('Category'),
      'enabled' => $this->t('Status'),
    ] + parent::buildHeader();
  }

  public function buildRow(EntityInterface $term) {
    $row = [];
    $status = [
      $this->t('Disabled'),
      $this->t('Enabled'),
    ];
    $row['name']['data'] = [
      '#type' => 'link',
      '#title' => $term->label(),
      '#url' => $term->toUrl(),
    ];
    $row['type'] = $term->vid->entity->label();
    $row['enabled'] = $status[$term->get('field_asklib_active')->value];
    return $row + parent::buildRow($term);
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

  protected function getEntityIds() {
    $query = $this->getStorage()->getQuery()
      ->sort($this->entityType->getKey('label'))
      ->sort($this->entityType->getKey('id'))
      ->condition('vid', ['asklib_libraries', 'asklib_municipalities'], 'in');

    if ($this->searchQuery) {
      $field = $this->entityType->getKey('label');
      $query->condition($field, '%' . $this->searchQuery . '%', 'LIKE');
    }

    if ($this->limit) {
      $query->pager($this->limit);
    }

    return $query->execute();
  }
}
