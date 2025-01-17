-- +--------------------------------------------------------------------+
-- | Copyright CiviCRM LLC. All rights reserved.                        |
-- |                                                                    |
-- | This work is published under the GNU AGPLv3 license with some      |
-- | permitted exceptions and without any warranty. For full license    |
-- | and copyright information, see https://civicrm.org/licensing       |
-- +--------------------------------------------------------------------+
--
-- Generated from schema.tpl
-- DO NOT EDIT.  Generated by CRM_Core_CodeGen
--
-- /*******************************************************
-- *
-- * Clean up the existing tables - this section generated from drop.tpl
-- *
-- *******************************************************/

SET FOREIGN_KEY_CHECKS=0;

DROP TABLE IF EXISTS `civicrm_electoral_district_job`;

SET FOREIGN_KEY_CHECKS=1;
-- /*******************************************************
-- *
-- * Create new tables
-- *
-- *******************************************************/

-- /*******************************************************
-- *
-- * civicrm_electoral_district_job
-- *
-- * Pre-defined districting jobs
-- *
-- *******************************************************/
CREATE TABLE `civicrm_electoral_district_job` (
  `id` int unsigned NOT NULL AUTO_INCREMENT COMMENT 'Unique DistrictJob ID',
  `description` varchar(512) COMMENT 'Description of the district job ',
  `contact_ids` text COMMENT 'Comma separated list of contact ids to re-district',
  `limit_per_run` int unsigned DEFAULT 0 COMMENT 'Only process the given number of contacts per run. Enter 0 for no limit.',
  `update` tinyint DEFAULT 0 COMMENT 'Lookup contacts that already have district data present.',
  `status` varchar(10) COMMENT 'Status of the job',
  `status_message` varchar(512) COMMENT 'Status explanation of the job',
  `offset` int unsigned DEFAULT 0 COMMENT 'Keeps track of the index of the last contact id processed',
  `date_created` timestamp DEFAULT CURRENT_TIMESTAMP COMMENT 'When was the job created.',
  `date_updated` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'When was the job last updated.',
  PRIMARY KEY (`id`)
)
ENGINE=InnoDB;
