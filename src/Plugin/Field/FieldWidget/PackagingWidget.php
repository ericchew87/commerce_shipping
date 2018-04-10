<?php

namespace Drupal\commerce_shipping\Plugin\Field\FieldWidget;

use Drupal\commerce_product\Entity\ProductInterface;
use Drupal\commerce_product\Entity\ProductVariation;
use Drupal\commerce_rental\Entity\RentalPeriod;
use Drupal\commerce_rental\RentalRateHelper;
use Drupal\commerce_shipping\PackageTypeManagerInterface;
use Drupal\Component\Utility\Html;
use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Entity\Element\EntityAutocomplete;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldFilteredMarkup;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\Field\WidgetBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;


/**
 * Plugin implementation of the 'commerce_packaging_default' widget.
 *
 * @FieldWidget(
 *   id = "commerce_packaging_default",
 *   label = @Translation("Packaging"),
 *   field_types = {
 *     "commerce_packaging",
 *   }
 * )
 */
class PackagingWidget extends WidgetBase implements ContainerFactoryPluginInterface {
  /**
   * The package type manager.
   *
   * @var \Drupal\commerce_shipping\PackageTypeManagerInterface
   */
  protected $packageTypeManager;

  /**
   * Constructs a new ProductVariationWidgetBase object.
   *
   * @param string $plugin_id
   *   The plugin_id for the widget.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Field\FieldDefinitionInterface $field_definition
   *   The definition of the field to which the widget is associated.
   * @param array $settings
   *   The widget settings.
   * @param array $third_party_settings
   *   Any third party settings.
   * @param \Drupal\commerce_shipping\PackageTypeManagerInterface $package_type_manager
   *   The entity type manager.
   */
  public function __construct($plugin_id, $plugin_definition, FieldDefinitionInterface $field_definition, array $settings, array $third_party_settings, PackageTypeManagerInterface $package_type_manager) {
    parent::__construct($plugin_id, $plugin_definition, $field_definition, $settings, $third_party_settings);

    $this->packageTypeManager = $package_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $plugin_id,
      $plugin_definition,
      $configuration['field_definition'],
      $configuration['settings'],
      $configuration['third_party_settings'],
      $container->get('plugin.manager.commerce_package_type')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    $package_types = $this->packageTypeManager->getDefinitions();
    $package_types = array_map(function ($package_type) {
      return $package_type['label'];
    }, $package_types);

    $element['package_type'] = [
      '#type' => 'select',
      '#title' => t('Package Type'),
      '#title_display' => FALSE,
      '#empty_option' => t('- Select -'),
      '#options' => $package_types,
      '#default_value' => isset($items[$delta]->package_type) ? $items[$delta]->package_type : '',
      '#attributes' => [],
    ];

    $element['min'] = [
      '#type' => 'number',
      '#title' => t('Minimum'),
      '#title_display' => FALSE,
      '#default_value' => isset($items[$delta]->min) ? $items[$delta]->min : 0,
      '#placeholder' => $this->getSetting('placeholder'),
      '#step' => 1,
      '#min' => 0
    ];

    $element['max'] = [
      '#type' => 'number',
      '#title' => t('Maximum'),
      '#title_display' => FALSE,
      '#default_value' => isset($items[$delta]->max) ? $items[$delta]->max : 0,
      '#placeholder' => $this->getSetting('placeholder'),
      '#step' => 1,
      '#min' => 0
    ];

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function formMultipleElements(FieldItemListInterface $items, array &$form, FormStateInterface $form_state) {
    $field_name = $this->fieldDefinition->getName();
    $cardinality = $this->fieldDefinition->getFieldStorageDefinition()->getCardinality();
    $parents = $form['#parents'];
    $field_state = static::getWidgetState($parents, $field_name, $form_state);
    $max = $field_state['items_count'] == 0 ? 1 : $field_state['items_count'];
    $is_multiple = TRUE;

    $title = $this->fieldDefinition->getLabel();
    $description = FieldFilteredMarkup::create(\Drupal::token()->replace($this->fieldDefinition->getDescription()));

    $elements['details'] = [
      '#type' => 'details',
      '#title' => $this->t('Manual Packaging'),
      '#open' => TRUE,
    ];

    $elements['details']['table'] = [
      '#type' => 'table',
      '#header' => [
        'Package Type',
        'Minimum',
        'Maximum',
      ],
      '#field_parents' => $parents,
      '#field_name' => $field_name,
      '#required' => $this->fieldDefinition->isRequired(),
      '#title' => $title,
      '#description' => $description,
      '#element_validate' => [
        [static::class, 'validate'],
      ],
    ];

    if ($max > 0) {
      for ($delta = 0; $delta < $max; $delta++) {
        // Add a new empty item if it doesn't exist yet at this delta.
        if (!isset($items[$delta])) {
          $items->appendItem();
        }

        // For multiple fields, title and description are handled by the wrapping
        // table.
        if ($is_multiple) {
          $element = [
            '#title' => $this->t('@title (value @number)', ['@title' => $title, '@number' => $delta + 1]),
            '#title_display' => 'invisible',
            '#description' => '',
          ];
        }
        else {
          $element = [
            '#title' => $title,
            '#title_display' => 'before',
            '#description' => $description,
          ];
        }

        $element = $this->formSingleElement($items, $delta, $element, $form, $form_state);

        if ($element) {
          // Input field for the delta (drag-n-drop reordering).
          $elements['details']['table'][$delta] = $element;
        }
      }
    }

    $field_state['items_count'] = $max;
    static::setWidgetState($parents, $field_name, $form_state, $field_state);
    if ($elements) {
      $elements['details']['table']['#max_delta'] = $max;

      // Add 'add more' button, if not working with a programmed form.
      if ($cardinality == FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED && !$form_state->isProgrammed()) {
        $id_prefix = implode('-', array_merge($parents, [$field_name]));
        $wrapper_id = Html::getUniqueId($id_prefix . '-add-more-wrapper');
        $elements['details']['#prefix'] = '<div id="' . $wrapper_id . '">';
        $elements['details']['#suffix'] = '</div>';

        $elements['details']['add_more'] = [
          '#type' => 'submit',
          '#name' => strtr($id_prefix, '-', '_') . '_add_more',
          '#value' => t('Add Packaging'),
          '#attributes' => ['class' => ['field-add-more-submit']],
          '#limit_validation_errors' => [array_merge($parents, [$field_name])],
          '#submit' => [[get_class($this), 'addMoreSubmit']],
          '#ajax' => [
            'callback' => [get_class($this), 'addMoreAjax'],
            'wrapper' => $wrapper_id,
            'effect' => 'fade',
          ],
        ];
      }
    }

    return $elements;
  }

  /**
   * Submission handler for the "Add another item" button.
   */
  public static function addMoreSubmit(array $form, FormStateInterface $form_state) {
    $button = $form_state->getTriggeringElement();

    // Go one level up in the form, to the widgets container.
    $element = NestedArray::getValue($form, array_slice($button['#array_parents'], 0, -1));
    $field_name = $element['table']['#field_name'];
    $parents = $element['table']['#field_parents'];

    // Increment the items count.
    $field_state = static::getWidgetState($parents, $field_name, $form_state);
    $field_state['items_count']++;
    static::setWidgetState($parents, $field_name, $form_state, $field_state);

    $form_state->setRebuild();
  }

  /**
   * Make sure max is greater than min.
   *
   * @param array $form
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   */
  public static function validate(array $form, FormStateInterface $form_state) {
    $values = $form_state->getValue($form['#parents']) ;
    foreach ($values as $delta => $value) {
     if (is_array($value) && $value['min'] > $value['max']) {
       $form_state->setError($form[$delta], t('Minimum must be less than Maximum.'));
     }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function massageFormValues(array $values, array $form, FormStateInterface $form_state) {
    $values = $values[0]['table'];
    uasort($values, function ($a, $b) {
      if (!empty($a['max']) && !empty($b['max'])) {
        return ($a['max'] > $b['max']) ? -1 : 1;
      }
      return 0;
    });
    return $values;
  }

  /**
   * {@inheritdoc}
   */
  public static function addMoreAjax(array $form, FormStateInterface $form_state) {
    $button = $form_state->getTriggeringElement();
    // Go one level up in the form, to the widgets container.
    $element = NestedArray::getValue($form, array_slice($button['#array_parents'], 0, -2));

    // Add a DIV around the delta receiving the Ajax effect.
    $delta = $element['#max_delta'];
    $element[$delta]['#prefix'] = '<div class="ajax-new-content">' . (isset($element[$delta]['#prefix']) ? $element[$delta]['#prefix'] : '');
    $element[$delta]['#suffix'] = (isset($element[$delta]['#suffix']) ? $element[$delta]['#suffix'] : '') . '</div>';

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public static function isApplicable(FieldDefinitionInterface $field_definition) {
    $entity_type = $field_definition->getTargetEntityTypeId();
    $field_name = $field_definition->getName();
    return $entity_type == 'commerce_product_variation' && $field_name == 'packaging';
  }

}
