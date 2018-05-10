<?php

namespace Drupal\commerce_shipping\Plugin\Commerce\ShipmentPackager;

use Drupal\commerce_shipping\Entity\ShipmentInterface;
use Drupal\commerce_shipping\PackageTypeManagerInterface;
use Drupal\commerce_shipping\ShipmentItem;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Plugin\PluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

abstract class ShipmentPackagerBase extends PluginBase implements ContainerFactoryPluginInterface, ShipmentPackagerInterface {

  /**
   * @var  \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The package type manager.
   *
   * @var \Drupal\commerce_shipping\PackageTypeManagerInterface
   */
  protected $packageTypeManager;

  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityTypeManagerInterface $entity_type_manager, PackageTypeManagerInterface $package_type_manager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);

    $this->entityTypeManager = $entity_type_manager;
    $this->packageTypeManager = $package_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager'),
      $container->get('plugin.manager.commerce_package_type')
    );
  }

  protected function updatePackagedItems(ShipmentInterface $shipment, array $items) {
    $packaged_items = $shipment->getData('packaged_items');
    foreach ($items as $item) {
      $packaged_items[] = $item;
    }
    $shipment->setData('packaged_items', $packaged_items);
  }

  protected function updateItemQuantity(ShipmentItem $item, $new_quantity) {
    $new_quantity = $new_quantity == 0 ? 1 : $new_quantity;
    $quantity_change = (string)($new_quantity / $item->getQuantity());

    $new_item = new ShipmentItem([
      'order_item_id' => $item->getOrderItemId(),
      'title' => $item->getTitle(),
      'quantity' => $new_quantity,
      'weight' => $item->getWeight()->multiply($quantity_change),
      'declared_value' => $item->getDeclaredValue()->multiply($quantity_change),
    ]);

    return $new_item;
  }

  /**
   * Gets the shipment package type for a shipment.
   *
   * @param \Drupal\commerce_shipping\Entity\ShipmentInterface $shipment
   *   The shipment.
   *
   * @return string
   *   The shipment package type.
   */
  protected function getShipmentPackageType(ShipmentInterface $shipment) {
    $shipment_type_storage = $this->entityTypeManager->getStorage('commerce_shipment_type');
    /** @var \Drupal\commerce_shipping\Entity\ShipmentTypeInterface $shipment_type */
    $shipment_type = $shipment_type_storage->load($shipment->bundle());

    return $shipment_type->getShipmentPackageTypeId();
  }

}