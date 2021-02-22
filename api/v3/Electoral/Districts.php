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
  try {
    $limit = (int) ($params['limit'] ?? 100);
    $update = (bool) ($params['update'] ?? FALSE);
    $EnabledProviders = \Civi::settings()->get('electoralApiProviders');
    foreach ($EnabledProviders as $enabledProvider) {
      $className = \Civi\Api4\OptionValue::get(FALSE)
        ->addSelect('name')
        ->addWhere('option_group_id:name', '=', 'electoral_api_data_providers')
        ->addWhere('value', '=', $enabledProvider)
        ->execute()
        ->column('name')[0];
      $provider = new $className($limit, $update);
      $returnValues[] = $provider->districts($limit, $update);
    }

    return civicrm_api3_create_success($returnValues, $params, 'Electoral', 'Districts');
  }
  catch (API_Exception $e) {
    throw $e;
  }

}
