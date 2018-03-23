<?php

namespace Drupal\commerce_shipping;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\DefaultPluginManager;

class ShipmentPackagerManager extends DefaultPluginManager {

  public function __construct(\Traversable $namespaces, CacheBackendInterface $cache_backend, ModuleHandlerInterface $module_handler) {
    parent::__construct(
      'Plugin/Commerce/ShipmentPackager',
      $namespaces,
      $module_handler,
      'Drupal\commerce_shipping\Plugin\Commerce\ShipmentPackager\ShipmentPackagerInterface',
      'Drupal\commerce_shipping\Annotation\CommerceShipmentPackager'
    );
    $this->setCacheBackend($cache_backend, 'commerce_shipment_packager_plugins');
  }

}
