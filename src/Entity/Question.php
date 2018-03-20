<?php

namespace Drupal\asklib\Entity;

use Drupal;
use LogicException;
use RuntimeException;
use Drupal\Component\Datetime\DateTimePlus as DateTime;
use Drupal\Component\Utility\Unicode;
use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityChangedTrait;
use Drupal\Core\Entity\EntityPublishedTrait;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\asklib\AnswerInterface;
use Drupal\asklib\QuestionInterface;

/**
 * @ContentEntityType(
 *   id = "asklib_question",
 *   label = @Translation("Question"),
 *   handlers = {
 *     "access" = "Drupal\asklib\QuestionAccessControlHandler",
 *     "storage" = "Drupal\asklib\QuestionStorage",
 *     "storage_schema" = "Drupal\asklib\QuestionStorageSchema",
 *     "list_builder" = "Drupal\Core\Entity\EntityListBuilder",
 *     "view_builder" = "Drupal\asklib\QuestionViewBuilder",
 *     "views_data" = "Drupal\asklib\QuestionViewsData",
 *     "form" = {
 *       "edit" = "Drupal\asklib\Form\QuestionAdminForm",
 *       "default" = "Drupal\asklib\Form\QuestionForm",
 *       "delete" = "Drupal\asklib\Form\QuestionDeleteForm",
 *       "email" = "Drupal\asklib\Form\QuestionEmailPreviewForm",
 *       "redirect" = "Drupal\asklib\Form\QuestionRedirectForm",
 *       "remote" = "Drupal\asklib\Form\RemoteQuestionForm"
 *     },
 *   },
 *   base_table = "asklib_questions",
 *   revision_table = "asklib_questions_revision",
 *   translatable = FALSE,
 *   entity_keys = {
 *     "id" = "id",
 *     "uid" = "owner",
 *     "uuid" = "uuid",
 *     "label" = "title",
 *     "langcode" = "langcode",
 *     "revision" = "vid",
 *     "status" = "published",
 *     "published" = "published",
 *   },
 *   links = {
 *     "add-form" = "/kysy-kirjastonhoitajalta",
 *     "canonical" = "/kysy-kirjastonhoitajalta/kysymykset/{asklib_question}",
 *     "collection" = "/tietopalvelu/{asklib_question}",
 *     "delete-form" = "/admin/content/asklib/{asklib_question}/delete",
 *     "edit-form" = "/admin/content/asklib/{asklib_question}/edit",
 *     "email-form" = "/admin/content/asklib/{asklib_question}/email",
 *     "email-preview" = "/admin/content/asklib/{asklib_question}/email_preview",
 *     "redirect-form" = "/admin/content/asklib/{asklib_question}/redirect",
 *   }
 * )
 */
class Question extends ContentEntityBase implements QuestionInterface {
  use EntityChangedTrait;
  use EntityPublishedTrait;

  /**
   * Enable this flag to trigger email-sending actions after persisting the question.
   */
  protected $notificationFlags = self::NO_NOTIFICATIONS;

  static function titleFromBody($body) {
    $title = trim($body);
    $title = preg_replace('/^(hei|moi|hi|hello|hey|hej)[,.!?\s]*\b/i', '', $title);
    $title = preg_replace('/[\\n\\r]+/', ' ', $title);
    $title = mb_strtoupper(mb_substr($title, 0, 1)) . mb_substr($title, 1);

    if (empty($title)) {
      $title = $body;
    }

    $title = mb_strtoupper(mb_substr($title, 0, 1)) . mb_substr($title, 1);

    return Unicode::truncate($title, 160, true, true);
  }

  public function getNotificationFlags() {
    return $this->notifySubscrnotificationFlagsibers;
  }

  public function hasNotificationFlag($flag) {
    return $this->notificationFlags & $flag;
  }

  public function setNotificationFlags($flags) {
    $this->notificationFlags = (int)$flags;
  }

  /**
   * Helper function for marking question reserved.
   */
  public function reserve($user_or_id) {
    $uid = is_object($user_or_id) ? $user_or_id->id() : $user_or_id;

    $lock = Drupal::entityManager()->getStorage('asklib_lock')->create();
    $lock->setUser($uid);
    $lock->setQuestion($this);

    $this->setState(Question::STATE_RESERVED);
    $this->setLock($lock);
    return $this;
  }

  public function release() {
    if ($lock = $this->getLock()) {
      $this->setLock(NULL);
      $this->setState($this->isAnswered() ? Question::STATE_ANSWERED : Question::STATE_OPEN);
    }
    return $this;
  }

  public function setLock($lock) {
    if (!is_null($lock) && $this->get('reservation')->target_id) {
      throw new RuntimeException('Cannot set a lock when another one exists already');
    }
    $this->set('reservation', $lock);
  }

  public function getLock() {
    return $this->get('reservation')->entity;
  }

  public function isAvailableTo($uid) {
    $uid = is_object($uid) ? $uid->id() : $uid;
    return !$this->isReserved() || $this->isReservedTo($uid);
  }

  public function isReservedTo($user_or_id) {
    if ($lock = $this->getLock()) {
      $uid = is_object($user_or_id) ? $user_or_id->id() : $user_or_id;
      return $lock->userId() == $uid;
    }
    return false;
  }

  public function isAnsweredBy($user_or_id) {
    if (!$this->isAnswered()) {
      return false;
    }
    $uid = is_object($user_or_id) ? $user_or_id->id() : $user_or_id;
    return $this->getAnweredBy()->id() == $uid;
  }

  public function isPublished() {
    return $this->get('published')->value == TRUE;
  }

  public function isReserved() {
    return $this->get('reservation')->target_id != NULL;
  }

  public function isAnswered() {
    if ($this->getState() == Question::STATE_ANSWERED) {
      return TRUE;
    }

    // Question state can be different when it is reserved after initial answer.
    return $this->getAnsweredTime() != FALSE;
  }

  public function getState() {
    return $this->get('state')->value;
  }

  /**
   * Setting the state to open or answered will automaticly clear reserved time.
   */
  public function setState($state) {
    $this->get('state')->setValue($state);
  }

  public function getAnswer() {
    return $this->get('answer')->entity;
  }

  public function setAnswer($answer) {
    $this->set('answer', $answer);

    // NOTE: Using getter to allow Drupal to convert $answer from ID to entity.
    if ($this->getAnswer() instanceof AnswerInterface) {
      $this->getAnswer()->setQuestion($this);
    }
  }

  public function getAnsweredBy() {
    return $this->getAnswer() ? $this->getAnswer()->getUser() : null;
  }

  public function getReservedTo() {
    if ($lock = $this->getLock()) {
      return $lock->getUser();
    }
  }

  public function getParent() {
    return $this->get('parent')->entity;
  }

  public function setParent($qid) {
    $this->set('parent', is_object($qid) ? $qid->id() : $qid);
  }

  public function getDisplays() {
    return $this->get('displays')->value;
  }

  public function setDisplays($count) {
    $this->set('displays', $count);
  }

  public function getTitle() {
    return $this->get('title')->value;
  }

  public function setTitle($title) {
    $this->set('title', $title);
  }

  public function getBody() {
    return $this->get('body')->value;
  }

  public function setBody($body) {
    $this->set('body', $body);
  }

  public function getBodyFormat() {
    return $this->get('body')->format;
  }

  public function setBodyFormat($format) {
    $this->get('body')->format = $format;
  }

  public function getDetails() {
    return $this->get('details')->value;
  }

  public function setDetails($details) {
    $this->set('details', $details);
  }

  public function getEmail() {
    return $this->get('email')->value;
  }

  public function setEmail($email) {
    $this->set('email', $email);
  }

  public function getName() {
    return $this->get('name')->value;
  }

  public function setName($name) {
    $this->set('name', $name);
  }

  public function getUser() {
    return $this->get('user')->value;
  }

  public function setUser($user) {
    $this->set('user', $user);
  }

  public function getTargetLibrary() {
    return $this->get('target_library')->entity;
  }

  public function setTargetLibrary($target) {
    $this->set('target_library', $target);
  }

  public function getMunicipality() {
    return $this->get('municipality')->entity;
  }

  public function setMunicipality($municipality) {
    $this->set('municipality', $municipality);
  }

  public function getCreatedTime() {
    return $this->get('created')->value;
  }

  public function getReservedTime() {
    if ($lock = $this->getLock()) {
      return $lock->getCreatedTime();
    }
  }

  public function getAnsweredTime() {
    if ($answer = $this->getAnswer()) {
      return $answer->getAnsweredTime();
    }
  }

  public function getEmailSentTime() {
    if ($answer = $this->getAnswer()) {
      return $answer->getEmailSentTime();
    }
  }

  public function setAnsweredTime($time) {
    if (!$this->getAnswer()) {
      throw new LogicException('Cannot set answer time without Answer object');
    }
    if ($time instanceof DateTime) {
      $time = $time->format(DATETIME_DATETIME_STORAGE_FORMAT);
    }
    $this->getAnswer()->setAnsweredTime($time);
  }

  public function isEmailSent() {
    if ($this->getAnswer()) {
      return $this->getAnswer()->getEmailSentTime() != NULL;
    }
    return FALSE;
  }

  public function getAdminNotes() {
    return $this->get('admin_notes')->value;
  }

  public function setAdminNotes($details) {
    $this->set('admin_notes', $details);
  }

  public function getTags() {
    return $this->get('tags')->referencedEntities();
  }

  public function getChannel() {
    return $this->get('channel')->entity;
  }

  public function setChannel($data) {
    $this->set('channel', $data);
  }

  public function getFeeds() {
    return $this->feeds->referencedEntities();
  }

  public function setFeeds(array $feeds) {
    $this->set('feeds', $feeds);
  }

  public function setAttachments(array $values) {
    $this->set('attachments', $values);
  }

  public function getAttachments() {
    return $this->get('attachments')->referencedEntities();
  }

  public function preSave(EntityStorageInterface $storage) {
    parent::preSave($storage);

    if (!$this->getTitle()) {
      $this->setTitle(static::titleFromBody($this->getBody()));
    }
  }

  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    /*
     * NOTE: When using setSettings(), even field defaults have to be
     * defined mnaually!
     */

    $fields = parent::baseFieldDefinitions($entity_type);
    $fields += static::publishedBaseFieldDefinitions($entity_type);

    $fields['published']
      ->setLabel(t('Archive'))
      ->setRequired(TRUE)
      ->setDescription(NULL)
      ->setSetting('on_label', t('Public'))
      ->setSetting('off_label', t('Private'))
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayOptions('form', [
        'type' => 'options_buttons',
        'weight' => 98,
      ]);

    $fields['parent'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Referenced question'))
      ->setDescription(t('Earlier entry that is relevant to this question.'))
      ->setSetting('target_type', 'asklib_question')
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE)
      ->setCardinality(1)
      ->setDisplayOptions('form', [
        'type' => 'hidden',
        'weight' => -10,
      ]);

    $fields['title'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Title'))
      ->setDescription(t('Descriptive title for the question.'))
      ->setSetting('max_length', 180)
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE)
      ->setRevisionable(TRUE)
      ->setDisplayOptions('form', [
        'weight' => -10,
      ])
      ->setDisplayOptions('view', [
        'weight' => -10,
        'type' => 'hidden',
      ]);

    $fields['body'] = BaseFieldDefinition::create('text_long')
      ->setLabel(t('Question'))
      ->setDescription(t('Question body.'))
      ->setSetting('max_length', 10000)
      ->setRequired(TRUE)
      ->setRevisionable(TRUE)
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayOptions('form', [
        'weight' => 0,
        'format' => 'basic_html_without_ckeditor',
        'handler_settings' => [
          'format' => 'basic_html_without_ckeditor'
        ]
      ])
      ->setDisplayOptions('view', [
        'weight' => 100,
        'label' => 'hidden',
      ]);

    $fields['details'] = BaseFieldDefinition::create('string_long')
      ->setLabel(t('Additional details'))
      ->setSetting('max_length', 10000)
      ->setDescription(t('These details will be hidden from the public question.'))
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', FALSE)
      ->setDisplayOptions('form', [
        'weight' => 10,
      ]);

    $fields['admin_notes'] = BaseFieldDefinition::create('string_long')
      ->setLabel(t('Messages to administrators'))
      ->setSetting('max_length', 10000)
      ->setDescription(t('Private message to administrators. Messages are displayed only here.'))
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', FALSE)
      ->setDisplayOptions('form', [
        'weight' => -30,
      ]);

    $fields['answer'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Answer'))
      ->setDescription(t('Answer attached to this question.'))
      ->setSettings(['target_type' => 'asklib_answer'])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayOptions('form', [
        'type' => 'asklib_answer',
        'weight' => 15,
      ])
      ->setDisplayOptions('view', [
        'label' => 'hidden',
        'type' => 'entity_reference_entity_view',
        'weight' => 10,
      ]);

    $fields['reservation'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Lock'))
      ->setDescription(t('Lock attached to this question.'))
      ->setSettings(['target_type' => 'asklib_lock'])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', FALSE)
      ->setDisplayOptions('view', [
        'label' => 'hidden',
        'type' => 'entity_reference_entity_view',
        'weight' => 10,
      ]);

    $fields['user'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Questioner'))
      ->setDescription(t('User who sent the question.'))
      ->setSettings(['target_type' => 'user'])
      ->setDefaultValueCallback('Drupal\asklib\Entity\Question::getCurrentUserId')
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', FALSE)
      ->setDisplayOptions('view', [
        'type' => 'hidden',
        'format' => 'hidden',
      ]);

    /*
     * For privacy reasons this field is not used. Older system contains this field so we
     * keep it for archival purposes.
     */
    $fields['name'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Name'))
      ->setRequired(TRUE)
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', FALSE)
      ->setDisplayOptions('form', [
        'type' => 'hidden',
        'weight' => 50,
      ])
      ->setDisplayOptions('view', [
        'type' => 'hidden',
        'format' => 'hidden',
      ]);

    $fields['email'] = BaseFieldDefinition::create('email')
      ->setLabel(t('Email'))
      ->setRequired(FALSE)
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', FALSE)
      ->setDisplayOptions('form', [
        'weight' => 51,
      ]);

    $fields['municipality'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Municipality'))
      ->setDescription(t('Municipality of the user.'))
      ->setSettings([
        'target_type' => 'taxonomy_term',
        'handler' => 'default',
        'handler_settings' => [
          'target_bundles' => ['asklib_municipalities' => 'asklib_municipalities'],
          'sort' => [
            'field' => 'name',
            'direction' => 'ASC',
          ],
        ],
      ])
      ->setCardinality(1)
      ->setRequired(TRUE)
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayOptions('form', [
        'weight' => 60,
        'type' => 'options_select',
      ])
      ->setDisplayOptions('view', [
        'type' => 'hidden',
        'label' => 'hidden',
        'weight' => -50,
      ]);

    $fields['target_library'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Target library'))
      ->setDescription(t('The library selected to answer the question.'))
      ->setSettings([
        'target_type' => 'taxonomy_term',
        'handler' => 'default',
        'handler_settings' => [
          'target_bundles' => [
            'asklib_libraries' => 'asklib_libraries',
            'asklib_municipalities' => 'asklib_municipalities',
          ],
          'sort' => [
            'field' => 'name',
            'direction' => 'ASC',
          ],
        ],
      ])
      ->setCardinality(1)
      ->setDisplayOptions('form', [
        'weight' => 70,
        'type' => 'options_select',
      ])
      // ->setDisplayOptions('view', [
      //   'weight' => 50,
      // ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['channel'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Channel'))
      ->setDescription(t('The channel this question was submitted to.'))
      ->setSettings([
        'target_type' => 'taxonomy_term',
        'handler' => 'default',
        'handler_settings' => [
          'target_bundles' => [
            'asklib_channels' => 'asklib_channels',
          ],
          'sort' => [
            'field' => 'name',
            'direction' => 'ASC',
          ],
        ],
      ])
      ->setCardinality(1)
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['feeds'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Feeds'))
      ->setDescription(t('Configured RSS feeds.'))
      ->setSettings([
        'target_type' => 'taxonomy_term',
        'handler' => 'default',
        'handler_settings' => [
          'target_bundles' => [
            'asklib_channels' => 'asklib_channels',
          ],
          'sort' => [
            'field' => 'name',
            'direction' => 'ASC',
          ],
        ],
      ])
      ->setRevisionable(false)
      ->setCardinality(FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED)
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayOptions('form', [
        'weight' => 80,
        'type' => 'options_buttons',
      ])
      // ->setDisplayOptions('form', [
      //   'type' => 'options_buttons',
      //   'weight' => 80,
      // ])
      ;

    $fields['state'] = BaseFieldDefinition::create('list_integer')
      ->setLabel(t('State'))
      ->setDescription(t('State of the question (waiting, reserved or answered)'))
      ->setDefaultValue(0)
      ->setReadOnly(TRUE)
      ->setSettings(['allowed_values' => [
        0 => t('Waiting'),
        1 => t('Reserved'),
        2 => t('Answered'),
      ]]);

    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel(t('Posted'))
      ->setDescription(t('The time that the node was created.'))
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayOptions('form', [
        'type' => 'datetime_timestamp',
        'weight' => 0,
      ])
      ->setDisplayOptions('view', [
        'type' => 'timestamp',
        'weight' => -50,
        'settings' => [
          'date_format' => 'date_only',
        ]
      ]);

    $fields['changed'] = BaseFieldDefinition::create('changed')
      ->setLabel(t('Changed'))
      ->setDescription(t('The time that the node was last edited.'))
      ->setDisplayConfigurable('view', FALSE);

    $fields['displays'] = BaseFieldDefinition::create('kifiform_view_counter')
      ->setLabel(t('Read count'))
      ->setDescription(t('Number of times this question has been read.'))
      ->setDefaultValue(0)
      ->setReadOnly(TRUE)
      ->setDisplayOptions('view', [
        'weight' => 10,
      ])
      ->setDisplayConfigurable('view', TRUE);

    $fields['tags'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Keywords'))
      ->setDescription(t('Tags attached to the question.'))
      ->setSettings([
        'target_type' => 'taxonomy_term',
        'handler' => 'default',
        'handler_settings' => [
          'finto_autocomplete_strict' => TRUE,
          'finto_autocomplete' => TRUE,
          'finto_vocabulary' => 'yso',
          'auto_create' => TRUE,
          'auto_create_bundle' => 'asklib_tags',
          'target_bundles' => [
            'asklib_tags' => 'asklib_tags',
            'finto' => 'finto',
          ],
          'sort' => [
            'field' => 'name',
            'direction' => 'ASC',
          ],
        ],
      ])
      ->setRevisionable(FALSE)
      ->setCardinality(-1)
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayOptions('form', [
        'weight' => 15,
        'type' => 'entity_reference_autocomplete_tags',
      ])
      ->setDisplayOptions('view', [
        'weight' => -100,
      ]);

    /*
     * NOTE: File widget info text can be altered by creating a new theme element and setting
     * $form['attachments']['widget']['#file_upload_description']['#theme'] on the form.
     */
    $fields['attachments'] = BaseFieldDefinition::create('file')
      ->setLabel(t('Attachments'))
      ->setDescription(t('Files attached to the question.'))
      ->setSettings([
        'file_directory' => 'asklib/questions',
        'file_extensions' => 'jpg jpeg png bmp gif tiff mp3 ogg oga wma wav flac m4a mp4 wmv mpeg mkv',
        'max_filesize' => '8 MB',
        'display_field' => TRUE,
      ])
      ->setCardinality(3)
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', FALSE)
      ->setDisplayOptions('form', [
        'type' => 'file_generic',
        'weight' => 10,
      ])
      ->setDisplayOptions('view', [
        'weight' => 100
      ]);

    $fields['captcha'] = BaseFieldDefinition::create('kifiform_captcha')
      ->setLabel(t('Captcha'))
      ->setComputed(TRUE)
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', FALSE)
      ->setDisplayOptions('form', [
        'type' => 'kifiform_captcha',
        'weight' => 100,
      ]);

      return $fields;
  }

  /**
   * Not great but this is how it's done in core.
   */
  public static function getCurrentUserId() {
    return [Drupal::currentUser()->id()];
  }
}
