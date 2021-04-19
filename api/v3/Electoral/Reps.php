<?php
use CRM_Electoral_ExtensionUtil as E;

/**
 * Electoral.Reps API
 *
 * @param array $params
 *
 * @return array
 *   API result descriptor
 *
 * @see civicrm_api3_create_success
 *
 * @throws API_Exception
 */
function civicrm_api3_electoral_Reps($params) {
  try {
    $EnabledProviders = \Civi::settings()->get('electoralApiProviders');
    foreach ($EnabledProviders as $enabledProvider) {
      $className = \Civi\Api4\OptionValue::get(FALSE)
        ->addSelect('name')
        ->addWhere('option_group_id:name', '=', 'electoral_api_data_providers')
        ->addWhere('value', '=', $enabledProvider)
        ->execute()
        ->column('name')[0];
      $provider = new $className();
      $returnValues = $provider->reps();
      $returnValues = "hey";
    }

    return civicrm_api3_create_success($returnValues, $params, 'Electoral', 'Districts');
  }
  catch (API_Exception $e) {
    throw $e;
  }
}
