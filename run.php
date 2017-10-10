<?php
/**
 * Created by PhpStorm.
 * User: irelance
 * Date: 2017/10/1
 * Time: 上午10:47
 */
include 'vendor/autoload.php';


$decompile = new Irelance\Mozjs34\Decompile($argv[1]);
$decompile->run();
$decompile->printOpcodes();
