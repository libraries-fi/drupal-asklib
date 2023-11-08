<?php
namespace Drupal\asklib\Plugin\EmailBuilder;

use Drupal\asklib\Entity\Answer;
use Drupal\asklib\Entity\Question;
use Drupal\symfony_mailer\EmailFactoryInterface;
use Drupal\symfony_mailer\EmailInterface;
use Drupal\symfony_mailer\Processor\EmailBuilderBase;
use Drupal\user\UserInterface;
use Drupal\symfony_mailer\Address;
use Drupal\Core\Render\Markup;
use Drupal\Core\Session\AccountInterface;
use Drupal\symfony_mailer\Processor\TokenProcessorTrait;

/**
 * Defines the Email Builder plug-in for user module.
 *
 * @EmailBuilder(
 *   id = "asklib",
 *   label = @Translation("Kirjastot.fi Ask Librarian"),
 *   sub_types = {
 *     "answer" = @Translation("Answer"),
 *     "new_question" = @Translation("New question"),
 *     "new_question_admin" = @Translation("New question (admin)")
 *   },
 *   override = {"asklib.answer", "asklib.new_question", "asklib.new_question_admin"}
 * )
 */
class AskLibEmailBuilder extends EmailBuilderBase {

  use TokenProcessorTrait;

  public function createParams(EmailInterface $email, $langcode = "fi", Question $question = NULL, 
  array|string $recipients = NULL, AccountInterface|array|string $reply_to = NULL, string $from = NULL,
  array $attachments = null) 
  {
    assert($question != NULL);
    $email->setParam('langcode', $langcode);

    $email->setParam('asklib_question', $question);
    $email->setVariable('asklib_question', $question);

    if($question->isAnswered())
    {
      $email->setParam('asklib_answer', $question->getAnswer());
      $email->setVariable('asklib_answer', $question->getAnswer());
    }

    $email->setParam('recipients', $recipients);
    $email->setParam('reply-to', $reply_to);
    $email->setParam('from', $from);
    $email->setParam('attachments', $attachments);
  }

  public function fromArray(EmailFactoryInterface $factory, array $message) {

    $recipients = $message['to'];
    $reply_to = $message['reply-to'];
    $from = NULL;
    if(!empty($message['params']['from']))
    {
      $from = $message['params']['from']->getEmail();
    }

    return $factory->newTypedEmail($message['module'], $message['key'],
      $message['langcode'], $message['params']['asklib_question'], $recipients, $reply_to, $from);
  }


  /**
   * {@inheritdoc}
   */
  public function preRender(EmailInterface $email) {
    $this->tokenOptions(['clear' => TRUE]);
  }

  public function build(EmailInterface $email) {

    $recipients = $email->getParam('recipients');
    $question = $email->getParam('asklib_question');

    if(is_string($recipients))
    {
      $email->setTo(new Address($recipients));
    } else if(is_array($recipients))
    {
      $addresses = [];
      foreach($recipients as $recipient)
      {
        $addresses[] = new Address($recipient['email'], $recipient['name'] ?? NULL);
      }
      $email->setTo($addresses);
    }

    $reply_to = $email->getParam('reply-to');
    if(!empty($reply_to))
    {
      if(is_string($reply_to))
      {
        $email->setReplyTo(new Address($reply_to));
      }
      else if(is_array($reply_to) && isset($reply_to['email']))
      {
        $email->setReplyTo(new Address($reply_to['email'], $reply_to['name'] ?? NULL));
      } else {
        // We are dealing with User object. Get the wanted fields from there.
        $sender_email = $reply_to->get('field_asklib_mail')->value ?: $reply_to->getEmail();
        $email->setReplyTo(new Address($sender_email, $reply_to->field_real_name->value));
      }
    }

    $from = $email->getParam('from');
    if(!empty($from))
    {
      $email->setFrom(new Address($from));
    }

    // If this is an answer, then set the answeree as reply-to mail.
    if($email->getSubType() == 'answer')
    {
      $answer = $email->getParam('asklib_answer');
      $anwerer = $answer->getUser();
      $email->setReplyTo(new Address($anwerer->get('field_asklib_mail')->value ?: $anwerer->getEmail(), $anwerer->field_real_name->value));
    }

  }

}