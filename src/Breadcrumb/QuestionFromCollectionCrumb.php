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

class QuestionFromCollectionCrumb extends PathBasedBreadcrumbBuilder {
  protected $requestStack;
  protected $nodeStorage;

  public static function collectionIdFromQuery($from) {
    // Variable value should be 'collection/{nid}'
    [$foo, $nid] = explode('/', $from . '//');
    if ($foo == 'collection' && ctype_digit($nid)) {
      return $nid;
    }
  }

   public function __construct(RequestContext $context, AccessManagerInterface $access_manager, RequestMatcherInterface $router, InboundPathProcessorInterface $path_processor, ConfigFactoryInterface $config_factory, TitleResolverInterface $title_resolver, AccountInterface $current_user, CurrentPathStack $current_path, RequestStack $request_stack, EntityTypeManagerInterface $entity_manager) {
     parent::__construct($context, $access_manager, $router, $path_processor, $config_factory, $title_resolver, $current_user, $current_path);

     $this->requestStack = $request_stack;
     $this->nodeStorage = $entity_manager->getStorage('node');
   }

  public function applies(RouteMatchInterface $route_match) {
    return ctype_digit(self::collectionIdFromQuery($this->from()));
  }

  public function build(RouteMatchInterface $route_match) {
    $nid = self::collectionIdFromQuery($this->from());
    $nodes = $this->nodeStorage->loadByProperties([
      'type' => 'asklib_collection',
      'nid' => $nid
    ]);

    $node = reset($nodes);

    $request = $this->getRequestForPath($node->toUrl()->toString(), []);
    $this->context->fromRequest($request);

    $crumb = parent::build($route_match);
    $crumb->addLink($node->toLink());

    return $crumb;
  }

  protected function from() {
    return $this->requestStack->getCurrentRequest()->query->get('from');
  }
}
