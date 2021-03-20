<?php

/*
 * Settings metadata file
 */
return [
  'electoralApiProviders' => [
    'group_name' => 'Electoral API settings',
    'group' => 'electoral',
    'name' => 'electoralApiProviders',
    'type' => 'Select',
    'default' => NULL,
    'add' => '5.0',
    'is_domain' => 1,
    'is_contact' => 0,
    'description' => 'Select the services you wish to use to provide data.',
    'html_type' => 'select',
    'html_attributes' => [
      'multiple' => 1,
      'class' => 'huge crm-select2',
    ],
    'pseudoconstant' => ['optionGroupName' => 'electoral_api_data_providers'],
    'title' => ts('Data Provider(s)'),
  ],
  'ciceroAPIKey' => [
    'group_name' => 'Electoral API settings',
    'group' => 'electoral',
    'name' => 'ciceroAPIKey',
    'type' => 'Text',
    'default' => '',
    'add' => '5.0',
    'is_domain' => 1,
    'is_contact' => 0,
    'description' => 'Cicero API Key',
    'help_text' => 'Add your registered Cicero API Key',
    'html_type' => 'text',
    'title' => ts('Cicero API Key'),
  ],
  'googleCivicInformationAPIKey' => [
    'group_name' => 'Electoral API settings',
    'group' => 'electoral',
    'name' => 'googleCivicInformationAPIKey',
    'type' => 'Text',
    'default' => '',
    'add' => '4.7',
    'is_domain' => 1,
    'is_contact' => 0,
    'description' => 'Google Civic API Key',
    'help_text' => 'Add your registered Google Civic Information API Key',
    'html_type' => 'text',
    'title' => ts('Google Civic Information API Key'),
  ],
  'addressLocationType' => [
    'group_name' => 'Electoral API settings',
    'group' => 'electoral',
    'name' => 'addressLocationType',
    'type' => 'Integer',
    'default' => '1',
    'add' => '4.7',
    'is_domain' => 1,
    'is_contact' => 0,
    'description' => 'Address location',
    'help_text' => 'Select the address location type to use when looking up a contact\'s districts.',
    'html_type' => 'select',
    'title' => ts('Address location for district lookup'),
    'pseudoconstant' => [
      'callback' => 'CRM_Admin_Form_Setting_Electoral::getLocationTypes',
    ],
  ],
  'electoralApiDistrictTypes' => [
    'group_name' => 'Electoral API settings',
    'group' => 'electoral',
    'name' => 'electoralApiDistrictTypes',
    'type' => 'Array',
    'default' => NULL,
    'add' => '5.33',
    'is_domain' => 1,
    'is_contact' => 0,
    'description' => 'Select the district types you want district data for.',
    'html_type' => 'select',
    'html_attributes' => [
      'multiple' => 1,
      'class' => 'huge crm-select2',
    ],
    'title' => ts('Districts to Look Up'),
    'pseudoconstant' => ['optionGroupName' => 'electoral_districts_level_options'],
  ],
  'electoralApiNonlegislativeDistricts' => [
    'group_name' => 'Electoral API settings',
    'group' => 'electoral',
    'name' => 'electoralApiNonlegislativeDistricts',
    'type' => 'Boolean',
    'default' => '',
    'add' => '5.33',
    'is_domain' => 1,
    'is_contact' => 0,
    'description' => 'Include nonlegislative district lookups (may incur additional charges).',
    'html_type' => 'checkbox',
    'title' => ts('Include Non-Legislative Districts'),
  ],
  'electoralApiIncludeRedistricted' => [
    'group_name' => 'Electoral API settings',
    'group' => 'electoral',
    'name' => 'electoralApiIncludeRedistricted',
    'type' => 'Boolean',
    'default' => '',
    'add' => '5.33',
    'is_domain' => 1,
    'is_contact' => 0,
    'description' => 'Cicero only: Include upcoming district data where available.',
    'html_type' => 'checkbox',
    'title' => ts('Include Future Districts'),
  ],
  'electoralApiAllCountries' => [
    'group_name' => 'Electoral API settings',
    'group' => 'electoral',
    'name' => 'electoralApiAllCountries',
    'type' => 'Boolean',
    'default' => '',
    'add' => '5.33',
    'is_domain' => 1,
    'is_contact' => 0,
    'description' => 'Include all countries in electoral district lookups',
    'help_text' => 'Include all countries in electoral district lookups',
    'html_type' => 'checkbox',
    'title' => ts('All Countries'),
  ],
  'electoralApiIncludedCountries' => [
    'group_name' => 'Electoral API settings',
    'group' => 'electoral',
    'name' => 'electoralApiIncludedCountries',
    'type' => 'Array',
    'default' => '',
    'add' => '5.33',
    'is_domain' => 1,
    'is_contact' => 0,
    'description' => 'Countries to include in electoral district lookups.',
    'help_text' => 'Add states and provinces to include in electoral district lookups',
    'html_type' => 'select',
    'html_attributes' => [
      'multiple' => 1,
      'class' => 'huge crm-select2',
    ],
    'title' => ts('Countries'),
    'pseudoconstant' => [
      'callback' => 'CRM_Core_PseudoConstant::country',
    ],
  ],
  'electoralApiAllStates' => [
    'group_name' => 'Electoral API settings',
    'group' => 'electoral',
    'name' => 'electoralApiAllStates',
    'type' => 'Boolean',
    'default' => '',
    'add' => '5.33',
    'is_domain' => 1,
    'is_contact' => 0,
    'description' => 'Include all states/provinces in electoral district lookups',
    'help_text' => 'Include all states/provinces in electoral district lookups',
    'html_type' => 'checkbox',
    'title' => ts('All States/Provinces'),
  ],
  'includedStatesProvinces' => [
    'group_name' => 'Electoral API settings',
    'group' => 'electoral',
    'name' => 'includedStatesProvinces',
    'type' => 'Array',
    'default' => '',
    'add' => '4.7',
    'is_domain' => 1,
    'is_contact' => 0,
    'description' => 'States and Provinces included in electoral district lookups',
    'help_text' => 'Add states and provinces to include in electoral district lookups',
    'html_type' => 'select',
    'html_attributes' => [
      'multiple' => 1,
      'class' => 'huge crm-select2',
    ],
    'title' => ts('States'),
    'pseudoconstant' => [
      'callback' => 'CRM_Core_PseudoConstant::stateProvince',
    ],
  ],
  'allCounties' => [
    'group_name' => 'Electoral API settings',
    'group' => 'electoral',
    'name' => 'allCounties',
    'type' => 'Boolean',
    'default' => '',
    'add' => '5.25',
    'is_domain' => 1,
    'is_contact' => 0,
    'description' => 'Include all counties in electoral district lookups',
    'help_text' => 'Include all counties in electoral district lookups',
    'html_type' => 'checkbox',
    'title' => ts('All Counties'),
  ],
  'includedCounties' => [
    'group_name' => 'Electoral API settings',
    'group' => 'electoral',
    'name' => 'includedCounties',
    'type' => 'Array',
    'default' => '',
    'add' => '4.7',
    'is_domain' => 1,
    'is_contact' => 0,
    'description' => 'Counties included in electoral district lookups',
    'help_text' => 'Add counties to include in electoral district lookups',
    'html_type' => 'select',
    'html_attributes' => [
      'multiple' => 1,
      'class' => 'huge crm-select2',
      'data-callback' => 'civicrm/ajax/jqCounty',
    ],
    'title' => ts('Counties'),
  ],
  'electoralApiAllCities' => [
    'group_name' => 'Electoral API settings',
    'group' => 'electoral',
    'name' => 'electoralApiAllCities',
    'type' => 'Boolean',
    'default' => '',
    'add' => '5.33',
    'is_domain' => 1,
    'is_contact' => 0,
    'description' => 'Include all cities in electoral district lookups',
    'help_text' => 'Include all cities in electoral district lookups',
    'html_type' => 'checkbox',
    'title' => ts('All Cities'),
  ],
  'includedCities' => [
    'group_name' => 'Electoral API settings',
    'group' => 'electoral',
    'name' => 'includedCities',
    'type' => 'Array',
    'default' => '',
    'add' => '4.7',
    'is_domain' => 1,
    'is_contact' => 0,
    'description' => 'Cities included in electoral district lookups',
    'help_text' => 'Add cities, comma separated, to include in electoral district lookups',
    'html_type' => 'text',
    'title' => ts('Cities'),
  ],
  'electoralApiLookupOnAddressUpdate' => [
    'group_name' => 'Electoral API settings',
    'group' => 'electoral',
    'name' => 'electoralApiLookupOnAddressUpdate',
    'type' => 'Boolean',
    'default' => '',
    'add' => '5.33',
    'is_domain' => 1,
    'is_contact' => 0,
    'description' => 'Get district data any time an address matching these criteria is added/changed.',
    'help_text' => 'Get district data any time an address matching these criteria is added/changed.',
    'html_type' => 'checkbox',
    'title' => ts('District Lookup on Address Update'),
  ],
];
