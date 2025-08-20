<?php
if (!defined('GLPI_ROOT')) { die('Access denied'); }
class PluginHelloMenuHelpdesk extends CommonGLPI {
   static function canView() { return Session::getLoginUserID() > 0; }
   static function getTypeName($nb = 0) { return __('My Assets', 'hello'); }
   static function getMenuName($nb = 0) { return self::getTypeName($nb); }
   static function getMenuContent() {
      global $CFG_GLPI;
      return ['title'=>__('My Assets','hello'),'page'=>$CFG_GLPI['root_doc'].'/plugins/userassetdetail/front/hello.php?root=helpdesk'];
   }
}
