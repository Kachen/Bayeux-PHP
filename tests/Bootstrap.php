<?php
namespace tests;

error_reporting(E_ALL | E_STRICT | E_DEPRECATED);


\set_include_path(\implode(':', array(
	__DIR__,
	realpath(__DIR__ . '/'),
	realpath(__DIR__ . '/../lib'),
	\get_include_path()
)));




spl_autoload_register(function($class) {
    var_dump($class);
	$file = str_replace("\\", "/", $class) . '.php';
	require $file;
});