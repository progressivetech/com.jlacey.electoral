<?php
/**
 * Form controller class
 *
 * @see http://wiki.civicrm.org/confluence/display/CRMDOC43/QuickForm+Reference
 */
class CRM_Admin_Form_Setting_Electoral extends CRM_Admin_Form_Setting {

  protected $_settings = [
    'electoralApiProviders' => 'Electoral API settings',
    'ciceroAPIKey' => 'Electoral API settings',
    'googleCivicInformationAPIKey' => 'Electoral API settings',
    'addressLocationType' => 'Electoral API settings',
    'includedStatesProvinces' => 'Electoral API settings',
    'includedCounties' => 'Electoral API settings',
    'includedCities' => 'Electoral API settings',
    'allCounties' => 'Electoral API settings',
    'electoralApiAllStates' => 'Electoral API settings',
    'electoralApiDistrictTypes' => 'Electoral API settings',
    'electoralApiIncludeRedistricted' => 'Electoral API settings',
  ];

  public function buildQuickForm() {
    parent::buildQuickForm();
    $this->addChainSelect('includedCounties', [
      'control_field' => 'includedStatesProvinces',
      'data-callback' => 'civicrm/ajax/jqCounty',
      'label' => "Counties",
      'data-empty-prompt' => 'Choose state first',
      'data-none-prompt' => '- N/A -',
      'multiple' => TRUE,
      'required' => FALSE,
      'placeholder' => '- none -',
    ]);
  }

  /**
   * Return options for the location type field.
   * @return array
   */
  public static function getLocationTypes() {
    $location_types = CRM_Core_PseudoConstant::get('CRM_Core_DAO_Address', 'location_type_id');
    $location_types = ['Primary'] + $location_types;
    return $location_types;
  }

}
