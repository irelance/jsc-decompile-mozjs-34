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
//$decompile->runResult();
$contexts = $decompile->getContexts();
foreach ($contexts as $index => $context) {
    //if ($index==0) {
        echo '==================================' . $index . '==================================S', CLIENT_EOL;
        /* @var \Irelance\Mozjs34\Context $context */
        $context->printProperties([
            //'Summaries',
            //'Atoms',
            //'Operations',
            'Content',
            //'Nodes',
            //'Consts',
            //'Objects',
            //'Regexps',
            //'TryNote',
            //'ScopeNote',
        ]);
        echo '==========================================================================E', CLIENT_EOL;
    //}
}
