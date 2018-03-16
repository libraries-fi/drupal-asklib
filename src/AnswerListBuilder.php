<?php

namespace Drupal\asklib;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityListBuilder;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\asklib\AnswerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

class AnswerListBuilder extends EntityListBuilder
{
  private $current_user;

  public static function createInstance(ContainerInterface $container, EntityTypeInterface $entity_type) {
    return new static(
      $entity_type,
      $container->get('entity.manager')->getStorage($entity_type->id()),
      $container->get('current_user')
    );
  }

  public function __construct(EntityTypeInterface $entity_type, EntityStorageInterface $storage, AccountProxyInterface $account) {
    parent::__construct($entity_type, $storage);
    $this->current_user = $account;
  }

  public function buildHeader()
  {
    return [
      'answer' => $this->t('Answer'),
      'rating' => $this->t('Score'),
      'created' => [
        'data' => $this->t('Created'),
        'class' => [RESPONSIVE_PRIORITY_LOW],
      ],
    ] + parent::buildHeader();
  }

  public function buildRow(EntityInterface $answer)
  {
    $row = [];
    $row['answer']['data'] = [
      '#type' => 'link',
      '#title' => $this->trimTitle($answer->getBody(), 80),
      '#url' => $answer->urlInfo(),
    ];
    $row['rating'] = $answer->getRating();
    $row['created'] = format_date($answer->getCreatedTime(), 'short');

    $row += parent::buildRow($answer);

    return $row;
  }

  protected function trimTitle($string, $length)
  {
    $parts = preg_split('/\s+/mi', $string);
    $string = '';
    foreach ($parts as $p) {
      $new = sprintf('%s %s', $string, $p);
      if (strlen($new) < $length) {
        $string = $new;
      } else {
        return $new;
      }
    }
    return $string;
  }

  protected function getDefaultOperations(EntityInterface $answer)
  {
    $ops = parent::getDefaultOperations($answer);
    // if ($answer->isReservedTo($this->current_user)) {
    //   $ops['answer'] = [
    //     'title' => $this->t('Answer'),
    //     'url' => $answer->urlInfo('answer-form'),
    //     'weight' => 0,
    //   ];
    // }
    $ops['edit'] = [
      'title' => $this->t('Edit'),
      'url' => $answer->urlInfo('edit-form'),
      'weight' => 5,
    ];
    $ops['delete'] = [
      'title' => $this->t('Delete'),
      'url' => $answer->urlInfo('delete-form'),
      'weight' => 10,
    ];
    return $ops;
  }
}
