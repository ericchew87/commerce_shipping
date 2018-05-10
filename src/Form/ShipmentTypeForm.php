<?php

namespace Drupal\commerce_shipping\Form;

use Drupal\commerce\EntityHelper;
use Drupal\commerce\EntityTraitManagerInterface;
use Drupal\commerce\Form\CommerceBundleEntityFormBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

class ShipmentTypeForm extends CommerceBundleEntityFormBase {

  /**
   * The shipment package type storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $shipmentPackageTypeStorage;

  /**
   * Creates a new ShipmentTypeForm object.
   *
   * @param \Drupal\commerce\EntityTraitManagerInterface $trait_manager
   *   The entity trait manager.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(EntityTraitManagerInterface $trait_manager, EntityTypeManagerInterface $entity_type_manager) {
    parent::__construct($trait_manager);

    $this->shipmentPackageTypeStorage = $entity_type_manager->getStorage('commerce_shipment_package_type');
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('plugin.manager.commerce_entity_trait'),
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    $form = parent::form($form, $form_state);
    /** @var \Drupal\commerce_shipping\Entity\ShipmentTypeInterface $shipment_type */
    $shipment_type = $this->entity;
    $shipment_package_types = $this->shipmentPackageTypeStorage->loadMultiple();

    $form['label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Label'),
      '#maxlength' => 255,
      '#default_value' => $shipment_type->label(),
      '#required' => TRUE,
    ];
    $form['id'] = [
      '#type' => 'machine_name',
      '#default_value' => $shipment_type->id(),
      '#machine_name' => [
        'exists' => '\Drupal\commerce_shipping\Entity\ShipmentType::load',
      ],
      '#maxlength' => EntityTypeInterface::BUNDLE_MAX_LENGTH,
    ];
    $form['shipmentPackageType'] = [
      '#type' => 'select',
      '#title' => $this->t('Shipment package type'),
      '#default_value' => $shipment_type->getShipmentPackageTypeId(),
      '#options' => EntityHelper::extractLabels($shipment_package_types),
      '#required' => TRUE,
      '#disabled' => !$shipment_type->isNew(),
    ];
    $form = $this->buildTraitForm($form, $form_state);

    return $this->protectBundleIdElement($form);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $this->validateTraitForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    $this->entity->save();
    $this->submitTraitForm($form, $form_state);

    drupal_set_message($this->t('Saved the %label shipment type.', [
      '%label' => $this->entity->label(),
    ]));
    $form_state->setRedirect('entity.commerce_shipment_type.collection');
  }

}
