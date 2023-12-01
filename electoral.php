<?php

require_once 'electoral.civix.php';
use CRM_Electoral_ExtensionUtil as E;

/**
 * Look up a single address' districts, if the settings are correct.
 */
function electoral_civicrm_postCommit($op, $objectName, $objectId, $objectRef) {
  if (in_array($op, ['create', 'edit']) && $objectName == 'Address') {
    if (Civi::settings()->get('electoralApiLookupOnAddressUpdate') ?? FALSE) {
      // Don't run during CiviCRM import. It will either overwhelm Cicero server
      // or be deathly slow.
      if (parse_url($_REQUEST['entryURL'])['path'] == '/civicrm/import/contact') {
        return;
      }
      $limit = 1;
      $update = FALSE;
      $enabledProviders = \Civi::settings()->get('electoralApiProviders');
      foreach ($enabledProviders as $enabledProvider) {
        $className = \Civi\Api4\OptionValue::get(FALSE)
          ->addSelect('name')
          ->addWhere('option_group_id:name', '=', 'electoral_api_data_providers')
          ->addWhere('value', '=', $enabledProvider)
          ->execute()
          ->column('name')[0];
        $provider = new $className($limit, $update);
        $provider->processSingleAddress($objectId);
      }
    }
  }
}

/**
 * Implements hook_civicrm_config().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_config
 */
function electoral_civicrm_config(&$config) {
  _electoral_civix_civicrm_config($config);
}

/**
 * Implements hook_civicrm_navigationMenu().
 *
 */
function electoral_civicrm_navigationMenu(&$params) {
  $path = "Administer/System Settings";
  $item = [
    'label' => ts('Electoral API', ['com.jlacey.electoral']),
    'name' => 'Electoral API',
    'url' => 'civicrm/admin/setting/electoral',
    'permission' => 'administer CiviCRM',
    'operator' => '',
    'separator' => '',
    'active' => 1,
  ];

  $navigation = _electoral_civix_insert_navigation_menu($params, $path, $item);
}

/**
 * Implements hook_civicrm_install().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_install
 */
function electoral_civicrm_install() {
  _electoral_civix_civicrm_install();
}

/**
 * Implements hook_civicrm_uninstall().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_uninstall
 */
function electoral_civicrm_uninstall() {
  // Remove the settings.
  $settings = include(E::path('/settings/Electoral.setting.php'));
  foreach ($settings as $key => $setting) {
    // How do you delete a setting??
    \Civi::settings()->set($key, NULL);
  }
}
/**
 * Implements hook_civicrm_enable().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_enable
 */
function electoral_civicrm_enable() {
  _electoral_civix_civicrm_enable();
}

/*
 * Parse a given address into components.
 *
 * @var string $address
 * @return array
 */
function electoral_parse_address($strAddress) {
  // We expect comma separated values.
  // 123 Street, City, State, Zip, Country
  $address = [];
  $pieces = explode(',', $strAddress);
  $address['street_address'] = trim($pieces[0] ?? '');
  $address['city'] = trim($pieces[1] ?? '');
  $address['state_province'] = trim($pieces[2] ?? '');
  $address['postal_code'] = trim($pieces[3] ?? '');
  $address['country'] = trim($pieces[4] ?? '');
  return $address;
}
