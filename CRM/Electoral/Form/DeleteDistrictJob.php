<?php

use CRM_Electoral_ExtensionUtil as E;

/**
 * Form controller class
 *
 * @see https://docs.civicrm.org/dev/en/latest/framework/quickform/
 */
class CRM_Electoral_Form_DeleteDistrictJob extends CRM_Core_Form {
  public function buildQuickForm() {

    $districtJobId = \CRM_Utils_Request::retrieve('id', 'Int');
    $description = \Civi\Api4\DistrictJob::get()
      ->addWhere('id', '=', $districtJobId)
      ->addSelect('description')
      ->execute()->first()['description'];
    $this->assign('districtJobId', $districtJobId);
    $this->assign('description', $description);
    $this->add('hidden', 'district_job_id', $districtJobId);
    $this->addButtons(array(
      array(
        'type' => 'submit',
        'name' => E::ts('Delete'),
        'isDefault' => TRUE,
      ),
    ));
    // This code reloads the main page after deleting district job.
    Civi::resources()->addScriptFile('com.jlacey.electoral', 'js/delete-district-job.js', [
      'weight' => 10,
      'region' => 'page-footer'
    ]);
    parent::buildQuickForm();
  }

  public function postProcess() {
    $values = $this->exportValues();
    \Civi\Api4\DistrictJob::delete()
      ->addWhere('id', '=', $values['district_job_id'])
      ->execute();
    $session = CRM_Core_Session::singleton();
    $session->setStatus(E::ts("The district job was deleted."), E::ts("Deletion"), 'success');
    parent::postProcess();
  }

}
