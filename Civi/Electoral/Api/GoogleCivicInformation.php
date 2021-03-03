<?php

namespace Civi\Electoral\Api;

use CRM_Electoral_ExtensionUtil as E;

// use \GuzzleHttp\Client;

/**
 * Cicero Data Provider class.
 */
class GoogleCivicInformation extends \Civi\Electoral\AbstractApi {

  /**
   * @inheritDoc
   */
  protected function getApiKey() : string {
    $key = \Civi::settings()->get('googleCivicInformationAPIKey');
    if (!$key) {
      throw new \Exception('Google Civic Information API Key is not set.');
    }
    return $key;
  }

  public function reps() {
    // Reps code here.
  }

  /**
   * @inheritDoc
   */
  protected function addressDistrictLookup() : array {
    // Assemble the API URL.
    $streetAddress = rawurlencode($this->address['street_address']);
    $city = rawurlencode($this->address['city']);
    $stateProvinceAbbrev = $this->address['state_province.abbreviation'];
    $apiKey = $this->getApiKey();
    $url = "https://www.googleapis.com/civicinfo/v2/representatives?address=$streetAddress%20$city%20$stateProvinceAbbrev&key=$apiKey";

    \Civi::log()->debug("Contacting Google Civic API with url: {$url}.", ['electoral']);
    $guzzleClient = new \GuzzleHttp\Client();
    $json = $guzzleClient->request('GET', $url)->getBody()->getContents();
    $result = $json ? json_decode($json, TRUE) : [];
    return $result;
  }

  /**
   * Convert the Cicero raw data to the format writeDistrictData expects and write it.
   */
  protected function parseDistrictData(array $districts) : bool {
    // Check for errors first.
    if (isset($districts['error'])) {
      $this->writeElectoralStatus($districts, $this->address['id']);
      return FALSE;
    }

    $chamber = $cityName = $county = NULL;

    // Sort the divisions by length.  Shortest is country, second-shortest is administrativeArea1 (state/province).
    $divisions = array_keys($districts['divisions']);
    usort($divisions, 'electoral_division_sort');
    $administrativeArea1DivisionId = $divisions[1];

    // Ideally we could break this out into a subextension to better handle non-US locations
    $districtMatches = [
      'cd' => [
        'level' => 'country',
        'chamber' => 'lower',
        'replace' => "$administrativeArea1DivisionId/cd:",
      ],
      'sldu' => [
        'level' => 'administrativeArea1',
        'chamber' => 'upper',
        'replace' => "$administrativeArea1DivisionId/sldu:",
      ],
      'sldl' => [
        'level' => 'administrativeArea1',
        'chamber' => 'lower',
        'replace' => "$administrativeArea1DivisionId/sldl:",
      ],
    ];
    // This next part is US-centric.  Conceivably we could determine this programmatically similar to county/local.
    // Country and state lookup.
    foreach ($districts['divisions'] as $divisionKey => $division) {
      $level = $chamber = $district = $county = $cityName = NULL;
      foreach ($districtMatches as $districtData) {
        if (strpos($divisionKey, $districtData['replace']) === 0) {
          $district = (int) str_replace($districtData['replace'], '', $divisionKey);
          $level = $districtData['level'];
          $chamber = $districtData['chamber'];
          break;
        }
      }
      // Sub-state divisions
      if (!$level && strpos($divisionKey, "$administrativeArea1DivisionId/") === 0) {
        $subdivisionId = str_replace("$administrativeArea1DivisionId/", '', $divisionKey);
        // If there's no slash in the subdivision ID, this is administrativeArea2
        if (strpos($subdivisionId, '/') === FALSE && $subdivisionId) {
          $district = explode(':', $subdivisionId)[1];
          $level = 'administrativeArea2';
          $county = $division['name'];
        }
        // locality
        if (strpos($subdivisionId, '/') !== FALSE && $subdivisionId) {
          $district = explode(':', $subdivisionId)[2];
          $level = 'locality';
          $cityName = $division['name'];
        }
      }

      // Write to db.
      if ($level) {
        $this->writeDistrictData($this->address['contact_id'], $level, $this->address['state_province_id'], $county, $cityName, $chamber, $district, 0);
      }
    }
    return TRUE;
  }

  /**
   * Function to sort divisions by length to determine their level.
   */
  private function electoral_division_sort(string $a, string $b) {
    return strlen($a) - strlen($b);
  }

}
