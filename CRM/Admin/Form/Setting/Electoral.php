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
    'electoralApiIncludedCountries' => 'Electoral API settings',
    //'includedStatesProvinces' => 'Electoral API settings',
    //'includedCounties' => 'Electoral API settings',
    'includedCities' => 'Electoral API settings',
    'electoralApiAllCountries' => 'Electoral API settings',
    'electoralApiAllStates' => 'Electoral API settings',
    'allCounties' => 'Electoral API settings',
    'electoralApiAllCities' => 'Electoral API settings',
    'electoralApiDistrictTypes' => 'Electoral API settings',
    'electoralApiIncludeRedistricted' => 'Electoral API settings',
    'electoralApiLookupOnAddressUpdate' => 'Electoral API settings',
    'electoralApiCreateOfficialOnDistrictLookup' => 'Electoral API settings',
  ];

  public function buildQuickForm() {
    // This whole function is until metadata-driven chain-selects are solid in core.
    $this->addChainSelect('includedStatesProvinces', [
      'control_field' => 'electoralApiIncludedCountries',
      'data-callback' => 'civicrm/ajax/jqState',
      'label' => "States",
      'data-empty-prompt' => 'Choose country first',
      'data-none-prompt' => '- N/A -',
      'multiple' => TRUE,
      'required' => FALSE,
      'placeholder' => '- none -',
    ]);
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
    parent::buildQuickForm();
  }

  public function buildForm() {
    // This whole function is until metadata-driven chain-selects are solid in core.
    $this->setDefaultsForMetadataDefinedFields();
    $this->_defaults['includedStatesProvinces'] = Civi::settings()->get('includedStatesProvinces');
    $this->_defaults['includedCounties'] = Civi::settings()->get('includedCounties');
    parent::buildForm();
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

  /**
   * Necessary until metadata-driven chain-select is properly handled in Smarty forms.
   */
  public function postProcess() {
    // This is until metadata-driven chain-selects are solid in core.
    $this->_settings['includedStatesProvinces'] = 'Electoral API settings';
    $this->_settings['includedCounties'] = 'Electoral API settings';
    $this->settingsMetadata = \Civi\Core\SettingsMetadata::getMetadata(['name' => array_keys($this->_settings)], NULL, TRUE);
    parent::postProcess();
    // This part is permanent, for now at least.
    // Check if Cicero is active.  Enable or disable the "valid from/to" date custom fields accordingly.
    $ciceroId = \Civi\Api4\OptionValue::get(FALSE)
      ->addSelect('value')
      ->addWhere('option_group_id:name', '=', 'electoral_api_data_providers')
      ->addWhere('label', '=', 'Cicero')
      ->execute()
      ->column('value')[0];
    $activeDates = in_array($ciceroId, $this->_submitValues['electoralApiProviders']);
    \Civi\Api4\CustomField::update(FALSE)
      ->addWhere('name', 'IN', ['electoral_valid_from', 'electoral_valid_to'])
      ->addValue('is_active', $activeDates)
      ->execute();
  }

}
