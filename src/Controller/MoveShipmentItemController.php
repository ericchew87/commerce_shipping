<?php

namespace Drupal\commerce_shipping\Controller;

use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_shipping\Entity\Shipment;
use Drupal\commerce_shipping\Entity\ShipmentInterface;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\TempStore\SharedTempStoreFactory;
use Symfony\Component\DependencyInjection\ContainerInterface;

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
   * MoveShipmentItemController constructor.
   *
   * @param \Drupal\Core\TempStore\SharedTempStoreFactory $temp_store_factory
   *   The shared tempstore factory.
   */
  public function __construct(SharedTempStoreFactory $temp_store_factory) {
    $this->tempStoreFactory = $temp_store_factory;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('user.shared_tempstore')
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
    if ($shipment_id == 'new') {
      $collection = 'commerce_shipping.order.'.$order->id().'.shipment.new';
    } else if ($shipment = Shipment::load($shipment_id)) {
      $collection = 'commerce_shipping.order.'.$order->id().'.shipment.'.$shipment->id();
    }

    if (!empty($collection)) {
      $temp_store = $this->tempStoreFactory->get($collection);
      /** @var \Drupal\commerce_shipping\Entity\ShipmentInterface $shipment */
      $shipment = $temp_store->get('shipment');
      $packages = $shipment->getPackages();
      $shipment_item = $this->getShipmentItem($shipment, $shipment_item_id);

      if ($package_to !== 'shipment-item-area') {
        $packages[(int)$package_to]->addItem($shipment_item);
        if (!$packages[(int)$package_to]->isNew()) {
          $packages[(int)$package_to]->save();
        }
      }
      if ($package_from !== 'shipment-item-area') {
        $packages[(int)$package_from]->removeItem($shipment_item);
        if (!$packages[(int)$package_from]->isNew()) {
          $packages[(int)$package_from]->save();
        }
      }
      $shipment->setPackages($packages);
      $temp_store->set('shipment', $shipment);
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
