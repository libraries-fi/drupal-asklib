<?php

namespace Drupal\asklib\EventSubscriber;

use DateTime;
use Drupal;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\asklib\Event\QuestionEvent;
use Drupal\asklib\QuestionInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class QuestionAnswered implements EventSubscriberInterface {
  protected $entities;

  public function __construct(EntityTypeManagerInterface $entities) {
    $this->entities = $entities;
  }

  public static function getSubscribedEvents() {
    return [
      'asklib.question_answered' => [
        ['changeQuestionState'],
        ['bindLibraryToAnswer'],
      ]
    ];
  }

  public function changeQuestionState(QuestionEvent $event) {
    $question = $event->getQuestion();
    $question->setAnsweredTime(new DateTime);
    $question->setState(QuestionInterface::STATE_ANSWERED);
    $question->release();
  }

  public function bindLibraryToAnswer(QuestionEvent $event) {
    $question = $event->getQuestion();
    $uid = Drupal::currentUser()->id();
    $groups = $this->entities->getStorage('taxonomy_term')->getQuery()
      ->condition('vid', ['asklib_libraries', 'asklib_municipalities'], 'IN')
      ->condition('field_asklib_subscribers', $uid)
      ->sort('vid')
      ->range(0, 1)
      ->execute();

    $question->getAnswer()->setLibrary(reset($groups));
  }
}
