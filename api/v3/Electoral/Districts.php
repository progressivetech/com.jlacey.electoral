<?php
use CRM_Electoral_ExtensionUtil as E;

/**
 * Electoral.Districts API
 *
 * @see civicrm_api3_create_success
 *
 * @throws API_Exception
 */
function civicrm_api3_electoral_Districts(array $params) : array {
  $limit = (int) ($params['limit'] ?? 100);
  $update = (bool) ($params['update'] ?? FALSE);
  $groups = $params['groups'] ?? FALSE;
  if ($groups) {
    // It's tempting to put double quotes or single quotes around
    // the group names when adding it as a parameter on the scheduled
    // jobs web UI.
    $groups = trim($groups, '"\' ');
  }
  $result = \Civi\Api4\Electoral::RunOneOff()
    ->setGroups($groups) 
    ->setUpdate($update)
    ->setLimit($limit)
    ->execute();

  return civicrm_api3_create_success($returnValues, $params, 'Electoral', 'Districts');
}
