<?php

namespace Drupal\asklib\Form;

use DateTime;
use Drupal\Component\Utility\Html;
use Drupal\Core\Url;
use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Entity\EntityManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element;
use Drupal\File\FileUsage\FileUsageInterface;
use Drupal\asklib\QuestionInterface;
use Drupal\asklib\UserMailGroupHelper;
use Drupal\asklib\Event\QuestionEvent;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

use Drupal\Core\Session\AccountInterface;

class QuestionEmailPreviewForm extends ContentEntityForm {
  use ProvideEntityFormActionGetter;

  protected $mailGroups;

  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity.manager'),
      $container->get('asklib.user_mail_group_helper')
    );
  }

  public function __construct(EntityManagerInterface $em, UserMailGroupHelper $mail_groups) {
    parent::__construct($em);
    $this->mailGroups = $mail_groups;
  }

  public function form(array $form, FormStateInterface $form_state) {
    $form = parent::form($form, $form_state);
    $form['#attached']['library'][] = 'asklib/email-preview-form';

    $question = $this->entity;
    $answer = $question->getAnswer();
    $sender = $this->entityManager->getStorage('user')->load($this->currentUser()->id());

    foreach (Element::children($form) as $name) {
      $form[$name]['#access'] = FALSE;

      // NOTE: Do not remove fields because it breaks validation later on.
      // unset($form[$name]);
    }

    unset($form['captcha']);

    $form['library'] = [
      '#type' => 'value',
      '#value' => $this->getStatisticsLibrary(),
    ];

    $form['sender'] = [
      '#type' => 'value',
      '#value' => $sender,
    ];

    $sender_email = $sender->get('field_asklib_mail')->value ?: $sender->getEmail();

    $form['email_info'] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['layout-column', 'layout-column--half']
      ]
    ];

    $form['attachment_info'] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['layout-column', 'layout-column--half'],
      ],
      'title' => [
        '#type' => 'html_tag',
        '#tag' => 'b',
        '#value' => $this->t('Attachments'),
      ],
      'files' => [
        '#type' => 'container',
      ]
    ];

    foreach ($answer->getAttachments() as $delta => $file) {
      $form['attachment_info']['files'][$delta] = [
        '#type' => 'plain_text',
        '#plain_text' => $file->getFilename(),
        '#suffix' => '<br/>',
      ];
    }

    if (!$answer->getAttachments()) {
      $form['attachment_info']['files'] = [
        '#type' => 'html_tag',
        '#tag' => 'div',
        '#value' => $this->t('No attachments'),
      ];
    }

    $form['email_info']['sender_address'] = [
      '#type' => 'item',
      '#title' => $this->t('Sender'),
      '#markup' => Html::escape(sprintf('%s <%s>', $sender->field_real_name->value, $sender_email)),
    ];

    if ($name = $question->getName()) {
      $form['email_info']['recipient_address'] = [
        '#type' => 'item',
        '#title' => $this->t('Recipient'),
        '#markup' => Html::escape(sprintf('%s <%s>', $question->getName(), $question->getEmail())),
      ];
    } else {
      $form['email_info']['recipient_address'] = [
        '#type' => 'item',
        '#title' => $this->t('Recipient'),
        '#markup' => Html::escape(sprintf('%s', $question->getEmail())) ?: $this->t('No recipient'),
      ];
    }

    $form['iframe'] = [
      '#type' => 'html_tag',
      '#tag' => 'iframe',
      '#attributes' => [
        'class' => ['email-preview-frame'],
        'src' => $question->urlInfo('email-preview')->toString(),
        'style' => 'width: 100%; min-height: 400px;',
      ],
    ];

    $form['error'] = ['#weight' => -100];

    if (!$this->entity->getEmail()) {
      $form['error'][0] = [
        '#type' => 'html_tag',
        '#tag' => 'p',
        '#attributes' => [
          'class' => ['messages', 'messages--error']
        ],
        '#value' => $this->t('Cannot send email without user email address.')
      ];
    }

    if (!$this->entity->getAnswer() || !$this->entity->getAnswer()->getBody()) {
      $form['error'][1] = [
        '#type' => 'html_tag',
        '#tag' => 'p',
        '#attributes' => [
          'class' => ['messages', 'messages--error']
        ],
        '#value' => $this->t('There is no answer text for the question.')
      ];
    }

    return $form;
  }

  protected function getStatisticsLibrary() {
    if ($library = $this->entity->getAnswer()->getLibrary()) {
      return $library;
    }

    return $this->mailGroups->getUserMainGroup($this->currentUser()->id());
  }

  public function actions(array $form, FormStateInterface $form_state) {
    $actions = parent::actions($form, $form_state);
    $actions['delete']['#access'] = false;
    $actions['submit']['#value'] = $this->t('Send');
    $actions['submit']['#submit'] = [
      '::submitForm',
      '::sendEmail',
      '::save',
    ];

    if (!$this->entity->getEmail() || !$this->entity->getAnswer() || !$this->entity->getAnswer()->getBody()) {
      $actions['submit']['#disabled'] = TRUE;
    }

    return $actions;
  }

  public function validateForm(array &$form, FormStateInterface $form_state) {
     if (!$this->entity->getEmail()) {
       form_set_error($this->t('Cannot send email without user email address.'));
     }

    if (!$this->entity->getAnswer() || !$this->entity->getAnswer()->getBody()) {
      form_set_error($this->t('There is no answer text for the question.'));
    }

    return parent::validateForm($form, $form_state);
  }

  public function submitForm(array & $form, FormStateInterface $form_state) {
    $this->entity->setState(QuestionInterface::STATE_ANSWERED);
    return parent::submitForm($form, $form_state);
  }

  public function sendEmail(array $form, FormStateInterface $form_state) {
    $this->executeAction('asklib_send_answer_email', $this->entity);
    $this->executeAction('asklib_mark_question_answered', $this->entity);

    $form_state->setRedirect('view.asklib_index.page_1');
    drupal_set_message(t('Email was sent successfully.'));
  }

  public function save(array $form, FormStateInterface $form_state) {
    $status = parent::save($form, $form_state);
    $this->entity->getAnswer()->save();

    return $status;
  }
}
