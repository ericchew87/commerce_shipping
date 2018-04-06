<?php

namespace Drupal\commerce_shipping\Form;

use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_shipping\Entity\ShipmentInterface;
use Drupal\commerce_shipping\PackageTypeManagerInterface;
use Drupal\commerce_shipping\ShipmentItem;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\TempStore\SharedTempStoreFactory;
use Drupal\physical\Weight;
use Drupal\physical\WeightUnit;
use Symfony\Component\DependencyInjection\ContainerInterface;

class ShipmentBuilderForm extends FormBase {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The package type manager.
   *
   * @var \Drupal\commerce_shipping\PackageTypeManagerInterface
   */
  protected $packageTypeManager;

  /**
   * The shared tempstore factory.
   *
   * @var \Drupal\Core\TempStore\SharedTempStoreFactory
   */
  protected $tempStoreFactory;

  /**
   * The shipment entity.
   *
   * @var \Drupal\commerce_shipping\Entity\ShipmentInterface
   */
  protected $shipment;

  /**
   * Constructs a new ShipmentBuilderForm object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\commerce_shipping\PackageTypeManagerInterface $package_type_manager
   *   The package type manager.
   * @param \Drupal\Core\TempStore\SharedTempStoreFactory $temp_store_factory
   *   The shared temp store factory.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, PackageTypeManagerInterface $package_type_manager, SharedTempStoreFactory $temp_store_factory) {
    $this->packageTypeManager = $package_type_manager;
    $this->tempStoreFactory = $temp_store_factory;
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * @inheritdoc
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('plugin.manager.commerce_package_type'),
      $container->get('user.shared_tempstore')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'commerce_shipping_shipment_builder';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, OrderInterface $order = NULL, ShipmentInterface $shipment = NULL) {

    if (!$shipment) {
      $shipment = $this->entityTypeManager->getStorage('commerce_shipment')->create([
        'type' => $this->getShipmentType($order),
      ]);
    }
    $this->shipment = $shipment;

    $temp_store = $this->getTempstore();
    if (empty($temp_store->get('shipment'))) {
      $this->prepareShipment();
    }

    /** @var \Drupal\commerce_shipping\Entity\ShipmentInterface $shipment */
    $shipment = $temp_store->get(('shipment'));
    $order_id = $shipment->getOrderId();

    $shipment_id = $shipment->id() ? $shipment->id() : 'new';

    $package_types = $this->packageTypeManager->getDefinitions();
    $package_types = array_map(function ($package_type) {
      return $package_type['label'];
    }, $package_types);

    $form['new_package'] = [
      '#type' => 'fieldset',
    ];
    $form['new_package']['new_package_select'] = [
      '#type' => 'select',
      '#title' => t('Package Type'),
      '#options' => $package_types,
    ];

    $form['new_package']['new_package_submit'] = [
      '#type' => 'submit',
      '#value' => t('Add Package'),
      '#submit' => [[$this, 'addPackageSubmit']],
      '#ajax' => [
        'callback' => [$this, 'ajaxRefresh'],
        'wrapper' => 'shipment-builder'
      ],
    ];

    $form['shipment_builder'] = [
      '#type' => 'container',
      '#attributes' => [
        'id' => [
          'shipment-builder',
        ],
      ],
    ];

    $form['shipment_builder']['shipment_items'] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => [
          'shipment-builder__area',
          'shipment-items'
        ],
        'data-layout-update-url' => '/shipment_builder/move/'.$order_id.'/'.$shipment_id,
      ],
    ];

    $form['shipment_builder']['packages'] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => [
          'package-area',
        ],
        'data-layout-update-url' => '/shipment_builder/move/'.$order_id.'/'.$shipment_id,
      ],
    ];

    $packages = $shipment->getPackages();
    $packaged_items = [];
    foreach ($packages as $delta => $package) {
      $package_items = '';
      foreach ($package->getItems() as $item) {
        $packaged_items[] = $item;
        $package_items .=
          '<div data-shipment-item-id="' . $item->getOrderItemId() . '-' . (int)$item->getQuantity() . '" class="draggable shipment-item">' . $item->getTitle() . '</div>';
      }

      $form['shipment_builder']['packages'][$delta] = [
        '#markup' => '<div data-package-id="'.$delta.'" class="shipment-builder__area">'.$package->getTitle(). $package_items .'</div>',
      ];

      $form['shipment_builder']['packages'][$delta]['remove'] = [
        '#type' => 'submit',
        '#value' => t('Remove Package'),
        '#package_delta' => $delta,
        '#submit' => [[$this, 'removePackageSubmit']],
        '#ajax' => [
          'callback' => [$this, 'ajaxRefresh'],
          'wrapper' => 'shipment-builder'
        ],
      ];
    }

    foreach ($shipment->getItems() as $item) {
      if (!in_array($item, $packaged_items)) {
        $form['shipment_builder']['shipment_items'][] = [
          '#markup' => '<div data-shipment-item-id="'.
            $item->getOrderItemId() . '-' .
            (int)$item->getQuantity().'"
           class="draggable shipment-item">' . $item->getTitle() . '</div>',
        ];
      }
    }

    $form['actions']['#type'] = 'actions';
    $form['actions']['submit'] = array(
      '#type' => 'submit',
      '#value' => $this->t('Save'),
      '#button_type' => 'primary',
    );
    $form['actions']['cancel'] = array(
      '#type' => 'submit',
      '#value' => $this->t('Cancel'),
      '#submit' => [[$this, 'cancelSubmit']],
    );

    $form['#attached']['library'][] = 'commerce_shipping/shipment_builder';

    return $form;
  }

  /**
   * Submit handler for the Save button. Saves the shipment from TempStore.
   *
   * @param array $form
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $shipment = $this->getTempstore()->get('shipment');
    $shipment->save();
    $this->getTempStore()->delete('shipment');
  }

  /**
   * Submit handler for the Cancel button. Deletes the TempStore shipment.
   *
   * @param array $form
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   */
  public function cancelSubmit(array &$form, FormStateInterface $form_state) {
    $this->getTempStore()->delete('shipment');
  }


  /**
   * Returns the TempStore.
   *
   * @return \Drupal\Core\TempStore\SharedTempStore
   *   The tempstore.
   */
  protected function getTempstore() {
    return $this->tempStoreFactory->get($this->getTempStoreId());
  }

  /**
   * Returns the ID of the TempStore.
   *
   * @return string
   */
  protected function getTempStoreId() {
    /** @var \Drupal\commerce_shipping\Entity\ShipmentInterface $shipment */
    $shipment = $this->shipment;

    $order = $this->getOrder();

    if ($shipment->isNew()) {
      $collection = 'commerce_shipping.order.'.$order->id().'.shipment.new';
    } else {
      $collection = 'commerce_shipping.order.'.$order->id().'.shipment.'.$shipment->id();
    }

    return $collection;
  }

  /**
   * Gets the Order from shipment.
   * If no order found, gets the Order from Route and saves it to shipment.
   *
   * @return \Drupal\commerce_order\Entity\OrderInterface
   */
  protected function getOrder() {
    $shipment = $this->shipment;
    $order_id = $shipment->get('order_id')->target_id;

    if (!$order_id) {
      $order_id = $this->getRouteMatch()->getParameter('commerce_order');
      $shipment->set('order_id', $order_id);
    }

    /** @var \Drupal\commerce_order\Entity\OrderInterface $order */
    $order = $this->entityTypeManager->getStorage('commerce_order')->load($order_id);

    return $order;
  }

  /**
   * Gets the shipment type for the current order.
   *
   * @param \Drupal\commerce_order\Entity\OrderInterface $order
   *   The order.
   *
   * @return string
   *   The shipment type.
   */
  protected function getShipmentType(OrderInterface $order) {
    $order_type_storage = $this->entityTypeManager->getStorage('commerce_order_type');
    /** @var \Drupal\commerce_order\Entity\OrderTypeInterface $order_type */
    $order_type = $order_type_storage->load($order->bundle());

    return $order_type->getThirdPartySetting('commerce_shipping', 'shipment_type');
  }

  /**
   * Populates the ShipmentItems on a shipment if empty and saves the shipment to TempStore.
   */
  protected function prepareShipment() {

    $temp_store = $this->getTempstore();
    /** @var \Drupal\commerce_shipping\Entity\ShipmentInterface $shipment */
    $shipment = $this->shipment;

    if (!$shipment->hasItems()) {
      $order = $this->getOrder();
      $items = [];
      foreach ($order->getItems() as $order_item) {
        $purchased_entity = $order_item->getPurchasedEntity();
        // Ship only shippable purchasable entity types.
        if (!$purchased_entity || !$purchased_entity->hasField('weight')) {
          continue;
        }
        // The weight will be empty if the shippable trait was added but the
        // existing entities were not updated.
        if ($purchased_entity->get('weight')->isEmpty()) {
          $purchased_entity->set('weight', new Weight(0, WeightUnit::GRAM));
        }

        $quantity = $order_item->getQuantity();
        /** @var \Drupal\physical\Weight $weight */
        $weight = $purchased_entity->get('weight')->first()->toMeasurement();
        $items[] = new ShipmentItem([
          'order_item_id' => $order_item->id(),
          'title' => $order_item->getTitle(),
          'quantity' => $quantity,
          'weight' => $weight->multiply($quantity),
          'declared_value' => $order_item->getUnitPrice()->multiply($quantity),
        ]);
      }
      $shipment->setItems($items);
    }

    $temp_store->set('shipment', $shipment);
  }

  /**
   * AJAX refresh for rebuilding the ShipmentBuilder form.
   *
   * @param array $form
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *
   * @return mixed
   */
  public static function ajaxRefresh(array $form, FormStateInterface $form_state) {
    return $form['shipment_builder'];
  }

  /**
   * Adds a new package to the shipment in TempStore.
   *
   * @param array $form
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   */
  public function addPackageSubmit(array $form, FormStateInterface $form_state) {
    $temp_store = $this->getTempstore();
    $shipment = $temp_store->get('shipment');
    $values = $form_state->getValues();
    $package_type = $this->packageTypeManager->createInstance($values['new_package_select']);
    /** @var \Drupal\commerce_shipping\Entity\ShipmentInterface $shipment */

    /** @var \Drupal\commerce_shipping\Entity\PackageInterface $package */
    $package = $this->entityTypeManager->getStorage('commerce_package')->create([
      'items' => [],
      'title' => $package_type->getLabel(),
      'package_type' => $package_type->getId(),
      'weight' => new Weight('0', 'g'),
    ]);
    $shipment->addPackage($package);
    $temp_store->set('shipment', $shipment);
    $form_state->setRebuild();
  }

  /**
   * Removes a package from the shipment in TempStore.
   *
   * @param array $form
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   */
  public function removePackageSubmit(array $form, FormStateInterface $form_state) {
    $temp_store = $this->getTempstore();
    /** @var \Drupal\commerce_shipping\Entity\ShipmentInterface $shipment */
    $shipment = $temp_store->get('shipment');
    $package = $shipment->getPackages()[$form_state->getTriggeringElement()['#package_delta']];
    $shipment->removePackage($package);
    $temp_store->set('shipment', $shipment);
    $form_state->setRebuild();
  }
}