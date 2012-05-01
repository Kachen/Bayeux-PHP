<?php
namespace tests;

error_reporting(E_ALL | E_STRICT | E_DEPRECATED | E_NOTICE);


\set_include_path(\implode(':', array(
	__DIR__,
	realpath(__DIR__ . '/'),
	realpath(__DIR__ . '/../lib'),
        realpath(__DIR__ . '/../vendo/PHPJetty/lib'),
	\get_include_path()
)));

spl_autoload_register(function($class) {
    //     var_dump($class);
    $file = str_replace("\\", "/", $class) . '.php';
    require $file;
});


define('COMMANDO_WEBSERVER', 'php -S localhost:3000 -t' . realpath(__DIR__ . '/../lib/Bayeux'));


$output = shell_exec('ps aux | grep "' . COMMANDO_WEBSERVER . '"');

if (preg_match_all('!' . COMMANDO_WEBSERVER . '!', $output) < 3) {
    system(COMMANDO_WEBSERVER . ' 2>1 > /dev/null &');
}

$output = shell_exec('ps aux | grep "' . COMMANDO_WEBSERVER . '"');
if (preg_match_all('!' . COMMANDO_WEBSERVER . '!', $output) < 3) {
    echo 'Falha ao subir o servidor web';
    exit;
}