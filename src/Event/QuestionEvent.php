<?php

namespace Drupal\asklib\Event;

use Drupal\asklib\QuestionInterface;
use Symfony\Component\EventDispatcher\Event;

class QuestionEvent extends Event {
  private $question;
  private $attachments;

  public function __construct(QuestionInterface $question, array $attachments = []) {
    $this->question = $question;
    $this->attachments = $attachments;
  }

  public function getQuestion() {
    return $this->question;
  }

  public function getAttachments() {
    return $this->attachments;
  }

  public function hasAttachments() {
    return !empty($this->attachments);
  }
}
