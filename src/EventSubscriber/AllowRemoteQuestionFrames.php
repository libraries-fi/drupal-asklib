<?php

namespace Drupal\asklib\EventSubscriber;

use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\KernelEvents;

class AllowRemoteQuestionFrames implements EventSubscriberInterface {
  public static function getSubscribedEvents() {
    $events = [];
    $events[KernelEvents::RESPONSE] = [['onResponse']];
    return $events;
  }

  public function onResponse(ResponseEvent $event) {
    $path = $event->getRequest()->getPathInfo();

    if (strpos($path, '/asklib/embed/') === 0) {
      $event->getResponse()->headers->remove('X-Frame-Options');
    }
  }
}
