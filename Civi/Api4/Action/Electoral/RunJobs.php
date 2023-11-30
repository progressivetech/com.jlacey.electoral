<?php

namespace Civi\Api4\Action\Electoral;
use CRM_Electoral_ExtensionUtil as E;

/** Run any scheduled district jobs that are active. 
 *
 */
class RunJobs extends \Civi\Api4\Generic\AbstractAction {
  /*
   * testReplacementMap
   *
   * A map of contactId => guzzleClient and Api Provider that is used for
   * testing to force the guzzle http client to return known data rather than
   * making an external http request.
   *
   * For example:
   *   [ 123 => [ 'guzzle_client' => $client, 'api_provider' => 'Cicero' ]]
   *
   * Only used for testing purposes.
   */

  protected $testReplacementMap = [];

  /**
   * geocodeProviderClass
   *
   * Only used for testing purposes.
   */
  protected $geocodeProviderClass = NULL;

  public function _run(\Civi\Api4\Generic\Result $result) {
    // We do not re-run jobs that are currently running to avoid stepping on toes
    // and also to avoid continuously re-running a job that is failing for some
    // reason.
    $statuses = [
      \CRM_Electoral_BAO_DistrictJob::STATUS_PENDING,
      \CRM_Electoral_BAO_DistrictJob::STATUS_IN_PROCESS,
    ];
    $jobs = \Civi\Api4\DistrictJob::get(FALSE)
      ->addWhere('status', 'IN', $statuses)
      ->execute(); 
    $considered = 0;
    $processed = 0;
    $activeOffset = NULL;
    $jobId = NULL;
    foreach ($jobs as $job) {
      try {
        $jobId = $job['id'];
        // We are starting! Update the status.
        \Civi\Api4\DistrictJob::update(FALSE)
          ->addValue('status', \CRM_Electoral_BAO_DistrictJob::STATUS_RUNNING)
          ->addWhere('id', '=', $jobId)
          ->execute();

        $contactIds = unserialize($job['contact_ids']);
        $totalContacts = count($contactIds);
        $activeOffset = $job['offset'] ?? 0;
        $limit = $job['limit'] ?? 100;
        if ($limit == 0) {
          // This means unlimited, so set to arbirtrary high value
          $limit = 9999999;
        }
        $activeLimit = $activeOffset + $limit;
        $update = $job['update'] ?? FALSE;

        for ($activeOffset; $activeOffset < $activeLimit; $activeOffset++) {
          // Are we done with this job? $offset starts at 0, so add one.
          $contactId = $contactIds[$activeOffset] ?? NULL;
          if (!$contactId) {
            // No more contacts to consider. We are done.
            \Civi\Api4\DistrictJob::update(FALSE)
              ->addValue('status', \CRM_Electoral_BAO_DistrictJob::STATUS_COMPLETED)
              ->addValue('offset', $activeOffset)
              ->addWhere('id', '=', $jobId)
              ->execute()->first()['status'];
            // Continue to the next job.
            continue 2;
          }

          // We are considering this contact.
          $considered++;
          if (!$update && $this->hasDistrictData($contactId)) {
            // We are not updating and this contact has district data
            // already, so skip it.
            continue;
          }
          // Special guzzle client is only used for testing.
          $guzzleClient = $this->getTestReplacementMap()[$contactId]['guzzle_client'] ?? NULL;
          $apiProvider = $this->getTestReplacementMap()[$contactId]['api_provider'] ?? NULL;
          // Process this contact.
          $result = \Civi\Api4\Electoral::Lookup(FALSE)
            ->setContactId($contactId)
            ->setWrite(TRUE)
            ->setGuzzleClient($guzzleClient)
            ->setApiProvider($apiProvider)
            ->setGeocodeProviderClass($this->getGeocodeProviderClass())
            ->execute();
          $processed++;
        }
        // Update the job so it's ready for the next run.
        \Civi\Api4\DistrictJob::update(FALSE)
          ->addValue('status', \CRM_Electoral_BAO_DistrictJob::STATUS_IN_PROCESS)
          ->addValue('offset', $activeOffset)
          ->addWhere('id', '=', $jobId)
          ->execute();

      }
      catch (\Exception $e) {
        // Catch any error so we can log and update the offset.
        \Civi\Api4\DistrictJob::update(FALSE)
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
    $result = \Civi\Api4\Contact::get(FALSE)
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
