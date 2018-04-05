<?php

namespace Drupal\commerce_shipping\Controller;

use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Link;
use Drupal\Core\Render\RendererInterface;

use Symfony\Component\DependencyInjection\ContainerInterface;

/**
* Returns responses for Shipment routes.
*/
class ShipmentController extends ControllerBase implements ContainerInjectionInterface {
  /**
  * The renderer service.
  *
  * @var \Drupal\Core\Render\RendererInterface
  */
  protected $renderer;

  /**
  * Constructs a ShipmentController object.
  *
  * @param \Drupal\Core\Render\RendererInterface $renderer
  *   The renderer service.
  */
  public function __construct(RendererInterface $renderer) {
    $this->renderer = $renderer;
  }

  /**
  * {@inheritdoc}
  */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('renderer')
    );
  }

  /**
  * Displays add content links for available content types.
  *
  * Redirects to shipment/add/[type] if only one shipment type is available.
  *
  * @return array|\Symfony\Component\HttpFoundation\RedirectResponse
  *   A render array for a list of the node types that can be added; however,
  *   if there is only one node type defined for the site, the function
  *   will return a RedirectResponse to the node add page for that one node
  *   type.
  */
  public function addPage(OrderInterface $commerce_order) {
    $build = [
      '#theme' => 'entity_add_list',
      '#cache' => [
        'tags' => $this->entityManager()->getDefinition('commerce_shipment_type')->getListCacheTags(),
      ],
    ];

  $content = [];

  // Only use shipment types the user has access to.
  foreach ($this->entityManager()->getStorage('commerce_shipment_type')->loadMultiple() as $type) {
    $access = $this->entityManager()->getAccessControlHandler('commerce_shipment')->createAccess($type->id(), NULL, [], TRUE);
    if ($access->isAllowed()) {
      $content[] = [
        'type' => $type->id(),
        'label' => $type->label(),
        'description' => '',
        'add_link' => Link::createFromRoute($type->label(), 'entity.commerce_shipment.add_form', ['commerce_order' => $commerce_order->id(), 'commerce_shipment_type' => $type->id()]),
      ];
    }
    $this->renderer->addCacheableDependency($build, $access);
  }

  // Bypass the node/add listing if only one content type is available.
  if (count($content) == 1) {
    $type = array_shift($content);
   return $this->redirect('entity.commerce_shipment.add_form', ['commerce_order' => $commerce_order->id(), 'commerce_shipment_type' => $type['type']]);
  }

  $build['#bundles'] = $content;

  return $build;
  }
}