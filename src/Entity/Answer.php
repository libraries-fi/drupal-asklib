<?php

namespace Drupal\asklib\Entity;

use DateTime;
use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityChangedTrait;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\asklib\AnswerInterface;

/**
 * @ContentEntityType(
 *   id = "asklib_answer",
 *   label = @Translation("Answer"),
 *   handlers = {
 *     "access" = "Drupal\asklib\AnswerAccessControlHandler",
 *     "list_builder" = "Drupal\asklib\AnswerListBuilder",
 *     "views_data" = "Drupal\asklib\AnswerViewsData",
 *     "form" = {
 *       "add" = "Drupal\asklib\Form\ReplyForm",
 *       "edit" = "Drupal\asklib\Form\ReplyForm",
 *       "default" = "Drupal\asklib\Form\QuestionForm",
 *       "delete" = "Drupal\asklib\Form\DeleteForm",
 *       "email" = "Drupal\asklib\Form\AnswerEmailForm",
 *       "redirect" = "Drupal\asklib\Form\RedirectForm",
 *     },
 *   },
 *   base_table = "asklib_answers",
 *   revision_table = "asklib_answers_revision",
 *   translatable = FALSE,
 *   entity_keys = {
 *     "id" = "id",
 *     "uid" = "user",
 *     "uuid" = "uuid",
 *     "label" = "id",
 *     "langcode" = "langcode",
 *     "revision" = "vid",
 *   },
 * )
 */
class Answer extends ContentEntityBase implements AnswerInterface {
  use EntityChangedTrait;

  public static function rating($votes, $score) {
    $cap = 30;

    if ($votes <= 1) {
      $rating = $score * $votes*10;
    } else {
      /*
       * Calculate simple boost and round result to nearest five to get
       * values 0, 5, 10, ..., 95, 100.
       */
      $boost = log(min($votes, $cap), $cap) * ($score < 0 ? -1 : 1);
      $rating = $score / $votes + $boost;
      $rating = round($rating * 10) * 5;
    }
    return $rating;
  }

  /**
   * Returns TRUE if answer is not considered persistent nor contains any information.
   */
  public function isSafeToDelete() {
    return strlen('' . $this->getAnsweredTime() . $this->getBody() . $this->getDetails()) == 0;
  }

  public function getLibrary() {
    return $this->library->entity;
  }

  public function setLibrary($library) {
    $this->library = $library;
  }

  public function setAttachments(array $values) {
    $this->set('attachments', $values);
  }

  public function getAttachments() {
    return $this->get('attachments')->referencedEntities();
  }

  public function getQuestion() {
    return $this->question->entity;
  }

  public function label() {
    // exit('label');

    return 'ANSWER LABEL';
  }

  public function setQuestion($question) {
    /*
     * NOTE: Do not do $question->setQuestion($this) here as the question might have a newer
     * answer bound already.
     */
    $this->question = $question;
  }

  public function getScore() {
    return $this->score->value;
  }

  public function setScore($score) {
    $this->score = $score;
  }

  public function getVotes() {
    return $this->votes->value;
  }

  public function setVotes($votes) {
    $this->votes = $votes;
  }

  /**
   * Rating is calculated from number of votes and their combined score.
   */
  public function getRating() {
    return $this->rating->value;
  }

  public function getBody() {
    return $this->body->value;
  }

  public function setBody($body) {
    $this->body = $body;
  }

  public function getBodyFormat() {
    return $this->get('body')->format;
  }

  public function setBodyFormat($format) {
    return $this->get('body')->format = $format;
  }

  public function getDetails() {
    return $this->details->value;
  }

  public function setDetails($details) {
    $this->details = $details;
  }

  public function getEmailSentTime() {
    return $this->email_sent->value;
  }

  public function setEmailSentTime($time) {
    if ($time instanceof DateTime) {
      $time = $time->format('U');
    }
    $this->email_sent->setValue($time);
  }

  public function getAnsweredTime() {
    return $this->answered->value;
  }

  public function setAnsweredTime($time) {
    if ($time instanceof DateTime) {
      $time = $time->format('U');
    }
    $this->answered->setValue($time);
  }

  public function getUser() {
    return $this->user->entity;
  }

  public function setUser($user) {
    $this->user = $user;
  }

  /**
   * Convenience function for rating questions up or down.
   * @var $point either +1 or -1
   */
  public function addVote($point) {
    $this->setVotes($this->getVotes() + 1);
    $this->setScore($this->getScore() + $point);
  }

  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    /*
     * NOTE: When using setSettings(), even field defaults have to be
     * defined mnaually!
     */

    $fields = parent::baseFieldDefinitions($entity_type);

    $fields['question'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Question ID'))
      ->setDescription(t('Question this answer is bound to'))
      ->setSettings(['target_type' => 'asklib_question'])
      ->setRequired(TRUE)
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['user'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Answerer'))
      ->setRequired(TRUE)
      ->setDescription(t('User who answered the question'))
      ->setSettings(['target_type' => 'user'])
      ->setDefaultValueCallback('Drupal\asklib\Entity\Question::getCurrentUserId')
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['library'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Answered by'))
      ->setDescription(t('Answering library'))
      ->setSettings([
        'target_type' => 'taxonomy_term',
        'handler' => 'default',
        'handler_settings' => [
          'target_bundles' => [
            'asklib_municipalities' => 'asklib_municipalities',
          ],
          'sort' => [
            'field' => 'name',
            'direction' => 'ASC',
          ],
        ],
      ])
      ->setCardinality(1)
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', FALSE)
      ->setDisplayOptions('form', [
        'weight' => 70,
        'type' => 'options_select',
      ])
      ->setDisplayOptions('view', [
        'weight' => 50,
      ]);

    $fields['body'] = BaseFieldDefinition::create('text_long')
      ->setLabel(t('Answer'))
      ->setSetting('max_length', 10000)
      ->setRevisionable(TRUE)
      ->setDescription(t('Public answer to the question'))
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayOptions('form', [
        'weight' => 20,
      ])
      ->setDisplayOptions('view', [
        'label' => 'hidden',
        'weight' => -20,
      ]);

    $fields['details'] = BaseFieldDefinition::create('string_long')
      ->setLabel(t('Private answer'))
      ->setSetting('max_length', 10000)
      ->setDescription(t('Private reply to the user.'))
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', false)
      ->setDisplayOptions('form', [
        'weight' => 30,
      ]);

    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel(t('Created'))
      ->setDescription(t('The time that the node was created.'))
      ->setDisplayOptions('view', array(
        'type' => 'hidden',
      ));

    $fields['changed'] = BaseFieldDefinition::create('changed')
      ->setLabel(t('Updated'))
      ->setDescription(t('The time that the node was last edited.'))
      ->setDisplayConfigurable('view', FALSE)
      ->setDisplayOptions('view', [
        'type' => 'timestamp',
        'weight' => -151,
        'settings' => [
          'date_format' => 'date_only',
        ]
      ]);

    $fields['answered'] = BaseFieldDefinition::create('timestamp')
      ->setLabel(t('Answered'))
      ->setDescription(t('The time time this question was marked as answered.'))
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayOptions('view', [
        'type' => 'timestamp',
        'weight' => -152,
        'settings' => [
          'date_format' => 'date_only',
        ]
      ]);

    $fields['email_sent'] = BaseFieldDefinition::create('timestamp')
      ->setLabel(t('Email sent'))
      ->setDescription(t('The time when email response was sent to the questioner.'))
      ->setDisplayConfigurable('view', TRUE);

    $fields['attachments'] = BaseFieldDefinition::create('file')
      ->setLabel(t('Attachments'))
      ->setDescription(t('Files can be attached to the answer.'))
      ->setSettings([
        // 'target_type' => 'file',
        'file_directory' => 'asklib/answers',
        'file_extensions' => 'txt doc docx pdf ppt pptx png jpg jpeg',
        'max_filesize' => '4 MB',
      ])
      ->setCardinality(3)
      ->setDisplayConfigurable('view', false)
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayOptions('form', [
        'type' => 'file_generic',
        'weight' => 999,
      ]);

    $fields['rating'] = BaseFieldDefinition::create('kifiform_rating')
      ->setLabel('Content rating')
      // ->setDefaultValue(50)
      ->setRequired(TRUE)
      ->setReadOnly(TRUE)
      ->setDisplayOptions('form', [
        'weight' => 0,
        'type' => 'kifiform_rating_statistics',
      ])
      ->setDisplayOptions('view', [
        'type' => 'kifiform_rating_stars',
        'label' => 'hidden',
        'weight' => 0,
        'settings' => [
          'enable_voting' => TRUE,
          'display_votes' => TRUE,
        ]
      ]);

    return $fields;
  }
}
