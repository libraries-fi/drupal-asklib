<?php

namespace Drupal\asklib\Routing;

use Drupal;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Session\AccountInterface;
use Symfony\Component\Routing\Route;

class KeywordIndexRoutes {
  private static array $aliases = [
    'fi' => '/kysy/asiasanat',
    'en' => '/ask/keywords',
    'sv' => '/fraga/amnesord'
  ];

  public function routes() {
    $routes = [];

    foreach (self::$aliases as $langcode => $alias) {
      $routes['asklib.keywords.letter.' . $langcode] = new Route($alias . '/{letter}', [
        '_controller' => 'Drupal\asklib\Controller\KeywordIndexController::letter',
        'langcode' => $langcode,
        '_title' => 'TEST',
        '_title_callback' => 'Drupal\asklib\Controller\KeywordIndexController::letterTitle',
      ],
      [
        '_custom_access' => 'Drupal\asklib\Routing\KeywordIndexRoutes::access'
      ]);
    }

    return $routes;
  }

  public static function access(AccountInterface $account) {
    // NOTE: Route match is not yet populated in this phase.

    $active_langcode = Drupal::languageManager()->getCurrentLanguage()->getId();
    $path = Drupal::request()->getPathInfo();

    foreach (self::$aliases as $langcode => $alias) {
      if ($active_langcode == $langcode && strpos($path, (string) $alias) === 0) {
        return AccessResult::allowed()->addCacheContexts(['languages:language_content']);
      }
    }

    return AccessResult::neutral();
  }
}
