<?php

namespace Drupal\asklib\Controller;

use Drupal\asklib\Entity\Question;
use Exception;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\Controller\EntityViewController;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Drupal\asklib\QuestionInterface;

class QuestionViewController extends EntityViewController {
  public function view(EntityInterface $asklib_question, $view_mode = 'full', $langcode = null) {
    $view = parent::view($asklib_question, $view_mode, $langcode);
    $view['#attached']['library'][] = 'asklib/view-counter';
    return $view;
  }

  public function title(EntityInterface $asklib_question) {
    return $asklib_question->label();
  }

  public function renderEmail(QuestionInterface $asklib_question) {
    $langcode = $asklib_question->language()->getId();
    $recipient = $asklib_question->getEmail();

    // Set the question "answered" for preview.
    $asklib_question->setState(Question::STATE_ANSWERED);

    $mail = [
      'from' => \Drupal::currentUser(),
      'asklib_question' => $asklib_question,
      'files' => $asklib_question->getAnswer()->getAttachments(),
      'do_not_send' => true, // This will cancel the mailing in 'asklib_mailer_post_render'.
    ];

    $reply_to = $asklib_question->getAnsweredBy();

    $mail = \Drupal::service('plugin.manager.mail')->mail('asklib', 'answer', $recipient, $langcode, $mail, $reply_to);

    return new Response($mail['body']);
  }
}
