<?php

namespace Drupal\asklib\Form;

use Drupal\Core\Action\ActionManager;
use Drupal\Core\DateTime\DateFormatterInterface;
use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Entity\EntityManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Path\AliasStorageInterface;
use Drupal\Core\Render\Element;
use Drupal\Core\Url;
use Drupal\asklib\ProvideQuestionFormHeader;
use Drupal\asklib\QuestionInterface;
use Drupal\asklib\UserMailGroupHelper;
use Drupal\asklib\Event\QuestionEvent;
use Drupal\autoslug\Slugger;
use Drupal\autoslug\SluggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

use Drupal\Core\Config\Config;

class QuestionAdminForm extends ContentEntityForm {
  use ProvideEntityFormActionGetter;

  protected $dates;
  protected $aliases;
  protected $aliasGenerator;
  protected $config;
  protected $mailGroups;

  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity.manager'),
      $container->get('date.formatter'),
      $container->get('path.alias_storage'),
      $container->get('autoslug.slugger.default'),
      $container->get('config.factory')->get('asklib.settings'),
      $container->get('asklib.user_mail_group_helper')
    );
  }

  public function __construct(EntityManagerInterface $em, DateFormatterInterface $dates, AliasStorageInterface $aliases, SluggerInterface $alias_generator, Config $config, UserMailGroupHelper $mail_groups) {
    parent::__construct($em);
    $this->dates = $dates;
    $this->aliases = $aliases;
    $this->aliasGenerator = $alias_generator;
    $this->config = $config;
    $this->mailGroups = $mail_groups;
  }

  public function form(array $form, FormStateInterface $form_state) {
    $question = $this->entity;
    $answer = $question->getAnswer();

    if (!$answer && $question->isReservedTo($this->currentUser())) {
      $answer = $this->entityManager->getStorage('asklib_answer')->create();
      $question->setAnswer($answer);
    }

    if (!$question->isAnswered() && $question->isReservedTo($this->currentUser())) {
      $answer->setUser($this->currentUser()->id());
    }

    $form['#attached']['library'][] = 'node/form';
    $form['#attached']['library'][] = 'asklib/question-edit-form';

    $form['advanced'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['entity-meta']],
      '#weight' => 99,
    ];

    $form = parent::form($form, $form_state);
    $form['title']['widget'][0]['value']['#size'] = 200;

    /*
     * Override details field description to be more informative.
     */
    $form['details']['widget'][0]['value']['#description'] = $this->t('Private details provided by the questioner.');

    /*
     * Drop body description.
     */
    $form['body']['widget'][0]['#description'] = '';

    /*
     * Simplify user-provided attachments list and prevent admins from accidentally uploading
     * files through it. They have their own file field.
     */
    foreach (Element::children($form['attachments']['widget']) as $delta) {
      $form['attachments']['widget'][$delta]['#display_field'] = FALSE;
    }

    $form['attachments']['widget']['#display_field'] = FALSE;
    unset($form['attachments']['widget'][$form['attachments']['widget']['#file_upload_delta']]);

    if ($form['attachments']['widget']['#file_upload_delta'] == 0) {
      $form['attachments']['widget']['#description'] = $this->t('Questioner sent no attachments.');
    }

    /*
     * Ensures that Drupal detects the primary Submit button is triggered when user
     * submits the form by pressing Enter.
     *
     * Otherwise the 'Release' button would be triggered by the browser.
     */
    $form['hidden_submit'] = [
      '#type' => 'html_tag',
      '#tag' => 'button',
      '#weight' => -1000,
      '#value' => $this->t('Save'),
      '#attributes' => [
        'type' => 'submit',
        'name' => 'op',
        'value' => $this->t('Save'),
        'style' => 'display: none',
      ],
    ];

    $form['question_group'] = [
      '#type' => 'container',
    ];

    $form['body']['#group'] = 'question_group';
    $form['body']['#attached']['library'][] = 'asklib/hide-text-format-picker';

    $form['title']['#group'] = 'question_group';
    $form['langcode']['#group'] = 'question_group';
    $form['details']['#group'] = 'question_group';
    $form['attachments']['#group'] = 'question_group';

    $form['sidebar_header'] = [
      '#type' => 'container',
      '#group' => 'advanced',
      '#open' => TRUE,
      '#weight' => -100,
      '#optional' => TRUE,
      '#attributes' => [
        'class' => ['entity-meta__header'],
      ],
      'library' => [
        '#type' => 'item',
        '#title' => $this->t('Target library'),
        '#plain_text' => $question->getTargetLibrary()
          ? $question->getTargetLibrary()->label()
          : $this->t('No library set'),
      ],
      'redirect' => [
        '#type' => 'link',
        '#weight' => 100,
        '#title' => $this->t('Redirect to another group'),
        '#url' => $question->urlInfo('redirect-form'),
        '#access' => $question->isAvailableTo($this->currentUser()) && !$question->isAnswered(),
      ]
    ];

    $form['library_group'] = [
      '#type' => 'container',
      '#group' => 'sidebar_header',
    ];

    $form['locks_group'] = [
      '#type' => 'details',
      '#group' => 'advanced',
      '#title' => $this->t('Reservation history'),
      '#open' => !$question->isReserved(),
      'locks' => $this->buildQuestionLockHistory(),
    ];

    $form['answer_meta_group'] = [
      '#type' => 'details',
      '#group' => 'advanced',
      '#title' => $this->t('Answering information'),
      '#open' => TRUE,
      '#access' => isset($answer),
    ];

    $form['questioner_group'] = [
      '#type' => 'details',
      '#group' => 'advanced',
      '#title' => $this->t('Questioner details'),
      '#open' => TRUE,
    ];

    $form['tags_group'] = [
      '#type' => 'details',
      '#group' => 'advanced',
      '#title' => $this->t('Keywords'),
      '#open' => isset($answer),
      '#open' => TRUE,
    ];

    $form['feeds_group'] = [
      '#type' => 'details',
      '#group' => 'advanced',
      '#title' => $this->t('Channel'),
      '#open' => TRUE,
    ];

    $form['stats_group'] = [
      '#type' => 'details',
      '#group' => 'advanced',
      '#title' => $this->t('Statistics'),
      '#open' => TRUE,
    ];

    $form['path_group'] = [
      '#type' => 'details',
      '#group' => 'advanced',
      '#title' => $this->t('URL alias'),
      '#access' => $this->currentUser()->hasPermission('administer asklib'),
    ];

    $form['email']['#group'] = 'questioner_group';
    $form['municipality']['#group'] = 'questioner_group';

    $form['tags']['#group'] = 'tags_group';
    $form['tags']['#attached']['library'][] = 'finto_taxonomy/kifiform-tags-plugin';

    $form['tags']['widget']['target_id']['#description'] = $this->t('Select keywords from the drop-down list or press Enter to add a new one.');

    $form['tags']['tags_legend'] = [
      'finto_legend' => [
        '#type' => 'container',
        'bullet' => [
          '#type' => 'html_tag',
          '#tag' => 'span',
          '#value' => '&nbsp;',
          '#attributes' => [
            'class' => ['kifiform-tag', 'finto-taxonomy-tag'],
          ],
          '#suffix' => $this->t('Finto keyword')
        ]
      ],
      'freeform_legend' => [
        '#type' => 'container',
        'bullet' => [
          '#type' => 'html_tag',
          '#tag' => 'span',
          '#value' => '&nbsp;',
          '#attributes' => [
            'class' => ['kifiform-tag']
          ],
          '#suffix' => $this->t('Free keyword')
        ]
      ]
    ];

    $form['finto_link'] = [
      '#type' => 'container',
      '#group' => 'tags_group',
      'link' => [
        '#type' => 'link',
        '#url' => Url::fromUri(sprintf('https://finto.fi/yso/%s/index', $this->entity->language()->getId())),
        '#title' => $this->t('Open Finto'),
        '#attributes' => [
          'id' => 'link-open-finto',
          'class' => ['button'],
          'target' => '_new'
        ]
      ]
    ];

    $form['feeds']['#group'] = 'feeds_group';
    $form['feeds']['widget']['#description'] = $this->t('The question will be published in selected RSS feeds.');

    $form['path_widget'] = [
      '#type' => 'container',
      '#group' => 'path_group',
      'path' => [
        '#type' => 'textfield',
        '#title' => $this->t('Identifier'),
        '#default_value' => $form_state->getValue('path') ?: $this->slugForQuestion(),
        '#description' => $this->t('URL alias should contain around 3-6 keywords separated with a dash. Only use letters and numbers.'),
        '#attributes' => [
          'pattern' => '[\wöäå\-]+',
        ]
      ]
    ];

    if (isset($form['answer'])) {
      foreach (Element::children($form['answer']['widget']) as $delta) {
        $form['answer']['widget'][$delta]['library']['#weight'] = 100;

        if ($question->isAnswered()) {
          $form['answer_meta_group']['answering_library'] = $form['answer']['widget'][$delta]['library'];
        }

        unset($form['answer']['widget'][$delta]['library']);

        if (isset($form['answer']['widget'][$delta]['rating'])) {
          $form['rating'] = $form['answer']['widget'][$delta]['rating'];
          $form['rating']['#group'] = 'stats_group';
          $form['answer']['widget'][$delta]['rating']['#access'] = FALSE;
          $form['stats_group']['#open'] = $answer->get('rating')->votes > 0;
        }
      }

      $form['answer_meta_group']['answering_library']['#access'] = $this->currentUser()->hasPermission('administer asklib');

    }

    if (isset($answer)) {
      $form['answer_meta_group']['update_time'] = [
        '#type' => 'item',
        '#title' => $this->t('Last updated'),
        '#markup' => $question->getUpdatedTime()
          ? $this->dates->format($question->getUpdatedTime())
          : $this->t('Question is waiting to be answered.'),
      ];

      $form['answer_meta_group']['answer_time'] = [
        '#type' => 'item',
        '#title' => $this->t('Answered on'),
        '#markup' => $question->getAnsweredTime()
          ? $this->dates->format($question->getAnsweredTime())
          : $this->t('Question is waiting to be answered.'),
      ];

      $form['answer_meta_group']['email_time'] = [
        '#type' => 'item',
        '#title' => $this->t('Email sent on'),
        '#markup' => $answer->getEmailSentTime()
          ? $this->dates->format($answer->getEmailSentTime())
          : $this->t('Email has not been sent.'),
      ];

      if ($question->isAnswered()) {
        $label = $answer->getLibrary() ? $answer->getLibrary()->label : $this->t('No answerer library.');
        $form['sidebar_header']['library']['#plain_text'] = $label;
      }
    }

    if ($question->isReserved()) {
      $form['actions_description'] = [
        '#type' => 'html_tag',
        '#tag' => 'div',
        '#attributes' => [
          'class' => ['messages', 'messages--warning'],
        ],
        '#value' => $this->t('Make sure that the public question text does not contain any personal information.'),
        '#weight' => 1000,
      ];
    }

    if (!$question->isEmailSent()) {
      $form['skip_email'] = [
        '#type' => 'checkbox',
        '#title' => $this->t('Mark answered'),
        '#description' => $this->t('Check ONLY when no email should be sent. Question will be considered answered immediately.'),
        '#default_value' => $question->isAnswered(),
        '#weight' => 900,
      ];
    }

    /*
     * NOTE: KEEP THIS AT THE VERY END!
     */
    if ($question->isReservedTo($this->currentUser())) {
      if ($answer->isNew()) {
        /*
        * Override the default value only for the first time admin is editing the entity.
        * Entity default is 'false' so that programmatically created entities would not be
        * published by accident.
        */
        $form['published']['widget']['#default_value'] = 1;
      }
    } else {
      foreach (Element::children($form) as $name) {
        $form[$name]['#disabled'] = TRUE;
      }
    }

    // Add header after possibly disabling inputs.
    $form['header'] = $this->getQuestionFormHeader($question);

    return $form;
  }

  public function actions(array $form, FormStateInterface $form_state) {
    $actions = parent::actions($form, $form_state);
    $actions['delete']['#access'] = FALSE;

    $question = $this->entity;

    if (!$this->entity->isReserved()) {
      $actions['submit']['#access'] = FALSE;
      $actions['reserve'] = [
        '#type' => 'submit',
        '#value' => $this->t('Reserve question'),
        '#submit' => ['::reserve'],
        '#validate' => ['::validateReserve'],
        '#attributes' => ['class' => ['button--primary']],
        '#disabled' => FALSE,
        '#limit_validation_errors' => [],
      ];
    } else if ($question->access('release')) {
      $actions['release'] = [
        '#type' => 'submit',
        // '#value' => $this->t('Release question'),
        '#value' => $question->isAnswered() ? $this->t('Release question') : $this->t('Release to waiting queue'),
        '#submit' => ['::release'],
        '#validate' => ['::validateRelease'],
        '#limit_validation_errors' => [],

        // This button is placed in the reserved status notification and we want to hide it
        // from the bottom of the form.
        '#attributes' => [
          'style' => 'display: none'
        ]
      ];

      $actions['submit']['#submit'] = [
        '::submitForm',
        '::processMarkAnswered',
        '::save',
        '::processSlug',
      ];

      $actions['submit']['#dropbutton'] = 'save';
      $actions['save_and_preview'] = $actions['submit'];
      $actions['save_and_preview']['#value'] = $this->t('Save and preview');
      $actions['save_and_preview']['#submit'][] = '::redirectToPreview';
      $actions['save_and_preview']['#validate'] = ['::validateAnswerBeforePreview', '::validateForm'];

      if (!$question->isAnswered() && $question->getEmail()) {
        // Make this button default for those questions that have never been answered.
        $actions['save_and_preview']['#weight'] = -10;
      }
    }

    return $actions;
  }

  public function submitForm(array &$form, FormStateInterface $form_state) {
    parent::submitForm($form, $form_state);
    $answer_data = $form_state->getValue('answer')[0];

    /*
     * Need to process answer in a hacky way as Drupal does not yet support
     * nesting an entity inside another entity's form. (8.2.0)
     */
    if ($this->entity->getAnswer()) {
      $answer = $this->entity->getAnswer();

      foreach ($answer_data as $field => $data) {
        if ($answer->hasField($field)) {
          $answer->set($field, $data);
        }
      }
    } else {
      $answer_data['email_sent'] = NULL;

      $answer = $this->entityManager->getStorage('asklib_answer')->create($answer_data);
      $answer->setUser($this->currentUser()->id());
      $this->entity->setAnswer($answer);
    }

    /*
     * When adding new taxonomy by hand, we want to force their langcode to match that of
     * the question.
     */
    foreach ($this->entity->getTags() as $i => $term) {
      if ($term->isNew()) {
        $old_langcode = $term->language()->getId();
        $new_langcode = $this->entity->language()->getId();

        $new_term = $this->entityManager->getStorage('taxonomy_term')->create([
          'name' => $term->getName(),
          'vid' => $term->getVocabularyId(),
          'langcode' => $new_langcode,
        ]);

        $this->entity->get('tags')->removeItem($i);
        $this->entity->get('tags')->appendItem($new_term);
      }
    }
  }

  public function save(array $form, FormStateInterface $form_state) {
    $status = parent::save($form, $form_state);
    if ($answer = $this->entity->getAnswer()) {
      $answer->save();
    }

    drupal_set_message(t('Changes have been saved.'));
    return $status;
  }

  public function processMarkAnswered(array $form, FormStateInterface $form_state) {
    $question = $this->entity;
    $skip_email = $form_state->getValue('skip_email');

    if ($skip_email && !$question->isAnswered()) {
      $this->executeAction('asklib_mark_question_answered', $question);

      drupal_set_message(t('Question was marked answered.'));
      $form_state->setRedirect('view.asklib_index.page_1');
    } else if (!$skip_email && !$question->getEmailSentTime() && $question->isAnswered()) {
      $question->getAnswer()->setAnsweredTime(NULL);

      drupal_set_message(t('Question was marked unanswered.'));
    }
  }

  public function processSlug(array $form, FormStateInterface $form_state) {
    $langcode = $this->entity->language()->getId();
    $source = '/' . $this->entity->urlInfo()->getInternalPath();
    $match = $this->aliases->load([
      'source' => $source,
      'langcode' => $langcode,
    ]);

    $alias = $this->aliasGenerator->build($this->entity);

    if ($slug = $form_state->getValue('path')) {
      $alias = substr_replace($alias, $slug, strrpos($alias, '/') + 1);
    }

    $pid = empty($match) ? NULL : $match['pid'];
    $this->aliases->save($source, $alias, $langcode, $pid);
  }

  public function validateReserve(array $form, FormStateInterface $form_state) {
    if ($this->entity->isReserved()) {
      throw new \Exception('Question is already taken!');
    }
  }

  public function validateRelease(array $form, FormStateInterface $form_state) {
    if (!$this->entity->access('release')) {
      throw new \Exception('This question is not reserved to you.');
    }
  }

  public function validateReply(array $form, FormStateInterface $form_state) {
    if (!$this->entity->isReservedTo($this->currentUser())) {
      throw new \Exception('This question is not reserved to you.');
    }
  }

  public function validateAnswerBeforePreview(array &$form, FormStateInterface $form_state) {
    $answer = $form_state->getValue('answer')[0]['body'][0]['value'];

    if (strlen(trim($answer)) == 0) {
      $form_state->setError($form['answer']['widget'][0]['body']['widget'][0]['value'], $this->t('Cannot send email without an answer.'));
    }
  }

  public function validateForm(array &$form, FormStateInterface $form_state) {
    $answer_text = $form_state->getValue('answer')[0]['body'][0]['value'];
    $publish = $form_state->getValue('published')[0]['value'];
    $skip_email = $form_state->getValue('skip_email');

    if (!strlen(trim($answer_text)) && $publish && $skip_email) {
      $form_state->setError($form['answer']['widget'][0]['body']['widget'], $this->t('Cannot publish question without an answer text.'));
    }

    return parent::validateForm($form, $form_state);
  }

  public function redirectToPreview(array $form, FormStateInterface $form_state) {
    $form_state->setRedirectUrl($this->entity->urlInfo('email-form'));
  }

  public function reserve(array $form, FormStateInterface $form_state) {
    $this->entity->reserve($this->currentUser());
    $this->entity->save();
  }

  public function release(array $form, FormStateInterface $form_state) {
    $question = $this->entity;
    $answer = $question->getAnswer();

    if ($answer && $answer->isSafeToDelete()) {
      $question->setAnswer(NULL);
    }

    $question->release()->save();
    $form_state->setRedirect('view.asklib_index.page_1');
    drupal_set_message(t('Question released successfully.'));
  }

  protected function rowCountForQuestion($body, $fallback = 4) {
    $lines = substr_count($body, "\n");
    $lines_alt = ceil(mb_strlen($body) / 60);
    $value = max(substr_count($body, "\n") + 2, $lines_alt);
    return max($value, $fallback);
  }

  protected function slugForQuestion() {
    $slug = substr(strrchr($this->entity->url(), '/'), 1);

    if (!ctype_digit($slug)) {
      // Strip query variables potentially injected by other modules etc.
      list($slug, $_) = explode('?', $slug . '?');
      return $slug;
    }

    return Slugger::slugify($this->entity->label());
  }

  protected function getQuestionFormHeader(QuestionInterface $question) {
    $header = [
      '#type' => 'container',
      '#weight' => -100,
      '#attributes' => [
        'class' => ['reserve-question-header'],
      ],
      '#attached' => [
        'library' => ['asklib/question-form-header']
      ],
    ];

    if ($question->isReserved()) {
      $lock = $question->getLock();
      $reserved_window = 24 * 3600 * ($this->config->get('reserved_window') + 1);
      $expires = $question->getReservedTime() + $reserved_window;

      if (!$question->isReservedTo($this->currentUser())) {
        $header['reserved_status'] = [
          '#type' => 'html_tag',
          '#tag' => 'p',
          '#attributes' => [
            'class' => ['messages', 'messages--error'],
          ],
          '#value' =>  $this->t('This question is reserved to user @user until @date. You can only view this question.', [
            '@user' => $lock->getUser()->getUsername(),
            '@date' => $this->dates->format($expires, 'month_and_day'),
          ]),
        ];
      } else {
        $header['reserved_status'] = [
          '#type' => 'html_tag',
          '#tag' => 'p',
          '#attributes' => [
            'class' => ['messages', 'messages--status'],
          ],
          '#value' =>  $this->t('Question will be released on @date.', [
            '@date' => $this->dates->format($expires, 'month_and_day')
          ]),
        ];
      }

      if ($question->access('release')) {
        $header['release'] = [
          '#type' => 'button',
          '#value' => $question->isAnswered() ? $this->t('Release question') : $this->t('Release to waiting queue'),
          '#attributes' => [
            'formnovalidate' => true,
          ],
        ];
      }
    } else {
      $header['reserved_status'] = [
        '#type' => 'html_tag',
        '#tag' => 'p',
        '#attributes' => [
          'class' => ['messages', 'messages--warning']
        ],
        '#value' =>  $this->t('Reserve this question to be able to answer it.'),
      ];
    }
    return $header;
  }

  protected function buildQuestionLockHistory() {
    $storage = $this->entityManager->getStorage('asklib_lock');
    $lids = $storage->getQuery()
      ->condition('question', $this->entity->id())
      ->sort('created', 'DESC')
      ->execute();

    $locks = $storage->loadMultiple($lids);

    $table = [
      '#type' => 'table',
      // '#header' => [$this->t('User'), $this->t('Time')],
      '#rows' => [],
      '#empty' => $this->t('Nothing')
    ];

    foreach ($locks as $lock) {
      $table['#rows'][] = [
        [
          'data' => [
            '#type' => 'link',
            '#title' => $lock->getUser()->getUsername(),
            '#url' => $lock->getUser()->urlInfo(),
          ]
        ],
        [
          'data' => $this->dates->format($lock->getCreatedTime(), 'short')
        ],
      ];
    }

    return $table;
  }
}
