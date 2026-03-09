<?php

if (!defined('GLPI_ROOT')) {
    die("Sorry. You can't access this file directly");
}

class PluginWhatsnewConfig extends CommonGLPI {

    public static $rightname = 'plugin_whatsnew_announcement';

    static function getTypeName($nb = 0) {
        return __("What's New", 'whatsnew');
    }

    static function getMenuName() {
        return __("What's New", 'whatsnew');
    }

    static function getMenuContent() {
        global $CFG_GLPI;

        $menu = [];

        if (Session::haveRight('plugin_whatsnew_announcement', UPDATE) || Session::haveRight('config', UPDATE)) {
            $menu['title'] = self::getMenuName();
            $menu['page']  = $CFG_GLPI['root_doc'] . '/plugins/whatsnew/front/config.php';
            $menu['icon']  = 'ti ti-speakerphone';
        }

        return $menu ?: false;
    }
}
