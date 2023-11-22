<?php

use CRM_Electoral_ExtensionUtil as E;

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
    'openstatesAPIKey' => 'Electoral API settings',
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
    'electoralApiFutureDate' => 'Electoral API settings',
    'electoralApiLookupOnAddressUpdate' => 'Electoral API settings',
  ];

  public function buildQuickForm() {
    Civi::resources()->addStyleFile('com.jlacey.electoral', 'electoral.css');
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

    $this->populateDistrictJobs();
    parent::buildForm();
  }

  protected function populateDistrictJobs() {
    $data = \Civi\Api4\DistrictJob::get()
      ->execute();
    $districtJobs = [];
    foreach ($data as $job) {
      // Add some calculated details
      $contacts = unserialize($job['contact_ids']);
      $job['total_contacts'] = count($contacts);
      $job['percent_complete'] = number_format($job['offset'] / $job['total_contacts'] * 100, 0);
      $job['delete_link'] = CRM_Utils_System::url('civicrm/admin/setting/electoral/delete', ['id' => $job['id']]);
      $districtJobs[] = $job;
    }
    $this->assign('districtJobs', $districtJobs);

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
   * AddRules hook
   */
  public function addRules() {
    $this->addFormRule([self::class, 'validateForm']);
  }

  /**
   * Validates form
   *
   * @param $values
   *
   * @return array
   */
  public static function validateForm($values) {
    $errors = [];
    // Ensure date is entered in the proper format.
    $electoralApiFutureDate = $values['electoralApiFutureDate'] ?? NULL;
    if ($electoralApiFutureDate) {
      $looksValid = preg_match('/^[0-9]{4}-[0-9]{2}-[0-9]{2}/', $electoralApiFutureDate);
      if (!$looksValid) {
        $errors['electoralApiFutureDate'] = E::ts('Please enter the date in YYYY-MM-DD format.');
      }
      else {
        if (!strtotime($electoralApiFutureDate)) {
          $errors["electoralApiFutureDate"] = E::ts('Please double check the date format to ensure it is a valid date (YYYY-MM-DD).');
        }
      }
    }

    // Ensure limit per run is an integer.
    $limit = $values['limit_per_run'] ?? NULL;
    if ($limit) {
      if (!is_numeric($limit)) {
        $errors['limit_per_run'] = E::ts("Please enter a number for limit to run.");
      }
    }
    return empty($errors) ? TRUE : $errors;
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
    $session = CRM_Core_Session::singleton();
    $session->replaceUserContext(CRM_Utils_System::url('civicrm/admin/setting/electoral'));

  }

}
