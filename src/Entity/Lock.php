<?php

namespace Drupal\asklib\Entity;

use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\asklib\QuestionInterface;
use Drupal\asklib\LockInterface;

/**
 * @ContentEntityType(
 *   id = "asklib_lock",
 *   label = @Translation("Question Lock"),
 *   handlers = {
 *     "access" = "Drupal\Core\Entity\EntityAccessControlHandler",
 *     "views_data" = "Drupal\asklib\LockViewsData"
 *   },
 *   base_table = "asklib_locks",
 *   translatable = FALSE,
 *   entity_keys = {
 *     "id" = "id",
 *     "uid" = "user",
 *     "label" = "id",
 *   },
 *   links = {
 *   }
 * )
 */
class Lock extends ContentEntityBase implements LockInterface {
  public function userId() {
    return $this->user->target_id;
  }

  public function setQuestion(QuestionInterface $question = null) {
    $this->question = $question;
  }

  public function getQuestion() {
    return $this->question->entity;
  }

  public function setUser($uid) {
    $this->user = $uid;
  }

  public function getUser() {
    return $this->user->entity;
  }

  public function getCreatedTime() {
    return $this->created->value;
  }

  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    $fields['id'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('ID'))
      ->setDescription(t('Lock ID'))
      ->setReadOnly(TRUE);

    $fields['question'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Question ID'))
      ->setDescription(t('Question this answer is bound to'))
      ->setSettings(['target_type' => 'asklib_question'])
      ->setRequired(TRUE)
      ->setDisplayConfigurable('form', false)
      ->setDisplayConfigurable('view', false);

    $fields['user'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Reserved to'))
      ->setDescription(t('User who has reserved the question'))
      ->setSettings(['target_type' => 'user'])
      ->setRequired(TRUE)
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel(t('Reservation time'))
      ->setDescription(t('Time when question was reserved.'))
      ->setDisplayConfigurable('view', FALSE)
      ->setDisplayOptions('view', [
        'type' => 'timestamp',
        'settings' => [
          'date_format' => 'short',
        ]
      ]);

    return $fields;
  }
}
