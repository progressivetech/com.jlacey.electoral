<?php
// phpcs:disable
use CRM_Electoral_ExtensionUtil as E;
// phpcs:enable

class CRM_Electoral_BAO_DistrictJob extends CRM_Electoral_DAO_DistrictJob {
  // Pending means it has been created, but never started.
  const STATUS_PENDING = 'pending'; 
  // In process means it has started, but is not yet completed and is not currently
  // running.
  const STATUS_IN_PROCESS = 'in process';
  // Running means, well, it is running. If a job dies while it is running, it will not
  // be re-started. This ensures we don't keep re-running a job that causes an error.
  const STATUS_RUNNING = 'running';
  const STATUS_COMPLETED = 'completed';
  const STATUS_ERROR = 'error'; 

}
