<?php

namespace Drupal\asklib\Plugin\Action;

use Exception;
use Drupal\taxonomy\TermInterface;

/**
 * Sends notification email to group subscribers.
 *
 * @Action(
 *   id = "asklib_send_question_notify_email",
 *   label = @Translation("Notify answerers of a new question by email"),
 *   type = "asklib_question"
 * )
 */
class SendQuestionNotifyEmail extends EmailActionBase {
  /**
   * {@inheritdoc}
   */
  public function execute($question = NULL) {
    $recipients = [];

    /*
     * Currently channels and regular target libraries are INITIALLY mutually exclusive.
     *
     * Target library has priority over a question channel.
     *
     * This action can be triggered also when UPDATING the question (redirecting to another group).
     * In this case we can ignore the channel because we want to notify only the actual target group.
     */
    if ($library = $question->getTargetLibrary()) {
      $recipients = array_merge($recipients, $this->getGroupRecipients($library));
    } else if (($channel = $question->getChannel()) && !empty($this->getChannelRecipients($channel))) {
      $recipients = array_merge($recipients, $this->getChannelRecipients($channel));
    } else if ($city = $question->getMunicipality()) {
      $recipients = array_merge($recipients, $this->getGroupRecipients($city));
    }

    if ($recipients = array_unique($recipients)) {
      $mail = ['asklib_question' => $question];
      $langcode = $question->language()->getId();
      $this->mail('new_question_admin', $recipients, $langcode, $mail);
    }
  }

  protected function getChannelRecipients(TermInterface $channel) {
    $recipients = [];

    if ($channel->hasField('field_asklib_email_groups')) {
      foreach ($channel->get('field_asklib_email_groups')->referencedEntities() as $group) {
        $recipients = array_merge($recipients, $this->getGroupRecipients($group));
      }
    }

    return $recipients;
  }

  protected function getGroupRecipients(TermInterface $group) {
    $users = $this->getGroupSubscribers($group);
    $recipients = [];

    if ($group->hasField('field_asklib_email') && $group->field_asklib_email->value) {
      $recipients[] = sprintf('%s <%s>', $group->label(), $group->field_asklib_email->value);
    }

    foreach ($users as $user) {
      $email = $user->get('field_asklib_mail')->value ?: $user->getEmail();
      if ($user->hasField('field_real_name') && $name = $user->get('field_real_name')->value) {
        $recipients[] = sprintf('%s <%s>', $name, $email);
      } else {
        $recipients[] = $email;
      }
    }
    return $recipients;
  }

  protected function getGroupSubscribers(TermInterface $term) {
    if (!$term->hasField('field_asklib_subscribers')) {
      throw new Exception('Term has to have field \'field_asklib_subscribers\'');
    }
    $users = [];
    foreach ($term->get('field_asklib_subscribers')->referencedEntities() as $user) {
      $users[] = $user;
    }
    return $users;
  }
}
