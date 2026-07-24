<?php
/**
 * Modular bootstrap for the INO Platform WordPress plugin.
 */

if (!defined('ABSPATH')) { exit; }

require_once __DIR__ . '/includes/class-ino-platform-loader.php';
INO_Platform_Loader::boot();
