<?php

/*
 * Settings metadata file
 */
return [
  'ciceroAPIKey' => [
    'settings_pages' => ['electoral' => ['weight' => 10]],
    'name' => 'ciceroAPIKey',
    'type' => 'Text',
    'default' => '',
    'add' => '5.33',
    'is_domain' => 1,
    'is_contact' => 0,
    'description' => 'Cicero API Key',
    'html_type' => 'text',
    'title' => ts('Cicero API Key'),
  ],
  'googleCivicInformationAPIKey' => [
    'settings_pages' => ['electoral' => ['weight' => 20]],
    'name' => 'googleCivicInformationAPIKey',
    'type' => 'Text',
    'default' => '',
    'add' => '4.7',
    'is_domain' => 1,
    'is_contact' => 0,
    'description' => ts('Enter your Google Civic Information API Key.  <a %1>Register at the Google Civic Information API</a> to obtain a key.', [1 => 'href="https://developers.google.com/civic-information/docs/using_api#APIKey" target="_blank"']),
    'html_type' => 'text',
    'title' => ts('Google Civic Information API Key'),
  ],
  'proPublicaCongressAPIKey' => [
    'settings_pages' => ['electoral' => ['weight' => 30]],
    'name' => 'proPublicaCongressAPIKey',
    'type' => 'Text',
    'default' => '',
    'add' => '4.7',
    'is_domain' => 1,
    'is_contact' => 0,
    'description' => ts('Enter your ProPublica Congress API Key.  <a %1>Register at the ProPublica Congress API</a> to obtain a key.', [1 => 'href="https://www.propublica.org/datastore/api/propublica-congress-api" target="_blank"']),
    'html_type' => 'text',
    'title' => ts('ProPublica Congress API Key'),
  ],
  'addressLocationType' => [
    'settings_pages' => ['electoral' => ['weight' => 40]],
    'name' => 'addressLocationType',
    'type' => 'Integer',
    'default' => '1',
    'add' => '4.7',
    'is_domain' => 1,
    'is_contact' => 0,
    'description' => ts('Select the address location type to use when looking up a contact\'s districts.'),
    'html_type' => 'select',
    'title' => ts('Address location for district lookup'),
    'pseudoconstant' => [
      'callback' => 'CRM_Admin_Form_Setting_Electoral::getLocationTypes',
    ],
  ],
  'includedStatesProvinces' => [
    'settings_pages' => ['electoral' => ['weight' => 50]],
    'name' => 'includedStatesProvinces',
    'type' => 'Array',
    'default' => '',
    'add' => '4.7',
    'is_domain' => 1,
    'is_contact' => 0,
    'description' => ts('Select states and provinces to include in API scheduled jobs.'),
    'html_type' => 'select',
    'html_attributes' => [
      'multiple' => 1,
      'class' => 'huge crm-select2',
    ],
    'title' => ts('States included in API calls'),
    // FIXME: This callback method is deprecated.
    'pseudoconstant' => [
      'callback' => 'CRM_Core_PseudoConstant::stateProvince',
    ],
  ],
  // Do NOT fix the typo in the name - see CRM_Core_Form::addMultiSelect and https://github.com/civicrm/civicrm-core/pull/19629
  'includedCountys' => [
    'settings_pages' => ['electoral' => ['weight' => 60]],
    'name' => 'includedCountys',
    'type' => 'Array',
    'default' => '',
    'add' => '4.7',
    'is_domain' => 1,
    'is_contact' => 0,
    'description' => ts('Select counties to include in API scheduled jobs.'),
    // Do not remove quick_form_type, see https://github.com/civicrm/civicrm-core/pull/19629.
    'quick_form_type' => 'ChainSelect',
    'html_type' => 'ChainSelect',
    'html_attributes' => [
      'multiple' => 1,
      'class' => 'huge crm-select2',
    ],

    'title' => ts('Counties included in the API calls'),
    // FIXME: This callback method is deprecated.
    'pseudoconstant' => [
      'callback' => 'CRM_Core_PseudoConstant::county',
    ],
  ],
  'allCounties' => [
    'settings_pages' => ['electoral' => ['weight' => 70]],
    'name' => 'allCounties',
    'type' => 'Boolean',
    'default' => '',
    'add' => '5.25',
    'is_domain' => 1,
    'is_contact' => 0,
    'description' => ts('Include all counties in API scheduled jobs'),
    'html_type' => 'checkbox',
    'title' => ts('All Counties'),
  ],
  'includedCities' => [
    'settings_pages' => ['electoral' => ['weight' => 80]],
    'name' => 'includedCities',
    'type' => 'Array',
    'default' => '',
    'add' => '4.7',
    'is_domain' => 1,
    'is_contact' => 0,
    'description' => ts('Select cities to include in API scheduled jobs.'),
    'html_type' => 'text',
    'title' => ts('Cities included in API Calls'),
  ],
];
