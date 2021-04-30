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

  $mapping = variable_get('civicrm_cicero_contact_field_map');

  if (empty($mapping)) {
    throw new \API_Exception(E::ts("Failed to retrieve the cicero contact field map. Is civicrm_cicero Drupal module in use on this site?"));
  }

  // Optionally, limit to just one contact.
  $contact_id = NULL;
  if (isset($params['contact_id'])) {
    $contact_id = $params['contact_id'];
  }

  // Optioanlly, limit to a fixed number of contacts.
  $limit = NULL;
  if (isset($params['limit'])) {
    $limit = $params['limit'];
  }

  // Get the last modified field id and convert to field name for use with Api4.
  $last_modified_field = variable_get('civicrm_cicero_last_updated_field');
  $last_modified_field_id = str_replace('custom_', '', $last_modified_field);
  $last_modified = \Civi\Api4\CustomField::get()
      ->addSelect('name')
      ->addSelect('custom_group.name')
      ->addWhere('id', '=', $last_modified_field_id)
      ->execute()
      ->first();
  $last_modified_field_name = $last_modified['custom_group.name'] . "." . $last_modified['name'];

  // The mapping variable references the Cicero field to the custom CiviCRM
  // field using the custom_NNN syntax. However, we need to the field name so
  // we can use apiv4.
  $api4_mapping = [];
  foreach ($mapping as $cicero_name => $custom_field) {
    // For each field mapping, get the field name.
    $field_id = str_replace('custom_', '', $custom_field);
    $field = \Civi\Api4\CustomField::get()
      ->addSelect('name')
      ->addSelect('custom_group.name')
      ->addWhere('id', '=', $field_id)
      ->execute()
      ->first();
    $api4_mapping[$cicero_name] = $field['custom_group.name'] . '.' . $field['name'];
  }

  // Build the query to find old cicero data in the database.
  $contacts = \Civi\Api4\Contact::get();
  $contacts->setJoin([
    ['Address AS address', TRUE, NULL],
  ]);
  $contacts->addWhere('address.location_type_id:name', '=', 'Home' );
  $contacts->addSelect('address.state_province_id')
    ->addSelect('address.city')
    ->addSelect($last_modified_field_name);

  // Add each field that we are currently configured to use.
  foreach ($api4_mapping as $cicero_name => $field_name) {
    $contacts->addSelect($field_name);
  }

  if ($contact_id) {
    $contacts->addWhere('id', '=', 24683);
  }
  $contacts_updated = 0;
  $contacts_skipped = 0;

  // Iterate over all records in the database with a value.
  $results = $contacts->execute();
  foreach($results as $contact) {
    // We try to be indempotent. If a record already has the new style cicero
    // fields, then we skip to avoid adding duplicate fields.
    $existing = \Civi\Api4\CustomValue::get('electoral_districts')
      ->addWhere('entity_id', '=', $contact['id'])
      ->execute();
    if ($existing->count() > 0) {
      $contacts_skipped++;
      continue;
    }

    // The new data is stored in a multiple values custom field, so we run an separate
    // update process for each field for this record.
    $update_contact = NULL;
    foreach ($api4_mapping as $cicero_name => $field_name) {
      if (empty($contact[$field_name])) {
        // If the field is empty, move on.
        continue;
      }
      $update_contact = \Civi\Api4\Contact::update()
        ->addWhere('id', '=', $contact['id'])
        ->addValue('electoral_districts.electoral_district', $contact[$field_name])
        ->addValue('electoral_districts.electoral_modified_date', $contact[$last_modified_field_name])
        ->addValue('electoral_districts.electoral_states_provinces', $contact['address.state_province_id']);
      switch($cicero_name) {
        case 'LOCAL':
          $update_contact->addValue('electoral_districts.electoral_level', 'locality')
            ->addValue('electoral_districts.electoral_cities', $contact['address.city']);
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
