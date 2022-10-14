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
    $params['geo_code_1'] = rand(1,100);
    $params['geo_code_2'] = -rand(1,100);
    return TRUE;
  }
}

/**
 * Test sinlge address lookup against all providers.
 *
 * @group headless
 */
class SingleAddressLookupTest extends \PHPUnit\Framework\TestCase implements HeadlessInterface, HookInterface, TransactionalInterface {

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

    // Configure to insert officials.
    \Civi\Api4\Setting::set()
      ->addValue('electoralApiCreateOfficialOnDistrictLookup', TRUE)
      ->execute();
    CRM_Core_ManagedEntities::singleton(TRUE)->reconcile();
    // cv('api system.flush');
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
        'officials' => [
          [ 'first_name' => 'Zellnor', 'last_name' => 'Myrie'],
          [ 'first_name' => 'Phara', 'last_name' => 'Forrest'],
          [ 'first_name' => 'Yvette', 'last_name' => 'Clarke'],
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
        'officials' => [
          [ 'first_name' => 'Eliot', 'last_name' => 'Bostar'],
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
        'officials' => [
          [ 'first_name' => 'Steve', 'last_name' => 'Bennett'],
          [ 'first_name' => 'Monique', 'last_name' => 'Limón'],
          [ 'first_name' => 'Salud', 'last_name' => 'Carbajal'],
        ],
      ],
    ];
    $this->checkData($testData, 'openstates');
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
        'officials' => [
          [ 'first_name' => 'Eliot', 'last_name' => 'Bostar'],
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
        'officials' => [
          [ 'first_name' => 'Zellnor', 'last_name' => 'Myrie'],
          [ 'first_name' => 'Phara', 'last_name' => 'Forrest'],
          [ 'first_name' => 'Yvette', 'last_name' => 'Clarke'],
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
        'officials' => [
          [ 'first_name' => 'Das', 'last_name' => 'Williams'],
          [ 'first_name' => 'Steve', 'last_name' => 'Bennett'],
          [ 'first_name' => 'Monique', 'last_name' => 'Limón'],
          [ 'first_name' => 'Salud', 'last_name' => 'Carbajal'],
        ],
      ],
    ];
    $this->checkData($testData, 'googlecivic');
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
              'lower' => 57,
          ],
          'country' => [
            'upper' => 'NY',
            'lower' => 9,
          ],
          'locality' => [
            'lower' => 35,
          ]
        ],
        'officials' => [
          [ 'first_name' => 'Zellnor', 'last_name' => 'Myrie'],
          [ 'first_name' => 'Phara', 'last_name' => 'Souffrant Forrest'],
          [ 'first_name' => 'Yvette', 'last_name' => 'Clarke'],
          [ 'first_name' => 'Crystal', 'last_name' => 'Hudson'],
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
          // and one in district 3. Ug. Just doesn't match our testing
          // framework. We only capture one of them.
          'locality' => [ 
            'lower' => 'At Large',
          ],
        ],
        'officials' => [
          [ 'first_name' => 'Eliot', 'last_name' => 'Bostar'],
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
        'officials' => [
          [ 'first_name' => 'Steve', 'last_name' => 'Bennett'],
          [ 'first_name' => 'S. Monique', 'last_name' => 'Limón'],
          [ 'first_name' => 'Salud', 'last_name' => 'Carbajal'],
        ],
      ],
    ];
    $this->checkData($testData, 'cicero');
  }
   
  private function checkData($testData, $name) {
    $class = NULL;
    if ($name == 'openstates') {
      $class = 'Civi\Electoral\Api\Openstates';
    }
    elseif ($name == 'cicero') {
      $class = 'Civi\Electoral\Api\Cicero';
    }
    elseif ($name == 'googlecivic') {
      $class = 'Civi\Electoral\Api\GoogleCivicInformation';
    }
    foreach($testData as $expected) {
      $this->createContact($expected['first_name'], $expected['last_name'], $expected['address']);
      // Create a mock guzzle client, specify exactly the response we
      // should get from running a real query against Open States.
      $json = file_get_contents(__DIR__ . '/' . $expected['file']);
      $mock = new \GuzzleHttp\Handler\MockHandler([
        new \GuzzleHttp\Psr7\Response(200, [], $json)
      ]);

      $handlerStack = \GuzzleHttp\HandlerStack::create($mock);
      $client = new \GuzzleHttp\Client(['handler' => $handlerStack]);

      $e = new $class(0, FALSE, TRUE);
      $e->setGuzzleClient($client);
      $e->setGeocodeProviderClass('mockGeocodeProviderClass');
      $e->processSingleAddress($this->addressId);
      $districts = \Civi\Api4\Contact::get()
          ->addSelect('electoral_districts.*')
          ->addWhere('id', '=', $this->contactId)
          ->execute();
      $identifier = $expected['file'];
      $this->assertEquals($expected['districts_count'], $districts->count(), "$identifier count of districts.");
      
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
      foreach ($expected['officials'] as $official) {
        $this->assertOfficialAdded($official['first_name'], $official['last_name'], $name);
      }
    }
  }
  protected function assertOfficialAdded($first_name, $last_name, $source): void {
    $official = \Civi\Api4\Contact::get()
      ->addWhere('contact_sub_type', '=', 'Official')
      ->addWhere('first_name', '=', $first_name)
      ->addWhere('last_name', '=', $last_name)
      ->execute();
    $this->assertEquals(1, $official->count(), "$first_name $last_name added as official via $source.");
  }


}
