<?php

namespace Drupal\asklib\Breadcrumb;

use Drupal\Core\Breadcrumb\Breadcrumb;
use Drupal\Core\Breadcrumb\BreadcrumbBuilderInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Symfony\Component\HttpFoundation\RequestStack;

class QuestionArchiveCrumb implements BreadcrumbBuilderInterface {
  static protected $routes = [
    'entity.asklib_question.add_form' => 1,
    'entity.asklib_question.canonical' => 3,
    'view.asklib_archive.page_1' => 2,
    'view.asklib_archive.page_2' => 3,
    'view.asklib_archive.page_3' => 3,
    'view.asklib_archive.page_4' => 3,
    'view.asklib_archive.page_5' => 3,
    'view.asklib_comments.page_1' => 3,
  ];

  static protected $sourcePages = [
    'popular' => 'view.asklib_archive.page_2',
    'best' => 'view.asklib_archive.page_3',
    'active' => 'view.asklib_archive.page_4',
    'comments' => 'view.asklib_comments.page_1',
  ];

  protected $requestStack;

  public function __construct(RequestStack $request_stack) {
    $this->requestStack = $request_stack;
  }

  public static function sourcePageKey($route_name) {
    return array_search($route_name, self::$sourcePages);
  }

  public function applies(RouteMatchInterface $route_match) {
    return isset(self::$routes[$route_match->getRouteName()]);
  }

  public function build(RouteMatchInterface $route_match) {
    $links = [];

    if ($from = $this->from()) {
      $links[] = Link::createFromRoute($from->label, $from->route);
    }

    switch ($this->level($route_match)) {
      case 3:
        $links[] = Link::createFromRoute(t('Answers'), 'view.asklib_archive.page_1');

      case 2:
        $links[] = Link::createFromRoute(t('Ask a Librarian'), 'entity.asklib_question.add_form');

      case 1:
        $links[] = Link::createFromRoute(t('Home'), '<front>');
    }

    $crumb = new Breadcrumb;
    $crumb->setLinks(array_reverse($links));
    $crumb->addCacheContexts(['url.path', 'url.query_args:from']);
    $crumb->mergeCacheMaxAge(0);

    return $crumb;
  }

  protected function level(RouteMatchInterface $route_match) {
    return self::$routes[$route_match->getRouteName()];
  }

  protected function request() {
    return $this->requestStack->getCurrentRequest();
  }

  protected function from() {
    $from = $this->request()->query->get('from');

    if (isset(self::$sourcePages[$from])) {
      $route = self::$sourcePages[$from];
      [, $view_id, $display_id] = explode('.', $route);

      $view = \Drupal::entityTypeManager()->getStorage('view')->load($view_id);
      $display = $view->getDisplay($display_id);

      return (object)[
        'route' => $route,
        'label' => $display['display_options']['title'] ?: $view->getDisplay('default')['display_options']['title'],
      ];
    }
  }
}
