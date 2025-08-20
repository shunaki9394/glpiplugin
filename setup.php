<?php
// User Asset Details - renamed from hello2stable
// MIT

if (!defined('GLPI_ROOT')) {
   // GLPI defines GLPI_ROOT before loading plugins
}

require_once __DIR__ . '/inc/menuassets.class.php';

function plugin_version_userassetdetail() {
   return array(
      'name'         => 'User Asset Details',
      'version'      => '1.1v',
      'author'       => 'Dev with ChatGPT, Consept From Tan Yeong Wei',
      'license'      => 'MIT',
      'requirements' => array(
         'glpi' => array('min' => '10.0.0', 'max' => '10.0.99')
      )
   );
}

function plugin_init_userassetdetail() {
   global $PLUGIN_HOOKS;
   // read-only UI; CSRF-safe
   $PLUGIN_HOOKS['csrf_compliant']['userassetdetail'] = true;
   // show under Assets
   $PLUGIN_HOOKS['menu_toadd']['userassetdetail'] = array(
      'assets' => PluginUserassetdetailMenuAssets::class
   );
}

function plugin_userassetdetail_install()   { return true; }
function plugin_userassetdetail_uninstall() { return true; }
