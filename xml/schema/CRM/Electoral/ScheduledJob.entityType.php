<?php
// This file declares a new entity type. For more details, see "hook_civicrm_entityTypes" at:
// https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_entityTypes
return [
  [
    'name' => 'ScheduledJob',
    'class' => 'CRM_Electoral_DAO_ScheduledJob',
    'table' => 'civicrm_electoral_scheduled_job',
  ],
];
