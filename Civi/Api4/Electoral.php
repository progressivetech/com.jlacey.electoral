<?php
namespace Civi\Api4;

/**
 * Electoral entity.
 *
 * Provided by the electoral extension.
 *
 * @package Civi\Api4
 */
class Electoral extends Generic\AbstractEntity {

  public static function getFields() {
    return new Generic\BasicGetFieldsAction(__CLASS__, __FUNCTION__, function() {
      return [];
    });
  }
}

