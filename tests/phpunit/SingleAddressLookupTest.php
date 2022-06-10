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
    $params['geo_code_1'] = '40.67650';
    $params['geo_code_2'] = '-73.96918';
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

    // Create a mock guzzle client, specify exactly the response we
    // should get from running a real query.
    $mock = new \GuzzleHttp\Handler\MockHandler([
      new \GuzzleHttp\Psr7\Response(200, [], $this->GoogleCivicJsonResults()),
    ]);

    $handlerStack = \GuzzleHttp\HandlerStack::create($mock);
    $client = new \GuzzleHttp\Client(['handler' => $handlerStack]);

    $gc = new Civi\Electoral\Api\GoogleCivicInformation();
    $gc->setGuzzleClient($client);
    $gc->setGeocodeProviderClass('mockGeocodeProviderClass');
    $gc->singleAddressLookup($this->addressId);
    $districts = \Civi\Api4\Contact::get()
        ->addSelect('electoral_districts.*')
        ->addWhere('id', '=', $this->contactId)
        ->execute();
    $this->assertEquals($districts->count(), 6);
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
        $this->assertEquals($district['electoral_districts.electoral_district'], '9');
      }
    }

  }

  /**
   * Test single address lookup for google civic.
   */
  public function testCicero() {
    \Civi\Api4\Setting::set()
      ->addValue('ciceroAPIKey', 'foo123')
      ->execute();
    \Civi\Api4\Setting::set()
      ->addValue('electoralApiDistrictTypes', ['country', 'administrativeArea1', 'administrativeArea2', 'county', 'city'])
      ->execute();

    // Create a mock guzzle client, specify exactly the response we
    // should get from running a real query.
    $mock = new \GuzzleHttp\Handler\MockHandler([
      new \GuzzleHttp\Psr7\Response(200, [], $this->ciceroJsonResults()),
    ]);

    $handlerStack = \GuzzleHttp\HandlerStack::create($mock);
    $client = new \GuzzleHttp\Client(['handler' => $handlerStack]);

    $c = new Civi\Electoral\Api\Cicero();
    $c->setGuzzleClient($client);
    $c->singleAddressLookup($this->addressId);
    $districts = \Civi\Api4\Contact::get()
        ->addSelect('electoral_districts.*')
        ->addWhere('id', '=', $this->contactId)
        ->execute();
    $this->assertEquals($districts->count(), 6);
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
        $this->assertEquals($district['electoral_districts.electoral_district'], '9');
      }
    }

  }
  protected function OpenstatesJsonResults() {
    return file_get_contents('openstates.lookup.json');
  }

  protected function GoogleCivicJsonResults() {
    return file_get_contents('googlecivic.lookup.json');
  }

  protected function CiceroJsonResults() {
    return file_get_contents('cicero.lookup.json');
  }

}
