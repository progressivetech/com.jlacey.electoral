<?php

use CRM_Electoral_ExtensionUtil as E;

/**
 * Form controller class
 *
 * @see https://docs.civicrm.org/dev/en/latest/framework/quickform/
 */
class CRM_Electoral_Form_Electoral extends CRM_Core_Form {
  public function buildQuickForm() {
    $groups = CRM_Core_PseudoConstant::nestedGroup();
    //get the campaign groups.
    $this->add('select', 'group_id',
      ts('Choose the group to district'),
      $groups,
      FALSE,
      [
        'class' => 'crm-select2 huge',
        'placeholder' => ts('- none -'),
      ]
    );
    $this->addRule('group_id', E::ts("Please select a group"), 'required', NULL, 'client');
    $this->add('text', 'limit_per_run', ts('Limit per run'));
    $this->add('advcheckbox', 'update', ts('Update?'));

    $this->addButtons([
        ['type' => 'submit', 'name' => E::ts('Schedule'), 'isDefault' => TRUE],
        ['type' => 'cancel', 'name' => E::ts('Cancel')]
    ]);

    // export form elements
    $this->assign('elementNames', $this->getRenderableElementNames());

    // This code reloads the main page after adding a new district job.
    Civi::resources()->addScriptFile('com.jlacey.electoral', 'js/electoral.js', [
      'weight' => 10,
      'region' => 'page-footer'
    ]);

    parent::buildQuickForm();
  }

  /**
   * AddRules hook
   */
  public function addRules() {
    $this->addFormRule([self::class, 'validateForm']);
  }

  /**
   * Validates form
   *
   * @param $values
   *
   * @return array
   */
  public static function validateForm($values) {
    $errors = [];
    // Ensure limit per run is an integer.
    $limit = $values['limit_per_run'] ?? NULL;
    if ($limit) {
      if (!is_numeric($limit)) {
        $errors['limit_per_run'] = E::ts("Please enter a number for limit to run.");
      }
    }
    $contacts = \CRM_Contact_BAO_Group::getGroupContacts($values['group_id']);
    if (count($contacts) == 0) {
      $errors['group_id'] = E::ts("That group has no members. Please pick a group with at least one contact in it.");
    }

    return empty($errors) ? TRUE : $errors;
  }

  public function postProcess() {
    $values = $this->exportValues();
    $groupId = $values['group_id'];
    $limitPerRun = $values['limit_per_run'] ?? 0;
    $update = $values['update'] ?? 0;

    $groupTitle = \Civi\Api4\Group::get()
      ->addWhere('id', '=', $groupId)
      ->addSelect('title')
      ->execute()->first()['title'];

    $contactIds = serialize(array_keys(\CRM_Contact_BAO_Group::getGroupContacts($groupId)));
    parent::postProcess();
    \Civi\Api4\DistrictJob::create()
      ->addValue('contact_ids', $contactIds)
      ->addValue('limit_per_run', $limitPerRun)
      ->addvalue('update', $update)
      ->addvalue('description', $groupTitle)
      ->addValue('status', \CRM_Electoral_BAO_DistrictJob::STATUS_PENDING)
      ->execute();
    $session = CRM_Core_Session::singleton();
    $msg = E::ts("Your Distrct Job has been saved.");
    $session->setStatus($msg, E::ts("Success"), 'success');
   
  }

  /**
   * Get the fields/elements defined in this form.
   *
   * @return array (string)
   */
  public function getRenderableElementNames() {
    // The _elements list includes some items which should not be
    // auto-rendered in the loop -- such as "qfKey" and "buttons".  These
    // items don't have labels.  We'll identify renderable by filtering on
    // the 'label'.
    $elementNames = array();
    foreach ($this->_elements as $element) {
      /** @var HTML_QuickForm_Element $element */
      $label = $element->getLabel();
      if (!empty($label)) {
        $elementNames[] = $element->getName();
      }
    }
    return $elementNames;
  }

}
