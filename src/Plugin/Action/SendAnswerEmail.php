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

    $reply_to = $question->getAnsweredBy();

    $this->mail('answer', $question->getEmail(), $question->language()->getId(), $mail, $reply_to);

    $answer = $question->getAnswer();

    if (!$answer->getEmailSentTime()) {
      $answer->setEmailSentTime(new DateTime);
      $answer->save();
    }
  }
}
