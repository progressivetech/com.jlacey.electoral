<?php

namespace Civi\Api4\Action\Electoral;
use CRM_Electoral_ExtensionUtil as E;

/**
 * Run a one off job to redistrict contacts in a given group.
 * any scheduled district jobs that are active. 
 *
 */
class RunOneOff extends \Civi\Api4\Generic\AbstractAction {

  /**
   * limit
   *
   * Limit the number of lookups per run.
   *
   * @var int
   */
  protected $limit = 100;

  /**
   * Update
   *
   * Whether or not we should lookup and update contacts that
   * already have electoral data. Set to TRUE to update these
   * contacts, or FALSE to skip them.
   * @var bool
   */
  protected $update = FALSE;

  /**
   * Groups
   *
   * A pipe separated list of groups (titles) that should
   * be districted. Only contacts in these groups will be
   * included.
   *
   * @var string
   * @required
   */
  protected $groups = NULL;

  public function _run(\Civi\Api4\Generic\Result $result) {
    $limit = $this->getLimit();
    $update = $this->getUpdate();
    $groups = $this->getGroups();
    $cache = FALSE;
    // If there is more than one enabled provider, only take the first.
    $enabledProvider = array_pop(\Civi::settings()->get('electoralApiProviders'));
    $className = \Civi\Api4\OptionValue::get(FALSE)
      ->addSelect('name')
      ->addWhere('option_group_id:name', '=', 'electoral_api_data_providers')
      ->addWhere('value', '=', $enabledProvider)
      ->execute()
      ->column('name')[0];
    $provider = new $className($limit, $update, $cache, $groups);
    $result[] = $provider->processBatch();
  }

}
?>
