<?php

namespace Drupal\asklib\Plugin\Action;

use DateTime;

/**
 * Changes question status to answered.
 *
 * @Action(
 *   id = "asklib_send_answer_email",
 *   label = @Translation("Send answer email"),
 *   type = "asklib_question"
 * )
 */
class SendAnswerEmail extends EmailActionBase {
  /**
   * {@inheritdoc}
   */
  public function execute($question = NULL) {
    $mail = [
      'asklib_question' => $question,
      'files' => $question->getAnswer()->getAttachments()
    ];

    $sender = $question->getAnsweredBy();
    $sender_email = $sender->get('field_asklib_mail')->value ?: $sender->getEmail();
    $reply_to = sprintf('%s <%s>', $sender->field_real_name->value, $sender_email);

    $this->mail('answer', $question->getEmail(), $question->language()->getId(), $mail, $reply_to);

    $answer = $question->getAnswer();

    if (!$answer->getEmailSentTime()) {
      $answer->setEmailSentTime(new DateTime);
      $answer->save();
    }
  }
}
