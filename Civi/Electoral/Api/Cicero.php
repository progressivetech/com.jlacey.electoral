<?php

namespace Civi\Electoral\Api;

use GuzzleHttp\Client;

class Cicero extends \Civi\Electoral\AbstractApi {

  public function districts() {
    // Get the addresses.
    $addresses = $this->getAddresses();
    return $addresses;
  }

  public function reps() {
    // Reps code here.
  }

  /**
   * @inheritDoc
   */
  protected function addressDistrictLookup(array $address) {
    $district_types = variable_get('civicrm_cicero_district_types', NULL);
    if (is_null($district_types)) {
      $cicero_district_types = civicrm_cicero_get_response(CIVICRM_CICERO_DISTRICT_QUERY_URL);
      if(FALSE === $cicero_district_types) {
        civicrm_cicero_log("Failed to retrieve the district types.");
        return FALSE;
      }
      $district_types = $cicero_district_types->response->results->district_types;
      variable_set('civicrm_cicero_district_types', $district_types);
    }
    // Get the map, keyed to the cicero name of the field, with the civicrm
    // name as the value.
    $req_fields = variable_get('civicrm_cicero_contact_field_map', NULL);
    if(!$force) {
      $saved_values = array();
      // Lookup the existing values for these fields. If we already have a value
      // we will skip the lookup.
      // Get an array of civicrm field names to return.
      $return = array_values($req_fields);
      $params = array('id' => $contact_id, 'return' => $return);
      $result = civicrm_api3('Contact', 'get', $params);
      if($result['is_error'] == 0) {
        $contact = array_pop($result['values']);
        // We want $saved_values to be keyed to the civi name of the field.
        $req_fields_keyed_to_civicrm_field_name = array_flip($req_fields);
        while(list(,$field) = each($return)) {
          $cicero_field = $req_fields_keyed_to_civicrm_field_name[$field];
          $saved_values[$cicero_field] = $contact[$field];
        }
      }
    }
    $legislative = FALSE;
    $legislative_noncurrent = FALSE;
    $non_legislative = FALSE;
    $non_leg_types = array();
    foreach ($district_types as $key => $district) {
      if ($all_fields || array_key_exists($district->name_short, $req_fields)) {
        if($force || empty($saved_values[$district->name_short])) {
          if (!$district->is_legislative) {
            $non_legislative = TRUE;
            $non_leg_types[] = $district->name_short;
          }
          else {
            // Cicero demands a different query if you are looking for current
            // vs new or previous district info.
            // Fixme: this is not generalized - only works for 2010 census
            // re-districting.
            if(preg_match('/_2010$/', $district->name_short)) {
              $legislative_noncurrent = TRUE;
            } else {
              $legislative = TRUE;
            }
          }
        }
      }
    }
    $result = civicrm_cicero_get_contact_address($contact_id);
    $response = array();
    if (civicrm_cicero_address_is_complete_enough($result)) {
      $query_string = civicrm_cicero_query_string_for_address($result);
      if ($query_string) {
        if ($legislative_noncurrent) {
          $url = CIVICRM_CICERO_LEGISLATIVE_QUERY_URL . $query_string .
            '&type=ALL_2010';
          $resp_obj = civicrm_cicero_get_response($url);
          if(FALSE === $resp_obj) {
            civicrm_cicero_log(t("Failed to obtain legislative non-current response. Continuing..."));
          }
          else {
            $response['legislative'][] = $resp_obj;
          }
        }
        if ($legislative) {
          $url = CIVICRM_CICERO_LEGISLATIVE_QUERY_URL . $query_string;
          $resp_obj = civicrm_cicero_get_response($url);
          if(FALSE === $resp_obj) {
            civicrm_cicero_log(t("Failed to obtain legislative current response. Continuing..."));
          }
          else {
            $response['legislative'][] = $resp_obj;
          }
        }
        if ($non_legislative) {
          while(list(,$type) = each($non_leg_types)) {
            $url = CIVICRM_CICERO_NONLEGISLATIVE_QUERY_URL . $query_string .
              '&type=' . $type;
          }
          $resp_obj = civicrm_cicero_get_response($url);
          if(FALSE === $resp_obj) {
            civicrm_cicero_log(t("Failed to obtain non-legislative response. Continuing..."));
          }
          else {
            $response['nonlegislative'][] = $resp_obj;
          }

        }
        return $response;
      }
    }
    else {
      civicrm_cicero_log(t("Failed to find enough address parameters to justify a lookup."));
      return FALSE;
    }
    return $response;
  }
  
}
