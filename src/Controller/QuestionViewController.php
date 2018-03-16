<?php

namespace Drupal\asklib\Controller;

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

    $data = [
      'from' => \Drupal::currentUser(),
      'asklib_question' => $asklib_question,
      'files' => $asklib_question->getAnswer()->getAttachments(),
    ];

    $mail = \Drupal::service('plugin.manager.mail')->mail('asklib', 'answer', $recipient, $langcode, $data, null, false);

    return new Response($mail['body']);
  }
}
