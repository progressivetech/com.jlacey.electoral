<?php

use CRM_Electoral_ExtensionUtil as E;
use Civi\Test\HeadlessInterface;
use Civi\Test\HookInterface;
use Civi\Test\TransactionalInterface;

/**
 * Mock Geocode Provider class
 *
 * Used to avoid network lookups while testing.
 */
class mockGeocodeProviderClass {
  public static function format(&$params) {
    // We don't want to make any network calls, so simulate a geo coding call.
    // Take an address and return geo coordinates. Ensure it is the same geo
    // coordinates for the same address.
    $hash1 = md5($params['street_address'] . ' ' . $params['city']);
    $hash2 = md5($params['country'] . $params['postal_code'] . $params['street_address']);
    $params['geo_code_1'] = crc32($hash1);
    $params['geo_code_2'] = crc32($hash2);
    return TRUE;
  }
}

/**
 * Test sinlge address lookup against all providers.
 *
 * @group headless
 */
class DistrictJobTest extends \PHPUnit\Framework\TestCase implements HeadlessInterface, HookInterface, TransactionalInterface {

  protected $addressId;
  protected $contactId;
  protected $contactIds = [];

  public function setUpHeadless() {
    return \Civi\Test::headless()
      ->installMe(__DIR__)
      ->apply();
  }

  public function setUp(): void {
    parent::setUp();
  }

  protected function createContact($firstName, $lastName, $address) {
    // Add a contact.
    $this->contactId = \Civi\Api4\Contact::create()
      ->addValue('contact_type', 'Individual')
      ->addValue('first_name', $firstName)
      ->addValue('last_name', $lastName)
      ->execute()->first()['id'];

    // Collect these for deletion.
    $this->contactIds[] = $this->contactId;

    // We use the parseAddress convenience function to break out
    // our string based address into pieces.
    $address = electoral_parse_address($address);
    $this->addressId = \Civi\Api4\Address::create()
      ->addValue('contact_id', $this->contactId)
      ->addValue('location_type_id.name', 'Home')
      ->addValue('street_address', $address['street_address'])
      ->addvalue('city', $address['city'])
      ->addValue('state_province_id.abbreviation', $address['state_province'])
      ->addValue('postal_code', $address['postal_code'])
      ->execute()->first()['id'];
  }

  public function tearDown(): void {
    foreach ($this->contactIds as $contactId) {
      \Civi\Api4\Contact::delete()
        ->addWhere('id', '=', $contactId)
        ->execute();
    }
    parent::tearDown();
  }

  /**
   * Test single address lookup for open states.
   */
  public function testOpenstates() {
    // We don't actually do an OpenStates lookup, but we have to have a
    // key or we get an error.
    \Civi\Api4\Setting::set()
      ->addValue('openstatesAPIKey', 'foo123')
      ->execute();
    \Civi\Api4\Setting::set()
      ->addValue('electoralApiDistrictTypes', ['country', 'administrativeArea1'])
      ->execute();
    
    $testData = [
      [
        'file' => 'data/openstates/ny.json',
        'first_name' => 'Newyork',
        'last_name' => 'McOpenstates',
        'address' => "431 Park Place, Brooklyn, NY, 11238",
        'districts_count' => 3,
        'districts' => [
          'administrativeArea1' => [
              'upper' => 20,
              'lower' => 57,
          ],
          'country' => [
            'lower' => 'NY-9'
          ],
        ],
      ],
      [
        'file' => 'data/openstates/ne.json',
        'first_name' => 'Nebraska',
        'last_name' => 'McOpenstates',
        'address' => '2707 Royal Ct, Lincoln, NE, 68502',
        'districts_count' => 2,
        'districts' => [
          'administrativeArea1' => [
              'upper' => 29,
          ],
          'country' => [
            'lower' => 'NE-1',
          ],
        ],
      ],
      [
        'file' => 'data/openstates/ca.json',
        'first_name' => 'Callie',
        'last_name' => 'McOpenstates',
        'address' => "2637 Dorking Place, Santa Barbara, CA, 93105",
        'districts_count' => 3,
        'districts' => [
          'administrativeArea1' => [
              'upper' => 19,
              'lower' => 37,
          ],
          'country' => [
            'lower' => 'CA-24',
          ],
        ],
      ],
    ];
    $this->checkData($testData, 'OpenStates');
  }

  /**
   * Test single address lookup for google civic.
   */
  public function testGoogleCivic() {
    \Civi\Api4\Setting::set()
      ->addValue('googleCivicInformationAPIKey', 'foo123')
      ->execute();
    \Civi\Api4\Setting::set()
      ->addValue('electoralApiDistrictTypes', ['country', 'administrativeArea1', 'administrativeArea2', 'county', 'city'])
      ->execute();
    $testData = [
      [
        'file' => 'data/googlecivic/ne.json',
        'first_name' => 'Nebraska',
        'last_name' => 'McGoogle',
        'address' => '2707 Royal Ct, Lincoln, NE, 68502',
        'districts_count' => 4,
        'districts' => [
          'administrativeArea1' => [
              'upper' => 29,
          ],
          'administrativeArea2' => [
              'lower' => 4,
          ],
          'country' => [
            'upper' => 'ne',
            'lower' => 1,
          ],
        ],
      ],
      [
        'file' => 'data/googlecivic/ny.json',
        'first_name' => 'Newyork',
        'last_name' => 'McGoogle',
        'address' => "431 Park Place, Brooklyn, NY, 11238",
        'districts_count' => 4,
        'districts' => [
          'administrativeArea1' => [
              'upper' => 20,
              'lower' => 57,
          ],
          'country' => [
            'upper' => 'ny',
            'lower' => 9 
          ],
        ],
      ],
      [
        'file' => 'data/googlecivic/ca.json',
        'first_name' => 'Callie',
        'last_name' => 'McGoogle',
        'address' => "2637 Dorking Place, Santa Barbara, CA, 93105",
        'districts_count' => 5,
        'districts' => [
          'administrativeArea1' => [
              'upper' => 19,
              'lower' => 37,
          ],
          'country' => [
            'upper' => 'ca',
            'lower' => 24,
          ],
          'administrativeArea2' => [
              'lower' => 1,
          ],
        ],
      ],
    ];
    $this->checkData($testData, 'GoogleCivic');
  }

  /**
   * Test single address lookup for google civic.
   */
  public function testCicero() {
    \Civi\Api4\Setting::set()
      ->addValue('ciceroAPIKey', 'foo123')
      ->execute();
    \Civi\Api4\Setting::set()
      ->addValue('electoralApiDistrictTypes', ['country', 'administrativeArea1', 'administrativeArea2', 'locality' ])
      ->execute();
    $testData = [
      [
        'file' => 'data/cicero/ny.json',
        'first_name' => 'Newyork',
        'last_name' => 'McCicero',
        'address' => "431 Park Place, Brooklyn, NY, 11238",
        'districts_count' => 5,
        'districts' => [
          'administrativeArea1' => [
              'upper' => 20,
              'lower' => 44,
          ],
          'country' => [
            'upper' => 'NY',
            'lower' => 10,
          ],
          'locality' => [
            'lower' => 35,
          ]
        ],
      ],
      [
        'file' => 'data/cicero/ne.json',
        'first_name' => 'Nebraska',
        'last_name' => 'McCicero',
        'address' => '2707 Royal Ct, Lincoln, NE, 68502',
        'districts_count' => 4,
        'districts' => [
          'administrativeArea1' => [
              'upper' => 29,
          ],
          'country' => [
            'upper' => 'NE',
            'lower' => 1,
          ],
          // Lincoln returns four city council members, three at large
          // and one in district 3. We should only return district info
          // for actual districts, not the at large ones. 
          'locality' => [ 
            'lower' => 3,
          ],
        ],
      ],
      [
        'file' => 'data/cicero/ca.json',
        'first_name' => 'Callie',
        'last_name' => 'McCicero',
        'address' => "2637 Dorking Place, Santa Barbara, CA, 93105",
        'districts_count' => 4,
        'districts' => [
          'administrativeArea1' => [
              'upper' => 19,
              'lower' => 37,
          ],
          'country' => [
            'lower' => 24,
            'upper' => 'CA',
          ],
        ],
      ],
      [
        'file' => 'data/cicero/mo.json',
        'first_name' => 'Mo',
        'last_name' => 'McCicero',
        'address' => "1505 Pinetree Ln, St. Louis, MO, 63119",
        'districts_count' => 5,
        'districts' => [
          'administrativeArea2' => [
              'lower' => 5,
          ],
          'administrativeArea1' => [
              'upper' => 1,
              'lower' => 91,
          ],
          'country' => [
            'lower' => 2,
            'upper' => 'MO',
          ],
        ],
      ],
    ];
    $this->checkData($testData, 'Cicero');
  }
   
  private function checkData($testData, $name) {
    // Build an array of values to pass to the districting code to override
    // an actual lookup and instead use our test data.
    $testReplacementMap = [];

    foreach($testData as $key => $expected) {
      // Create each contact.
      $this->createContact($expected['first_name'], $expected['last_name'], $expected['address']);

      // Add the contactId so we can accurately test if this contactId
      // has the correct districts attached when we are done.
      $testData[$key]['contact_id'] = $this->contactId;

      // Create a mock guzzle client, specify exactly the response we
      // should get from running a real query.
      $json = file_get_contents(__DIR__ . '/' . $expected['file']);
      $response = new \GuzzleHttp\Psr7\Response(200, [], $json);
      $mock = new \GuzzleHttp\Handler\MockHandler([$response]);
      $handlerStack = \GuzzleHttp\HandlerStack::create($mock);
      $guzzleClient = new \GuzzleHttp\Client(['handler' => $handlerStack]);

      // This array will be passed to the RunJobs API to ensure we "mock" the
      // execution to avoid real lookups.
      $testReplacementMap[$this->contactId] = [
        'guzzle_client' => $guzzleClient,
        'api_provider' => $name,
      ];
    }

    // Create a district job that will district all these contacts.
    $districtJobId = \Civi\Api4\DistrictJob::create()
      ->addValue('contact_ids', serialize($this->contactIds))
      ->addValue('status', \CRM_Electoral_BAO_DistrictJob::STATUS_PENDING)
      ->addValue('offset', 0)
      ->execute()->first()['id'];

    // Execute the job.
    \Civi\Api4\Electoral::RunJobs()
      ->setTestReplacementMap($testReplacementMap)
      ->setGeocodeProviderClass('mockGeocodeProviderClass')
      ->execute();
   
    // Ensure the job status is set to completed as expected.
    $status = \Civi\Api4\DistrictJob::get()
      ->addWhere('id', '=', $districtJobId)
      ->addSelect('status')
      ->execute()->first()['status'];
    $this->assertEquals(\CRM_Electoral_BAO_DistrictJob::STATUS_COMPLETED, $status, "District Job status is not complete.");

    // Now test to see if we got the expected results for each contact.
    foreach($testData as $expected) {
      $districts = \Civi\Api4\Contact::get()
          ->addSelect('electoral_districts.*')
          ->addWhere('id', '=', $expected['contact_id'])
          ->execute();
      $identifier = $expected['file'];
      $this->assertEquals($expected['districts_count'], $districts->count(), "$identifier count of districts for " . $expected['first_name'] . ' ' . $expected['last_name']);
      
      foreach($districts as $district) {
        $level = $district['electoral_districts.electoral_level'];
        $chamber = $district['electoral_districts.electoral_chamber'];
        $districtId = $district['electoral_districts.electoral_district'];
        if ($chamber) {
          $value = $expected['districts'][$level][$chamber];
        }
        else {
          $value = $expected['districts'][$level];
        }
        if ($value) {
          $this->assertEquals($value, $districtId, $name);
        }
      }
    }
  }
}
