<?php

namespace Drupal\asklib\Breadcrumb;


use Drupal\Core\Access\AccessManagerInterface;
use Drupal\Core\Breadcrumb\Breadcrumb;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Controller\TitleResolverInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Path\CurrentPathStack;
use Drupal\Core\PathProcessor\InboundPathProcessorInterface;
use Drupal\Core\Routing\RequestContext;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\system\PathBasedBreadcrumbBuilder;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Matcher\RequestMatcherInterface;

class TaxonomyCrumb extends PathBasedBreadcrumbBuilder {
  protected $requestStack;

  protected $allowedRoutes = [
    'entity.taxonomy_term.canonical',
    // 'view.asklib_keywords.page_1',
    // 'view.asklib_keywords.page_2',
    'asklib.keywords',
    'asklib.keywords.letter.en',
    'asklib.keywords.letter.fi',
    'asklib.keywords.letter.sv',
  ];

  protected $allowedVocabularies = ['asklib_tags', 'finto'];

  public static function fixLinkTitles(RouteMatchInterface $route_match, Breadcrumb $crumb) {
    $links = $crumb->getLinks();

    if (isset($links[2])) {
      /*
       * NOTE: Work around a bug in Drupal 8.2:
       * Strip away '– {{ arguments.name }}' from the link title.
       *
       * This link points to the root of the index anyway, so it shouldn't display the argument anyway.
       */

      // $links[2]->setText(strstr($links[2]->getText(), ' ', TRUE));
    }

    if (isset($links[3])) {
      // By default This link has the same title as the previous one. Rewrite it to make more sense.

      $parts = explode('/', $route_match->getParameter('taxonomy_term')->url());
      $letter = strtoupper($parts[count($parts) - 2]);
      $links[3]->setText(t('Letter @letter', ['@letter' => $letter]));
    }

    return $crumb;
  }

  public function __construct(RequestContext $context, AccessManagerInterface $access_manager, RequestMatcherInterface $router, InboundPathProcessorInterface $path_processor, ConfigFactoryInterface $config_factory, TitleResolverInterface $title_resolver, AccountInterface $current_user, CurrentPathStack $current_path, RequestStack $request_stack) {
     parent::__construct($context, $access_manager, $router, $path_processor, $config_factory, $title_resolver, $current_user, $current_path);

     $this->requestStack = $request_stack;
   }

  public function applies(RouteMatchInterface $route_match) {
    if (in_array($route_match->getRouteName(), $this->allowedRoutes)) {
      if ($route_match->getRouteName() == 'entity.taxonomy_term.canonical') {
        $vid = $route_match->getParameter('taxonomy_term')->getVocabularyId();
        return in_array($vid, $this->allowedVocabularies);
      }
      return TRUE;
    }
    return FALSE;
  }

  public function build(RouteMatchInterface $route_match) {
    $crumb = parent::build($route_match);
    return self::fixLinkTitles($route_match, $crumb);
  }
}
