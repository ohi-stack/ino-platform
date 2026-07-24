<?php
if (!defined('ABSPATH')) { exit; }

class INO_Platform_Loader {
    public static function boot() {
        $modules = array(
            'membership','identity','genealogy','buddypress','treasury','grants','housing','governance','certificates','volunteers'
        );
        foreach ($modules as $module) {
            $file = INO_PLATFORM_PATH . 'modules/' . $module . '/module.php';
            if (file_exists($file)) { require_once $file; }
        }
    }
}
