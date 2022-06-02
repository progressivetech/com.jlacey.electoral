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
      // Make sure this person doesn't have data already.
      $hasDistrictData = \Civi\Api4\CustomValue::get('electoral_districts', FALSE)
        ->addWhere('entity_id', '=', $objectRef->contact_id)
        ->execute()
        ->count();
      if ($hasDistrictData) {
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
 * Implements hook_civicrm_xmlMenu().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_xmlMenu
 */
function electoral_civicrm_xmlMenu(&$files) {
  _electoral_civix_civicrm_xmlMenu($files);
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

  $demTagExists = civicrm_api3('Tag', 'getcount', ['name' => "Democrat"]);
  if ($demTagExists == 0) {
    $demTag = civicrm_api3('Tag', 'create', ['name' => "Democrat"]);
  }
  $repTagExists = civicrm_api3('Tag', 'getcount', ['name' => "Republican"]);
  if ($repTagExists == 0) {
    $repTag = civicrm_api3('Tag', 'create', ['name' => "Republican"]);
  }
  $indTagExists = civicrm_api3('Tag', 'getcount', ['name' => "Independent"]);
  if ($indTagExists == 0) {
    $indTag = civicrm_api3('Tag', 'create', ['name' => "Independent"]);
  }

  _electoral_civix_civicrm_install();
}

/**
 * Implements hook_civicrm_uninstall().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_uninstall
 */
function electoral_civicrm_uninstall() {
  _electoral_civix_civicrm_uninstall();
}

/**
 * Implements hook_civicrm_enable().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_enable
 */
function electoral_civicrm_enable() {
  _electoral_civix_civicrm_enable();
}

/**
 * Implements hook_civicrm_disable().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_disable
 */
function electoral_civicrm_disable() {
  _electoral_civix_civicrm_disable();
}

/**
 * Implements hook_civicrm_upgrade().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_upgrade
 */
function electoral_civicrm_upgrade($op, CRM_Queue_Queue $queue = NULL) {
  return _electoral_civix_civicrm_upgrade($op, $queue);
}

/**
 * Implements hook_civicrm_managed().
 *
 * Generate a list of entities to create/deactivate/delete when this module
 * is installed, disabled, uninstalled.
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_managed
 */
function electoral_civicrm_managed(&$entities) {
  _electoral_civix_civicrm_managed($entities);
}

/**
 * Implements hook_civicrm_caseTypes().
 *
 * Generate a list of case-types
 *
 * Note: This hook only runs in CiviCRM 4.4+.
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_caseTypes
 */
function electoral_civicrm_caseTypes(&$caseTypes) {
  _electoral_civix_civicrm_caseTypes($caseTypes);
}

/**
 * Implements hook_civicrm_alterSettingsFolders().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_alterSettingsFolders
 */
function electoral_civicrm_alterSettingsFolders(&$metaDataFolders = NULL) {
  _electoral_civix_civicrm_alterSettingsFolders($metaDataFolders);
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
  $address['street_address'] = trim($pieces[0] ?? NULL);
  $address['city'] = trim($pieces[1] ?? NULL);
  $address['state_province'] = trim($pieces[2] ?? NULL);
  $address['postal_code'] = trim($pieces[3] ?? NULL);
  $address['country'] = trim($pieces[4] ?? NULL);
  return $address;
}
