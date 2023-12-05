<?php

namespace Drupal\asklib\Plugin\Action;

/**
 * Changes question status to answered.
 *
 * @Action(
 *   id = "asklib_send_client_receipt_email",
 *   label = @Translation("Send user a receipt by email"),
 *   type = "asklib_question"
 * )
 */
class SendClientReceiptEmail extends EmailActionBase {
  /**
   * {@inheritdoc}
   */
  public function execute($question = NULL) {
    if ($question->getEmail()) {
      $mail = ['asklib_question' => $question, 'files' => $question->getAttachments()];
      $this->mail('new_question', $question->getEmail(), $question->language()->getId(), $mail, $this->getGenericSenderAddress());
    }
  }
}
