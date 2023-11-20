<?php
// phpcs:disable
use CRM_Electoral_ExtensionUtil as E;
// phpcs:enable

class CRM_Electoral_BAO_DistrictJob extends CRM_Electoral_DAO_DistrictJob {
  const STATUS_PENDING = 'pending'; 
  const STATUS_IN_PROCESS = 'in process';
  const STATUS_COMPLETED = 'completed';
  const STATUS_ERROR = 'error'; 

}
