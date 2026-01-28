<?php

namespace Drupal\asklib;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityPublishedInterface;

interface QuestionInterface extends ContentEntityInterface, EntityPublishedInterface {
  public const STATE_OPEN = 0;
  public const STATE_RESERVED = 1;
  public const STATE_ANSWERED = 2;

  public const NO_NOTIFICATIONS = 0;
  public const NOTIFY_AUTHOR = 1;
  public const NOTIFY_SUBSCRIBERS = 2;

  public function getAdminNotes();
  public function setAdminNotes($details);
}
