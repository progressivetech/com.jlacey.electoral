<?php
use CRM_Electoral_ExtensionUtil as E;

/**
 * Electoral.Runjobs API
 *
 * @see civicrm_api3_create_success
 *
 * @throws API_Exception
 */
function civicrm_api3_electoral_Runjobs(array $params) : array {
  $result = \Civi\Api4\Electoral::RunJobs()
    ->execute();

  return civicrm_api3_create_success($returnValues, $params, 'Electoral', 'Runjobs');
}
