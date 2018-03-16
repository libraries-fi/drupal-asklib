<?php

namespace Drupal\asklib\EventSubscriber;

use Exception;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityManagerInterface;
use Drupal\Core\Mail\MailManagerInterface;
use Drupal\asklib\Event\QuestionEvent;
use Drupal\kifimail\EmailComposer;
use Drupal\taxonomy\TermInterface;
use Drupal\user\UserDataInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class SendEmail implements EventSubscriberInterface {
  protected $entity_manager;
  protected $config;
  protected $user_data;
  protected $composer;
  protected $mailer;

  protected $processors = [];

  public function addProcessor($template_id, MailParameterProcessorInterface $processor) {
    $this->processors[$template_id] = $processor;
  }

  public static function getSubscribedEvents() {
    return [
      'asklib.question' => [
        ['onNewQuestionNotifyGroupSubscribers'],
        ['onNewQuestionSendReceivedConfirmation'],
      ],
      'asklib.redirect_question' => [['onNewQuestionNotifyGroupSubscribers']],
      'asklib.answer' => [['onAnswerSendReply']],
    ];
  }

  public function __construct(EntityManagerInterface $entity_manager, ConfigFactoryInterface $config, UserDataInterface $user_data, MailManagerInterface $mailer, EmailComposer $composer) {
    $this->entity_manager = $entity_manager;
    $this->config = $config;
    $this->user_data = $user_data;
    $this->mailer = $mailer;
    $this->composer = $composer;
  }

  public function onNewQuestionSendReceivedConfirmation(QuestionEvent $event) {
    $question = $event->getQuestion();
    $mail = $this->composer->compose('asklib_new_question', ['question' => $question]);
    $reply_to = $this->getSenderEmail();
    $this->mailer->mail('asklib', 'new_question', $question->getEmail(), $question->language()->getId(), $mail, $reply_to);
  }

  public function onNewQuestionNotifyGroupSubscribers(QuestionEvent $event) {
    if ($event->getQuestion()->isAnswered()) {
      return;
    }
    
    $question = $event->getQuestion();
    $target = $question->getTargetLibrary();

    if ($target && ($people = $this->getGroupRecipients($target))) {
      $mail = $this->composer->compose('asklib_new_question_admin', ['question' => $question]);
      $reply_to = $this->getSenderEmail();
      $this->mailer->mail('asklib', 'new_question_admin', implode(', ', $people), $question->language()->getId(), $mail, $reply_to);
    }
  }

  public function onAnswerSendReply(QuestionEvent $event) {
    $question = $event->getQuestion();
    $mail = $this->composer->compose('asklib_answer', ['question' => $question]);

    if ($event->hasAttachments()) {
      $mail['files'] = $event->getAttachments();
    }

    $sender = $question->getAnsweredBy();
    $reply = sprintf('%s <%s>', $sender->field_real_name->value, $sender->getEmail());

    // $reply = $this->getSenderEmail();
    $ok = $this->mailer->mail('asklib', 'answer', $question->getEmail(), $question->language()->getId(), $mail, $reply);
  }

  private function getGroupSubscribers(TermInterface $term) {
    if (!$term->hasField('field_asklib_subscribers')) {
      throw new Exception('Term has to have field \'field_asklib_subscribers\'');
    }
    $users = [];
    foreach ($term->get('field_asklib_subscribers') as $item) {
      $enabled = $this->user_data->get('asklib', $item->entity->id(), 'email.notifications');

      if ($enabled) {
        $users[] = $item->entity;
      }
    }
    return $users;
  }

  private function getGroupRecipients(TermInterface $term) {
    $users = $this->getGroupSubscribers($term);
    $recipients = [];

    if ($term->hasField('field_asklib_email') && $term->field_asklib_email->value) {
      $recipients[] = sprintf('%s <%s>', $term->label(), $term->field_asklib_email->value);
    }

    foreach ($users as $user) {
      if ($user->hasField('field_real_name') && $name = $user->get('field_real_name')->value) {
        $recipients[] = sprintf('%s <%s>', $name, $user->getEmail());
      } else {
        $recipients[] = $user->getEmail();
      }
    }
    return $recipients;
  }

  private function getSenderEmail() {
    $config = $this->config->get('asklib.settings');
    $site_config = $this->config->get('system.site');
    $name = $config->get('reply.name');
    $email = $config->get('reply.address') ?: $site_config->get('mail');
    return $name ? sprintf('%s <%s>', $name, $email) : $email;
  }
}
