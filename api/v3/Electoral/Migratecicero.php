<?php
use CRM_Electoral_ExtensionUtil as E;

/**
 * Electoral.MigrateCicero API
 *
 * Migrate data from the old civicrm_cicero Drupal module to
 * this extension.
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
function civicrm_api3_electoral_Migratecicero($params) {
  // Make sure we have some required configuration information.
  if (!function_exists('variable_get')) {
    throw new \API_Exception(E::ts("variable_get does not seem to be a function. Is this a Drupal site?"));
  }

  // Add cicero as a provider if it's not already set.
  $electoralApiProviders = \Civi\Api4\Setting::get()
    ->addSelect('electoralApiProviders:name')
    ->execute()->first()['value'];

  if (!in_array('\Civi\Electoral\Api\Cicero', $electoralApiProviders)) {
    $electoralApiProviders[] = '\Civi\Electoral\Api\Cicero';
    \Civi\Api4\Setting::set()
      ->addValue('electoralApiProviders:name', $electoralApiProviders)
      ->execute();
  }

  // Migrate the cicero key if it's not already set.
  $electoralCiceroKey = \Civi\Api4\Setting::get()
    ->addSelect('ciceroAPIKey')
    ->execute()->first()['ciceroApiKey'];

  if (empty($electoralCiceroKey)) {
    // Check for Drupal module settings.
    $drupalCiceroKey = variable_get('civicrm_cicero_api_key');
    if ($drupalCiceroKey) {
      \Civi\Api4\Setting::set()
        ->addValue('ciceroAPIKey', $drupalCiceroKey)
        ->execute();
    }

  }
  $drupalLocationTypes = variable_get('civicrm_cicero_location_types', civicrm_cicero_get_default_location_types());
  $drupalFirstLocationType = array_shift(explode("\n", $drupalLocationTypes));

  if ($drupalFirstLocationType != 'Home') {
    // Set to alternative location type
    \Civi\Api4\Setting::set()
      ->addValue('addressLocationType:name', trim($drupalFirstLocationType))
      ->execute();
  }

  // We are only migrating the following cicero fields:
  $migrate = [
    'LOCAL',
    'NATIONAL_LOWER',
    'STATE_LOWER',
    'STATE_UPPER',
  ];
  // Get the mapping of cicero fields to CiviCRM custom fields.
  $original_cicero_map = variable_get('civicrm_cicero_contact_field_map');

  if (empty($original_cicero_map)) {
    throw new \API_Exception(E::ts("Failed to retrieve the cicero contact field map. Is civicrm_cicero Drupal module in use on this site?"));
  }

  // Make a new array of only the fields we will migrate.
  $cicero_map = [];
  foreach ($original_cicero_map as $key => $value) {
    if (in_array($key, $migrate)) {
      $cicero_map[$key] = $value;
    }
  }

  $contacts_updated = 0;
  $contacts_skipped = 0;

  // Get the last modified field id.
  $last_modified_field = variable_get('civicrm_cicero_last_updated_field');

  // Build an API3 query to get all results. I've found sites in which the custom voter fields
  // don't have names, so we can't use Apiv4.
  $contact_params = [];
  // Return all cicero fields plus the last modified field.
  $return = array_values($cicero_map);
  $return[] = $last_modified_field;
  $contact_params["return"] = $return;

  // We try to be indempotent. Collect a list of contacts that already
  // have the new cicero fields and we will exclude them.
  $converted = \Civi\Api4\CustomValue::get('electoral_districts')
    ->addSelect('entity_id')
    ->execute();

  $converted_contact_ids = [];
  foreach($converted as $converted_contact_id) {
    $converted_contact_ids[] = $converted_contact_id['entity_id'];
  }

  // Optionally, limit to just one contact.
  $contact_id = NULL;
  if (isset($params['contact_id'])) {
    $contact_id = $params['contact_id'];
    if (in_array($contact_id, $converted_contact_ids)) {
      $contacts_skipped++;
      $returnValues['contacts_skipped'] = $contacts_skipped; 
      return civicrm_api3_create_success($returnValues, $params, 'Electoral', 'Districts');
    }
  }

  // Here we exclude already converted contacts.
  if (count($converted_contact_ids) > 0) {
    $contact_params['id'] = ['NOT IN' => $converted_contact_ids ];
  }

  // Only return records with a last modified date.
  $contact_params[$last_modified_field] = ["IS NOT NULL" => 1];

  if ($contact_id) {
    $contact_params['id'] = $contact_id;
  }
  $contact_params['options'] = ['limit' => 0];

  $results = civicrm_api3('Contact', 'get', $contact_params);
 
  // Optioanlly, limit to a fixed number of contacts.
  $limit = NULL;
  if (isset($params['limit'])) {
    $limit = $params['limit'];
  }

  $addressLocationTypeId = \Civi\Api4\Setting::get()
    ->addSelect('addressLocationType')
    ->execute()->first()['value'];

  foreach($results['values'] as $contact) {
    // We need the city and state. One more query.
    $address = \Civi\Api4\Address::get()
      ->addSelect("city")
      ->addSelect("state_province_id")
      ->addWhere('contact_id', '=', $contact['id'])
      ->addWhere('location_type_id', '=', $addressLocationTypeId)
      ->execute()->first();

    $state_province_id = $address['state_province_id'];
    $city = $address['city'];

    // The new data is stored in a multiple values custom field, so we run an separate
    // update process for each field for this record.
    $update_contact = NULL;
    foreach ($cicero_map as $cicero_name => $field_name) {
      if (empty($contact[$field_name])) {
        // If the field is empty, move on.
        continue;
      }
      $update_contact = \Civi\Api4\Contact::update()
        ->addWhere('id', '=', $contact['id'])
        ->addValue('electoral_districts.electoral_district', $contact[$field_name])
        ->addValue('electoral_districts.electoral_modified_date', $contact[$last_modified_field])
        ->addValue('electoral_districts.electoral_states_provinces', $state_province_id);
      switch($cicero_name) {
        case 'LOCAL':
          $update_contact->addValue('electoral_districts.electoral_level', 'locality')
            ->addValue('electoral_districts.electoral_cities', $city);
          break;
        case 'NATIONAL_LOWER':
          $update_contact->addValue('electoral_districts.electoral_level', 'country');
          $update_contact->addValue('electoral_districts.electoral_chamber', 'lower');
          break;
        case 'STATE_LOWER':
          $update_contact->addValue('electoral_districts.electoral_level', 'administrativeArea1');
          $update_contact->addValue('electoral_districts.electoral_chamber', 'lower');
          break;
        case 'STATE_UPPER':
          $update_contact->addValue('electoral_districts.electoral_level', 'administrativeArea1');
          $update_contact->addValue('electoral_districts.electoral_chamber', 'upper');
          break;
        default:
          // We are only migrating these four main mappings. All others get skipped. 
          continue 2;
      }
      if ($update_contact) {
        $update_contact->execute();
      }
    }
    if ($update_contact) {
      $contacts_updated++;
      if ($limit) {
        if ($contacts_updated >= $limit) {
          break;
        }
      }
    }
    else {
      $contacts_skipped++;
    }
  }
  $returnValues['contacts_updated'] = $contacts_updated; 
  $returnValues['contacts_skipped'] = $contacts_skipped; 
  return civicrm_api3_create_success($returnValues, $params, 'Electoral', 'Districts');
}
