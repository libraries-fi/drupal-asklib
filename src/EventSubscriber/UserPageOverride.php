<?php

namespace Drupal\asklib\EventSubscriber;

use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Session\AccountInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;

class UserPageOverride implements EventSubscriberInterface {
  public static function getSubscribedEvents() {
    return [
      KernelEvents::REQUEST => [['onRequest']],
    ];
  }

  public function __construct(RouteMatchInterface $route_match, AccountInterface $current_user) {
    $this->currentRoute = $route_match;
    $this->currentUser = $current_user;
  }

  public function onRequest(GetResponseEvent $event) {
    $allowed = $this->currentUser->hasPermission('answer questions');

    if ($allowed && $this->currentRoute->getRouteName() == 'user.page') {
      // $event->setResponse(new RedirectResponse('/admin/asklib'));
    }
  }
}
