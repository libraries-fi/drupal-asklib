<?php

namespace Drupal\asklib\Breadcrumb;

use SplPriorityQueue;
use Drupal\Core\Breadcrumb\Breadcrumb;
use Drupal\Core\Breadcrumb\BreadcrumbBuilderInterface;
use Drupal\Core\Breadcrumb\ChainBreadcrumbBuilderInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Breadrcumb builders are injected using tag 'asklib_builder'
 *
 * We use only our custom crumbs and fallback to path-based builder, ignoring core builders
 * such as forum and taxonomy crumbs. We employ URL aliases everywhere, so the path-based
 * builder works the best.
 */
class BreadcrumbProxy implements ChainBreadcrumbBuilderInterface {
  use StringTranslationTrait;

  protected $urlAliases = [
    '/kysy',
    '/fraga',
    '/ask',
  ];

  protected $requestStack;
  protected $builders;

  public function __construct(RequestStack $request_stack) {
    $this->requestStack = $request_stack;
    $this->builders = new SplPriorityQueue;
  }

  public function addBuilder(BreadcrumbBuilderInterface $builder, $priority) {
    $this->builders->insert($builder, $priority);
  }

  public function applies(RouteMatchInterface $route_match) {
    $path = $this->requestStack->getCurrentRequest()->getPathInfo();

    foreach ($this->urlAliases as $alias) {
      if (substr($path, 0, strlen($alias)) == $alias) {
        return $this->getApplicableBuilder($route_match) != NULL;
      }
    }

    return FALSE;
  }

  public function build(RouteMatchInterface $route_match) {
    $builder = $this->getApplicableBuilder($route_match);
    $source = $builder->build($route_match);

    $source_links = $source->getLinks();
    $links = $source->getLinks();

    if (isset($links[1]) && $links[1]->getUrl()->getRouteName() == 'entity.asklib_question.add_form') {
      $links[1]->setText($this->t('Ask a Librarian'));
    } else {
      $links = array_splice($links, 1, 0, [Link::createFromRoute(t('Ask a Librarian'), 'entity.asklib_question.add_form')]);

    }

    $crumb = new Breadcrumb;
    $crumb->setLinks($links);
    $crumb->addCacheableDependency($source);
    $crumb->mergeCacheMaxAge(0);

    return $crumb;
  }

  protected function getApplicableBuilder(RouteMatchInterface $route_match) {
    foreach ($this->builders as $builder) {
      if ($builder->applies($route_match)) {
        return $builder;
      }
    }
  }
}
