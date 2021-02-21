<?php

class CRM_Admin_Form_Setting_Electoral extends CRM_Admin_Form_Setting {

  


  public function buildQuickForm() {
    // Settings forms don't support chain-select, so we need to have some code here.
    // $this->addChainSelect('includedCounties', [
    //   'control_field' => 'includedStatesProvinces',
    //   'data-callback' => 'civicrm/ajax/jqCounty',
    //   'label' => "Counties included in the API calls",
    //   'data-empty-prompt' => 'Choose state first',
    //   'data-none-prompt' => '- N/A -',
    //   'multiple' => TRUE,
    //   'required' => FALSE,
    //   'placeholder' => '- none -',
    // ]);
    parent::buildQuickForm();
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
