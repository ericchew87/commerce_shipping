<?php

namespace Drupal\commerce_shipping\Controller;

use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_shipping\Entity\Shipment;
use Drupal\commerce_shipping\Entity\ShipmentInterface;
use Drupal\commerce_shipping\Form\ShipmentBuilderForm;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\Core\DependencyInjection\ClassResolverInterface;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\TempStore\SharedTempStoreFactory;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Defines a controller to move a shipment item.
 *
 * @internal
 */
class MoveShipmentItemController implements ContainerInjectionInterface {

  /**
   * The shared tempstore factory.
   *
   * @var \Drupal\Core\TempStore\SharedTempStoreFactory
   */
  protected $tempStoreFactory;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The class resolver.
   *
   * @var \Drupal\Core\DependencyInjection\ClassResolverInterface
   */
  protected $classResolver;

  /**
   * MoveShipmentItemController constructor.
   *
   * @param \Drupal\Core\TempStore\SharedTempStoreFactory $temp_store_factory
   *   The shared tempstore factory.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager service.
   */
  public function __construct(SharedTempStoreFactory $temp_store_factory, EntityTypeManagerInterface $entity_type_manager, ClassResolverInterface $class_resolver) {
    $this->tempStoreFactory = $temp_store_factory;
    $this->entityTypeManager = $entity_type_manager;
    $this->classResolver = $class_resolver;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('user.shared_tempstore'),
      $container->get('entity_type.manager'),
      $container->get('class_resolver')
    );
  }

  /**
   * Updates packages and saves to TempStore when a ShipmentItem is moved on ShipmentBuilderForm.
   * This method is called via AJAX when a ShipmentItem is moved.
   *
   * @param \Drupal\commerce_order\Entity\OrderInterface $order
   * The order.
   * @param $shipment_id
   * The id of the shipment entity.
   * @param $shipment_item_id
   * The ID of the ShipmentItem, designated by order_item_id-quantity (e.g. 8-2)
   * @param $package_from
   * The ID of the package the shipment item was moved from, or 'shipment-item-area' if not a package.
   * @param $package_to
   * The ID of the package the shipment item was moved to, or 'shipment-item-area' if not a package.
   */
  public function moveItem(OrderInterface $order, $shipment_id, $shipment_item_id, $package_from, $package_to) {
    /** @var \Drupal\commerce_shipping\Entity\ShipmentInterface $shipment */
    if ($shipment = $this->entityTypeManager->getStorage('commerce_shipment')->load($shipment_id)) {
      $collection = 'commerce_shipping.order.'.$order->id().'.shipment.'.$shipment->id();
      $temp_store = $this->tempStoreFactory->get($collection);
      /** @var \Drupal\commerce_shipping\Entity\PackageInterface[] $packages */
      $packages = $temp_store->get('packages');
      $shipment_item = $this->getShipmentItem($shipment, $shipment_item_id);

      // $package_to and $package_from will be set to 'shipment-item-area' if being moved from or to
      // the Un-Packaged Items area.
      if ($package_to !== 'shipment-item-area') {
        $packages[(int)$package_to]->addItem($shipment_item);
      }

      if ($package_from !== 'shipment-item-area') {
        $packages[(int)$package_from]->removeItem($shipment_item);
      }

      $temp_store->set('packages', $packages);

      /** @var \Drupal\commerce_shipping\Form\ShipmentBuilderForm $shipment_builder_instance */
      $shipment_builder_instance = $this->classResolver->getInstanceFromDefinition(ShipmentBuilderForm::class);
      $response = new AjaxResponse();
      $response->addCommand(new ReplaceCommand('#test', $shipment_builder_instance->buildShipmentBuilder($shipment)));
      return $response;
    } else {
      throw new NotFoundHttpException();
    }
  }

  /**
   * Find the shipment item that was moved based on the order_item_id and quantity.
   *
   * @param \Drupal\commerce_shipping\Entity\ShipmentInterface $shipment
   * The shipment entity
   * @param $shipment_item_id
   * The shipment item, designated by order_item_id-quantity (e.g. 8-3)
   *
   * @return \Drupal\commerce_shipping\ShipmentItem|null
   */
  protected function getShipmentItem(ShipmentInterface $shipment, $shipment_item_id) {
    list($order_item_id, $quantity) = explode('-', $shipment_item_id);

    foreach ($shipment->getItems() as $item) {
      if ($item->getOrderItemId() == $order_item_id && (int)$item->getQuantity() == $quantity) {
        return $item;
        break;
      }
    }

    return NULL;
  }

}
