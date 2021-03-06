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

