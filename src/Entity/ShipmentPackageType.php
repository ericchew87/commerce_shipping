<?php

namespace Drupal\commerce_shipping\Entity;

use Drupal\commerce\Entity\CommerceBundleEntityBase;

/**
 * Defines the shipment package type entity class.
 *
 * @ConfigEntityType(
 *   id = "commerce_shipment_package_type",
 *   label = @Translation("Shipment package type"),
 *   label_singular = @Translation("shipment package type"),
 *   label_plural = @Translation("shipment package types"),
 *   label_count = @PluralTranslation(
 *     singular = "@count shipment package type",
 *     plural = "@count shipment package types",
 *   ),
 *   handlers = {
 *     "list_builder" = "Drupal\commerce_shipping\ShipmentPackageTypeListBuilder",
 *     "form" = {
 *       "add" = "Drupal\commerce_shipping\Form\ShipmentPackageTypeForm",
 *       "edit" = "Drupal\commerce_shipping\Form\ShipmentPackageTypeForm",
 *       "delete" = "Drupal\commerce_shipping\Form\ShipmentPackageTypeDeleteForm"
 *     },
 *     "route_provider" = {
 *       "default" = "Drupal\Core\Entity\Routing\DefaultHtmlRouteProvider",
 *     },
 *   },
 *   admin_permission = "administer commerce_shipment_package_type",
 *   config_prefix = "commerce_shipment_package_type",
 *   bundle_of = "commerce_package",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "label",
 *     "uuid" = "uuid",
 *   },
 *   config_export = {
 *     "id",
 *     "label",
 *     "uuid",
 *     "traits",
 *   },
 *   links = {
 *     "add-form" = "/admin/commerce/config/shipment-package-types/add",
 *     "edit-form" = "/admin/commerce/config/shipment-package-types/{commerce_shipment_package_type}/edit",
 *     "delete-form" = "/admin/commerce/config/shipment-package-types/{commerce_shipment_package_type}/delete",
 *     "collection" = "/admin/commerce/config/shipment-package-types",
 *   }
 * )
 */
class ShipmentPackageType extends CommerceBundleEntityBase implements ShipmentPackageTypeInterface {

}
