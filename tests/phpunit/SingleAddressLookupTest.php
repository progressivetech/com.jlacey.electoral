<?php

use CRM_Electoral_ExtensionUtil as E;
use Civi\Test\HeadlessInterface;
use Civi\Test\HookInterface;
use Civi\Test\TransactionalInterface;

/**
 * Test sinlge address lookup against all providers.
 *
 * @group headless
 */
class SingleAddressLookupTest extends \PHPUnit\Framework\TestCase implements HeadlessInterface, HookInterface, TransactionalInterface {

  protected $addressId;
  protected $contactId;

  public function setUpHeadless() {
    return \Civi\Test::headless()
      ->installMe(__DIR__)
      ->apply();
  }

  public function setUp(): void {
    parent::setUp();
    // Add a contact.
    $this->contactId = \Civi\Api4\Contact::create()
      ->addValue('contact_type', 'Individual')
      ->addValue('first_name', 'Maria')
      ->addValue('middle_name', 'Q')
      ->addValue('last_name', 'Voter')
      ->addChain('create_address', \Civi\Api4\Address::create()->setValues(['contact_id' => '$id', 'street_address' => '431 Park Pl', 'city' => 'Brooklyn', 'state_province_id' => '1031', 'postal_code' => '11238', 'country_id' => 1228]))
      ->execute()->first()['id'];

    // Record the address id.
    $this->addressId = \Civi\Api4\Address::get()
      ->addSelect('id')
      ->addWhere('contact_id', '=', $this->contactId)
      ->execute()->first()['id'];
  }

  public function tearDown(): void {
    \Civi\Api4\Contact::delete()
      ->addWhere('id', '=', $this->contactId)
      ->execute();
    parent::tearDown();
  }

  /**
   * Test single address lookup for open states.
   */
  public function testOpenStates() {
    // We don't actually do an OpenStates lookup, but we have to have a
    // key or we get an error.
    \Civi\Api4\Setting::set()
      ->addValue('openstatesAPIKey', 'foo123')
      ->execute();
    \Civi\Api4\Setting::set()
      ->addValue('electoralApiDistrictTypes', ['country', 'administrativeArea1'])
      ->execute();

    // Create a mock guzzle client, specify exactly the response we
    // should get from running a real query against Open States.
    $mock = new \GuzzleHttp\Handler\MockHandler([
      new \GuzzleHttp\Psr7\Response(200, [], $this->OpenstatesJsonResults()),
    ]);

    $handlerStack = \GuzzleHttp\HandlerStack::create($mock);
    $client = new \GuzzleHttp\Client(['handler' => $handlerStack]);

    $os = new Civi\Electoral\Api\Openstates();
    $os->setGuzzleClient($client);
    $os->setGeocodeProviderClass('mockGeocodeProviderClass');
    $os->singleAddressLookup($this->addressId);
    $districts = \Civi\Api4\Contact::get()
        ->addSelect('electoral_districts.*')
        ->addWhere('id', '=', $this->contactId)
        ->execute();
    $this->assertEquals($districts->count(), 3);
    foreach($districts as $district) {
      if ($district['electoral_districts.electoral_level'] == 'administrativeArea1') {
        if ($district['electoral_districts.electoral_chamber'] == 'lower') {
          $this->assertEquals($district['electoral_districts.electoral_district'], 57);
        }
        else if ($district['electoral_districts.electoral_chamber'] == 'upper') {
          $this->assertEquals($district['electoral_districts.electoral_district'], 20);
        }
      }
      else if ($district['electoral_districts.electoral_level'] == 'country') {
        $this->assertEquals($district['electoral_districts.electoral_district'], 'NY-9');
      }
    }

  }

  protected function OpenstatesJsonResults() {
    return '{"results":[{"id":"ocd-person/8683c8ab-0d6c-471f-9259-a1b506109912","name":"Phara Souffrant Forrest","party":"Democratic","current_role":{"title":"Assembly Member","org_classification":"lower","district":"57","division_id":"ocd-division/country:us/state:ny/sldl:57"},"jurisdiction":{"id":"ocd-jurisdiction/country:us/state:ny/government","name":"New York","classification":"state"},"given_name":"","family_name":"","image":"https://assembly.state.ny.us/write/upload/member_files/057/headshot/057.jpg","email":"souffrantforrestp@nyassembly.gov","gender":"","birth_date":"","death_date":"","extras":{},"created_at":"2021-01-09T00:14:50.467984+00:00","updated_at":"2022-02-19T01:08:20.373856+00:00","openstates_url":"https://openstates.org/person/phara-souffrant-forrest-45pCE4KIyx6HyKZYyDq7so/"},{"id":"ocd-person/969d3e67-5686-5b4c-a8fe-5ec702a7343d","name":"Yvette D. Clarke","party":"Democratic","current_role":{"title":"Representative","org_classification":"lower","district":"NY-9","division_id":"ocd-division/country:us/state:ny/cd:9"},"jurisdiction":{"id":"ocd-jurisdiction/country:us/government","name":"United States","classification":"country"},"given_name":"Yvette","family_name":"Clarke","image":"https://theunitedstates.io/images/congress/450x550/C001067.jpg","email":"","gender":"F","birth_date":"1964-11-21","death_date":"","extras":{},"created_at":"2021-05-10T15:10:40.897825+00:00","updated_at":"2022-01-04T03:07:19.608798+00:00","openstates_url":"https://openstates.org/person/yvette-d-clarke-4aChztG65qAUrvrrvBBZRl/"},{"id":"ocd-person/52c0e400-2385-4c12-8bf4-6b9205c18eb2","name":"Zellnor Myrie","party":"Democratic","current_role":{"title":"Senator","org_classification":"upper","district":"20","division_id":"ocd-division/country:us/state:ny/sldu:20"},"jurisdiction":{"id":"ocd-jurisdiction/country:us/state:ny/government","name":"New York","classification":"state"},"given_name":"Zellnor","family_name":"Myrie","image":"https://www.nysenate.gov/sites/default/files/styles/160x160/public/zmyriemainheadimage_0.jpg?itok=O2-EZTeD","email":"myrie@nysenate.gov","gender":"","birth_date":"","death_date":"","extras":{},"created_at":"2019-01-13T06:15:42.453689+00:00","updated_at":"2021-10-08T14:55:28.147689+00:00","openstates_url":"https://openstates.org/person/zellnor-myrie-2W9WMrIZovzFCgYZe7bmza/"}],"pagination":{"per_page":10,"page":1,"max_page":1,"total_items":3}}';
  }
}

class mockGeocodeProviderClass {
  public static function format(&$params) {
    $params['geo_code_1'] = '40.67650';
    $params['geo_code_2'] = '-73.96918';
    return TRUE;
  }
}
