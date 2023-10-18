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
    $this->add('text', 'limit_per_run', ts('Limit per run'));
    $this->add('advcheckbox', 'update', ts('Update?'));

    $this->addButtons([
        ['type' => 'submit', 'name' => E::ts('Schedule'), 'isDefault' => TRUE],
        ['type' => 'cancel', 'name' => E::ts('Cancel')]
    ]);

    // export form elements
    $this->assign('elementNames', $this->getRenderableElementNames());

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
    return empty($errors) ? TRUE : $errors;
  }

  public function postProcess() {
    $values = $this->exportValues();
    parent::postProcess();
    $session = CRM_Core_Session::singleton();
    $msg = E::ts("Your Scheduled Job has been saved.");
    $session->setStatus($msg);
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
