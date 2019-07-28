<?php // index.php
/**
 * This file is the master router for the v4 system: all queries are passed through here
 */

error_reporting(E_ALL & ~E_NOTICE & ~E_STRICT & ~E_DEPRECATED);

date_default_timezone_set('America/Los_Angeles');

/**
 * site version cookie (1-year) for entire domain
 */
setcookie("ver", 4, time() + (365 * 24 * 60 * 60), "/", ".timelesstruths.org");

/**
 * Global switch for development version
 */
if ($_GET['dev'] == 'true') {
    setcookie('dev', 'true', time() + 60 * 60 * 24 * 30/*30 days*/, '/', '.' . $_SERVER['HTTP_HOST']);
}
if ($_GET['dev'] == 'false') {
    setcookie('dev', '', time() - 3600/*expired*/, '/', '.' . $_SERVER['HTTP_HOST']);
}
define('TT_DEV', isset($_COOKIE['dev']));

/**
 * Global switch for debugging
 * @? may not be necessary
 */
define('TT_DEBUG', false);

/**
 * Global variable for localhost ip, which can change in portable installations
 */
define('TT_LOCAL_IP', (!stristr($_SERVER['HTTP_HOST'], 'timelesstruths.org') ? $_SERVER['HTTP_HOST'] : ''));
define('TT_LOCALHOST', TT_LOCAL_IP ? TT_LOCAL_IP . '/' : '');
define('TT_BASE_ROOT', TT_LOCAL_IP ? 'http://' . TT_LOCAL_IP . '/' : 'https://');

/**
 * Determine the operating zone
 */
if (stristr($_SERVER['HTTP_HOST'], 'timelesstruths.org')) {
    define('TT_ZONE', 'www');
    define('TT_CONFIG_FILE', '../../_config.www.php');
} else if (file_exists('../../_config.admin.php')) {
    define('TT_ZONE', 'dev');
    define('TT_CONFIG_FILE', '../../_config.admin.php');
} else {
    define('TT_ZONE', 'dev');
    define('TT_CONFIG_FILE', '../_config.dev.php');
}

/**
 * Determine the app (subdomain)
 */
preg_match("'(?:([^/]*)\.)?timelesstruths\.org'", $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'], $m);

if ($m[1]) {
    define('TT_APP_NAME', $m[1]);
} else {
    define('TT_APP_NAME', 'default');
}

/**
 * Sets the directory of the current project
 */
define('TT_ROOT', dirname(__FILE__));
define('TT_APP_ROOT', TT_ROOT . '/app');
define('TT_LIBS_ROOT', TT_APP_ROOT . '/libs');

/**
 * Sets whether views should be rendered (default: true)
 */
define('TT_VIEW', $_REQUEST['view'] != 'false');

/**
 * Add engine/core to include repositories
 */
if (!function_exists('ini_set')) {
    throw new Exception('Function ini_set() unavailable.');
}

if (function_exists('ini_set')) {
    ini_set('include_path', TT_LIBS_ROOT . (TT_DEV ? PATH_SEPARATOR . TT_APP_ROOT . '.DEV' : '') . PATH_SEPARATOR . TT_APP_ROOT . PATH_SEPARATOR . TT_ROOT . PATH_SEPARATOR . ini_get('include_path'));
}

/**
 * sets exception handler for all uncaught exceptions
 */
function exception_handler($exception) {
    echo "Uncaught exception: ", $exception->getMessage(), "\n";
}

set_exception_handler('exception_handler');

/**
 * create autoloader
 * Example: class 'Some_ClassName' will be found in 'Some/ClassName.php'
 */
function __autoload($className) {
    $classFile = str_replace('_', '/', $className) . '.php';
    if (TT_DEBUG) {
        echo "<hr />$className == $classFile";
    }
    include $classFile;
}

/**
 * run the app
 */
$app = new App();

/*****************************/
if (TT_DEBUG) {
    echo "\r\n" . str_repeat('=', 100) . str_repeat("\r\n", 25);
    /*
     ?><
     pre><?
     //print_r($GLOBALS);
     //print_r($app);
     ?><
     /pre><?
     //*/
    phpinfo();
}
