<?php

use CRM_Electoral_ExtensionUtil as E;
use Civi\Test\HeadlessInterface;
use Civi\Test\HookInterface;
use Civi\Test\TransactionalInterface;

/**
 *  Ensure we avoid a duplicate external id error if we add an official who has been
 *  placed in the trash.
 *
 * @group headless
 */
class DeletedOfficialTest extends \PHPUnit\Framework\TestCase implements HeadlessInterface, HookInterface, TransactionalInterface {

  public function setUpHeadless() {
    return \Civi\Test::headless()
      ->installMe(__DIR__)
      ->apply();
  }

  public function setUp(): void {
    parent::setUp();
  }

  public function tearDown(): void {
    parent::tearDown();
  }

  /**
   * Test creation of official with external id, then delete, then
   * create again.
   */
  public function testDeleteThanCreateOfficial() {
    $official = new CRM_Electoral_Official();
    $official
      ->setFirstName('Finn')
      ->setLastName('The Human')
      ->setExternalIdentifier('finn-1234')
      ->createOfficial();

    // Now mark as deleted.
    \Civi\Api4\Contact::delete()
      ->addWhere('id', '=', $official->getContactId())
      ->execute();

    // Now try to re-add an official with the same external identifier.
    $newOfficial = new CRM_Electoral_Official();
    $newOfficial
      ->setFirstName('Finn')
      ->setLastName('The Human')
      ->setExternalIdentifier('finn-1234')
      ->createOfficial();
    $this->assertIsInt($newOfficial->getContactId(), "Ensure we can create an official after deleting one.");

  }


}
