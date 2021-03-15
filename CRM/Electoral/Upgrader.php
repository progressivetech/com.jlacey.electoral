<?php

/**
 * Collection of upgrade steps.
 */
class CRM_Electoral_Upgrader extends CRM_Electoral_Upgrader_Base {

  // By convention, functions that look like "function upgrade_NNNN()" are
  // upgrade tasks. They are executed in order (like Drupal's hook_update_N).

  /**
   * Add the Electoral API Data Providers Option Group/Values.
   */
  public function upgrade_1000() {
    $this->ctx->log->info('Adding Electoral API Option Group: Data Providers');
    return $this->addDataProviders();
  }

  public function upgrade_1001() {
    $this->ctx->log->info('Adding new Electoral API Columns');
    \Civi\Api4\CustomField::create(FALSE)
      ->addValue('name', 'electoral_note')
      ->addValue('label', 'Note')
      ->addValue('data_type', 'String')
      ->addValue('html_type', 'Text')
      ->addValue('is_required', '0')
      ->addValue('is_searchable', '1')
      ->addValue('is_search_range', '0')
      ->addValue('weight', '9')
      ->addValue('is_active', '1')
      ->addValue('is_view', '0')
      ->addValue('text_length', '128')
      ->addValue('column_name', 'electoral_districts_note')
      ->addValue('in_selector', '1')
      ->addValue('custom_group_id:name', 'electoral_districts')
      ->execute();
    \Civi\Api4\CustomField::create(FALSE)
      ->addValue('name', 'electoral_modified_date')
      ->addValue('label', 'Last Updated')
      ->addValue('data_type', 'Date')
      ->addValue('html_type', 'Select Date')
      ->addValue('is_required', '0')
      ->addValue('is_searchable', '1')
      ->addValue('is_search_range', '0')
      ->addValue('weight', '10')
      ->addValue('is_active', '1')
      ->addValue('is_view', '0')
      ->addValue('column_name', 'electoral_modified_date')
      ->addValue('in_selector', '1')
      ->addValue('custom_group_id:name', 'electoral_districts')
      ->execute();
    return TRUE;
  }

  private function addDataProviders() {
    $results = \Civi\Api4\OptionGroup::create(FALSE)
      ->addValue('name', 'electoral_api_data_providers')
      ->addValue('title', 'Electoral API Data Providers')
      ->addValue('data_type:name', 'String')
      ->addValue('is_reserved', TRUE)
      ->addChain('add_cicero', \Civi\Api4\OptionValue::create()
        ->addValue('option_group_id', '$id')
        ->addValue('label', 'Cicero')
        ->addValue('name', '\Civi\Electoral\Api\Cicero')
      )
      ->addChain('add_google', \Civi\Api4\OptionValue::create()
        ->addValue('option_group_id', '$id')
        ->addValue('label', 'Google Civic')
        ->addValue('name', '\Civi\Electoral\Api\GoogleCivicInformation')
      )
      ->execute();
    $success = isset($results['error_message']) ? FALSE : TRUE;
    return $success;
  }

  /**
   * FIXME: This is unused, still using auto_install.xml.
   */
  private function addChamberOptions() {
    $results = \Civi\Api4\OptionGroup::create(FALSE)
      ->addValue('name', 'electoral_districts_chamber_options')
      ->addValue('title', 'Chamber')
      ->addValue('data_type:name', 'String')
      ->addValue('is_reserved', TRUE)
      ->addChain('add_upper', \Civi\Api4\OptionValue::create()
        ->addValue('option_group_id', '$id')
        ->addValue('label', 'Upper')
        ->addValue('name', 'upper')
      )
      ->addChain('add_lower', \Civi\Api4\OptionValue::create()
        ->addValue('option_group_id', '$id')
        ->addValue('label', 'Lower')
        ->addValue('name', 'lower')
      )
      ->execute();
    $success = isset($results['error_message']) ? FALSE : TRUE;
    return $success;
  }

  /**
   * FIXME: This is unused, still using auto_install.xml.
   */
  private function addLevelOptions() {
    $results = \Civi\Api4\OptionGroup::create(FALSE)
      ->addValue('name', 'electoral_districts_level_options')
      ->addValue('title', 'Level')
      ->addValue('data_type:name', 'String')
      ->addValue('is_reserved', TRUE)
      ->addChain('add_upper', \Civi\Api4\OptionValue::create()
        ->addValue('option_group_id', '$id')
        ->addValue('label', 'Upper')
        ->addValue('name', 'upper')
      )
      ->addChain('add_lower', \Civi\Api4\OptionValue::create()
        ->addValue('option_group_id', '$id')
        ->addValue('label', 'Lower')
        ->addValue('name', 'lower')
      )
      ->execute();
    $success = isset($results['error_message']) ? FALSE : TRUE;
    return $success;
  }


  /**
   * Remove the Data Providers option group.
   */
  public function uninstall() {
    \Civi\Api4\OptionGroup::delete(FALSE)
      ->addWhere('name', '=', 'electoral_api_data_providers')
      ->execute();
  }

  /**
   * Example: Run an external SQL script when the module is installed.
   */
  public function install() {
    return $this->addDataProviders();
  }

  /**
   * Example: Run a simple query when a module is enabled.
   *
  public function enable() {
    CRM_Core_DAO::executeQuery('UPDATE foo SET is_active = 1 WHERE bar = "whiz"');
  }

  /**
   * Example: Run a simple query when a module is disabled.
   *
  public function disable() {
    CRM_Core_DAO::executeQuery('UPDATE foo SET is_active = 0 WHERE bar = "whiz"');
  }

  /**
   * Example: Run a couple simple queries.
   *
   * @return TRUE on success
   * @throws Exception
   *
  public function upgrade_4200() {
    $this->ctx->log->info('Applying update 4200');
    CRM_Core_DAO::executeQuery('UPDATE foo SET bar = "whiz"');
    CRM_Core_DAO::executeQuery('DELETE FROM bang WHERE willy = wonka(2)');
    return TRUE;
  } // */


  /**
   * Example: Run an external SQL script.
   *
   * @return TRUE on success
   * @throws Exception
  public function upgrade_4201() {
    $this->ctx->log->info('Applying update 4201');
    // this path is relative to the extension base dir
    $this->executeSqlFile('sql/upgrade_4201.sql');
    return TRUE;
  } // */


  /**
   * Example: Run a slow upgrade process by breaking it up into smaller chunk.
   *
   * @return TRUE on success
   * @throws Exception
  public function upgrade_4202() {
    $this->ctx->log->info('Planning update 4202'); // PEAR Log interface

    $this->addTask(ts('Process first step'), 'processPart1', $arg1, $arg2);
    $this->addTask(ts('Process second step'), 'processPart2', $arg3, $arg4);
    $this->addTask(ts('Process second step'), 'processPart3', $arg5);
    return TRUE;
  }
  public function processPart1($arg1, $arg2) { sleep(10); return TRUE; }
  public function processPart2($arg3, $arg4) { sleep(10); return TRUE; }
  public function processPart3($arg5) { sleep(10); return TRUE; }
  // */


  /**
   * Example: Run an upgrade with a query that touches many (potentially
   * millions) of records by breaking it up into smaller chunks.
   *
   * @return TRUE on success
   * @throws Exception
  public function upgrade_4203() {
    $this->ctx->log->info('Planning update 4203'); // PEAR Log interface

    $minId = CRM_Core_DAO::singleValueQuery('SELECT coalesce(min(id),0) FROM civicrm_contribution');
    $maxId = CRM_Core_DAO::singleValueQuery('SELECT coalesce(max(id),0) FROM civicrm_contribution');
    for ($startId = $minId; $startId <= $maxId; $startId += self::BATCH_SIZE) {
      $endId = $startId + self::BATCH_SIZE - 1;
      $title = ts('Upgrade Batch (%1 => %2)', array(
        1 => $startId,
        2 => $endId,
      ));
      $sql = '
        UPDATE civicrm_contribution SET foobar = whiz(wonky()+wanker)
        WHERE id BETWEEN %1 and %2
      ';
      $params = array(
        1 => array($startId, 'Integer'),
        2 => array($endId, 'Integer'),
      );
      $this->addTask($title, 'executeSql', $sql, $params);
    }
    return TRUE;
  } // */

}
