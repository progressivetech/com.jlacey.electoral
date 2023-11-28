<?php

namespace Civi\Api4\Action\Electoral;
use CRM_Electoral_ExtensionUtil as E;

/**
 * Run any scheduled district jobs that are active. 
 *
 */
class RunJobs extends \Civi\Api4\Generic\AbstractAction {
  public function _run(\Civi\Api4\Generic\Result $result) {
    // We do not re-run jobs that are currently running to avoid stepping on toes
    // and also to avoid continuously re-running a job that is failing for some
    // reason.
    $statuses = [
      \CRM_Electoral_BAO_DistrictJob::STATUS_PENDING,
      \CRM_Electoral_BAO_DistrictJob::STATUS_IN_PROCESS,
    ];
    $jobs = \Civi\Api4\Electoral\DistrictJob::get()
      ->addWhere('status', 'IN', $statuses)
      ->execute(); 
    $considered = 0;
    $processed = 0;
    foreach ($jobs as $job) {
      try {
        // We are starting! Update the status.
        \Civi\Api4\DistrictJob::update()
          ->addValue('status', \CRM_Electoral_BAO_DistrictJob::STATUS_RUNNING)
          ->addWhere('id', '=', $job['id'])
          ->execute();

        $contactIds = unserialize($job['contact_ids']);
        $totalContacts = count($contactIds);
        $activeOffset = $job['offset'] ?? 0;
        $activeLimit = $offset + $job['limit'] ?? 100;
        $update = $job['update'];
        $jobId = $job['id'];

        for ($activeOffset; $activeOffset < $activeLimit; $activeOffset++) {
          // Are we done with this job? $offset starts at 0, so add one.
          $contactId = $contactIds[$activeOffset] ?? NULL;
          if (!$contactId) {
            // No more contacts to consider. We are done.
            \Civi\Api4\DistrictJob::update()
              ->addValue('status', \CRM_Electoral_BAO_DistrictJob::STATUS_COMPLETED)
              ->addValue('offset', $activeOffset)
              ->addWhere('id', '=', $jobId)
              ->execute();
            continue;
          }

          // We are considering this contact.
          $considered++;
          if (!$update && $this->hasDistrictData($contactId)) {
            // We are not updating and this contact has district data
            // already, so skip it.
            continue;
          }
          // Process this contact.
          $result = \Civi\Api4\Electoral::Lookup()
            ->setContactId($contactId)
            ->setWrite(TRUE)
            ->execute();
          $processed++;
        }
        // Update the job so it's ready for the next run.
        \Civi\Api4\DistrictJob::update()
          ->addValue('status', \CRM_Electoral_BAO_DistrictJob::STATUS_IN_PROCESS)
          ->addValue('offset', $activeOffset)
          ->addWhere('id', '=', $jobId)
          ->execute();

      }
      catch (\Exception $e) {
        // Catch any error so we can log and update the offset.
        \Civi\Api4\DistrictJob::update()
          ->addValue('status', \CRM_Electoral_BAO_DistrictJob::STATUS_ERROR)
          ->addValue('status_message', $e->getMessage())
          ->addValue('offset', $activeOffset)
          ->addWhere('id', '=', $jobId)
          ->execute();
        // Re-throw.
        throw($e);

      }
    }
    $result[] = "{$considered} contacts considered, {$processed} contacts processed.";
  }
  
  /**
   * hasDistrictdata 
   *
   * Determine if the contact id already has district data. 
   *
   * @var int $contactId 
   * @return bool 
   */
  protected function hasDistrictData($contactId): bool {
    $result = \Civi\Api4\Contact::get()
      ->addSelect('id')
      ->addWhere('electoral_update_status.electoral_last_updated', 'IS NOT EMPTY')
      ->addWhere('id', '=', $contactId)
      ->execute();

    if ($result->count() > 0) {
      return TRUE;
    }
    return FALSE;
  }

}
?>
