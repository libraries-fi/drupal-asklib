<?php

namespace Drupal\asklib\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\HttpKernel\Event\FilterResponseEvent;

class AllowRemoteQuestionFrames implements EventSubscriberInterface {
  public static function getSubscribedEvents() {
    $events[KernelEvents::RESPONSE] = [['onResponse']];
    return $events;
  }

  public function onResponse(FilterResponseEvent $event) {
    $path = $event->getRequest()->getPathInfo();

    if (strpos($path, '/asklib/embed/') === 0) {
      $event->getResponse()->headers->remove('X-Frame-Options');
    }
  }
}
