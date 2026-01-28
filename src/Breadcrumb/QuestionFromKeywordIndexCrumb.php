<?php

namespace Drupal\asklib\Breadcrumb;

use Drupal\Core\Routing\RouteMatchInterface;

use Drupal\system\PathBasedBreadcrumbBuilder;
use Drupal\Core\Routing\RequestContext;
use Drupal\Core\Access\AccessManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\PathProcessor\InboundPathProcessorInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Controller\TitleResolverInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Path\CurrentPathStack;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Matcher\RequestMatcherInterface;
use Drupal\Core\Path\PathMatcherInterface;
use Drupal\Core\Cache\CacheableMetadata;

class QuestionFromKeywordIndexCrumb extends PathBasedBreadcrumbBuilder {
  protected $requestStack;
  protected $termStorage;

  public static function termIdFromQuery($from) {
    // Variable value should be 'term/{tid}'
    [$foo, $tid] = explode('/', $from . '//');
    if ($foo == 'term' && ctype_digit($tid)) {
      return $tid;
    }
  }

  public function __construct(RequestContext $context, AccessManagerInterface $access_manager, RequestMatcherInterface $router, InboundPathProcessorInterface $path_processor, ConfigFactoryInterface $config_factory, TitleResolverInterface $title_resolver, AccountInterface $current_user, CurrentPathStack $current_path, ?PathMatcherInterface $path_matcher = NULL, RequestStack $request_stack, EntityTypeManagerInterface $entity_manager) {
    parent::__construct($context, $access_manager, $router, $path_processor, $config_factory, $title_resolver, $current_user, $current_path, $path_matcher);
    $this->requestStack = $request_stack;
    $this->termStorage = $entity_manager->getStorage('taxonomy_term');
  }

  public function applies(RouteMatchInterface $route_match, ?CacheableMetadata $cacheable_metadata = NULL) {
    return ctype_digit(self::termIdFromQuery($this->from()));
  }

  public function build(RouteMatchInterface $route_match) {
    $tid = self::termIdFromQuery($this->from());
    $terms = $this->termStorage->loadByProperties([
      'tid' => $tid
    ]);

    if (!empty($terms)) {
      $term = reset($terms);

      if ($request = $this->getRequestForPath($term->toUrl()->toString(), [])) {
        $this->context->fromRequest($request);
      }
      $crumb = parent::build($route_match);
      $crumb->addLink($term->toLink());

      // TaxonomyCrumb::fixLinkTitles requires this parameter.
      $route_match->getParameters()->set('taxonomy_term', $term);
      return TaxonomyCrumb::fixLinkTitles($route_match, $crumb);
    } else {
      return parent::build($route_match);
    }
  }

  protected function from() {
    return $this->requestStack->getCurrentRequest()->query->get('from');
  }
}
