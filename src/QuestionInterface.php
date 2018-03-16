<?php

namespace Drupal\asklib;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityPublishedInterface;

interface QuestionInterface extends ContentEntityInterface, EntityPublishedInterface {
  const STATE_OPEN = 0;
  const STATE_RESERVED = 1;
  const STATE_ANSWERED = 2;

  const NO_NOTIFICATIONS = 0;
  const NOTIFY_AUTHOR = 1;
  const NOTIFY_SUBSCRIBERS = 2;

  public function getAdminNotes();
  public function setAdminNotes($details);
}
