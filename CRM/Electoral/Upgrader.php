<?php
use CRM_Electoral_ExtensionUtil as E;

/**
 * Collection of upgrade steps.
 */
class CRM_Electoral_Upgrader extends CRM_Extension_Upgrader_Base {

  /**
   * Convert electoral status from address to contact
   *
   * Take every entity_id in the electoral_status table, lookup the address
   * matching the entity_id to find the corresponding contact id, and then
   * insert that into the new electoral update status table. If there is more
   * then one (a contact might have more then one address with an electoral
   * status record), take the first one. 
   *
   * @return TRUE on success
   * @throws Exception
   */
  public function upgrade_1005() {
    $this->ctx->log->info('Applying update 1005');

    // Get all the electoral statuses.
    $oldElectoralStatusGroup = \Civi\Api4\CustomGroup::get()
      ->addSelect('table_name')
      ->addWhere('name', '=', 'electoral_status')
      ->execute()->first();

    if (!$oldElectoralStatusGroup) {
      // It's already gone, nothing to do.
      return TRUE;
    }

    $tableName = $oldElectoralStatusGroup['table_name'];
    $sql = "SELECT * FROM $tableName";
    $dao = \CRM_Core_DAO::executeQuery($sql);
     
    $seenContactIds = [];
    while ($dao->fetch()) {
      // Before the upgrade, the entity_id points to the id of the
      // record in the corresponding civicrm_address table.
      $addressId = $dao->entity_id;
      $electoralStatusId = $dao->id;
      $message = $dao->electoral_status_error_message;
      $reason = $dao->electoral_status_error_reason;

      // Fetch the related contact_id.
      $contactId = \Civi\Api4\Address::get()
        ->addSelect('contact_id')
        ->addWhere('id', '=', $addressId)
        ->execute()->first()['contact_id'];
      if ($contactId) {
        if (!in_array($seenContactIds)) {
          // Add to contact
          \Civi\Api4\Contact::update()
            ->addValue('electoral_update_status.electoral_error_message', $reason . ' ' . $message)
            ->addValue('electoral_update_status.electoral_last_status', 'failed')
            ->addValue('electoral_update_status.electoral_last_updated', date('Y-m-d'))
            ->addWhere('id', '=', $contactId)
            ->execute();
          // Indicate that we have already updated this contact record. If
          // we get another electoral status, we will ignore it and it will get
          // deleted when we delete the old custom field group.
          $seenContactIds[] = $contactId; 
          // Go to the next record.
          continue;
        }
      }
    }

    // Lastly, delete the old custom fields. 
    \Civi\Api4\CustomField::delete()
      ->addWhere('custom_group_id.name', '=', 'electoral_status')
      ->execute();
    \Civi\Api4\CustomGroup::delete()
      ->addWhere('name', '=', 'electoral_status')
      ->execute();

    return TRUE;
  }

  /**
   * Add new table for tracking distrct jobs. 
   *
   * @return TRUE on success
   * @throws Exception
   */
  public function upgrade_1006() {
    $dao = CRM_Core_DAO::executeQuery("SELECT COUNT(*) AS count FROM information_schema.TABLES WHERE TABLE_NAME = 'civicrm_electoral_district_job'");
    $dao->fetch();

    if ($dao->count > 0) {
      return TRUE;
    }
    return $this->executeSqlFile('sql/auto_install.sql');
  }
}
