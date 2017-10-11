<?php

/**
 * Created by PhpStorm.
 * User: irelance
 * Date: 2017/9/20
 * Time: 上午8:04
 *
 * js/src/jsscript.h
 * js/src/vm/Xdr.h
 */
namespace Irelance\Mozjs34;

class Decompile
{
    use Xdr\Common;
    use Xdr\Script;
    use Xdr\Atom;
    use Xdr\Object;
    use Xdr\Scope;
    use Xdr\Operation;
    private $fp;
    protected $parseIndex = 0;

    protected $buildId = '';
    protected $contexts = [];

    public $bytecodes = [];
    public $bytecodeLength = 0;

    public function __construct($filename)
    {
        $this->fp = fopen($filename, 'rb');
        $this->init();
    }

    public function __destruct()
    {
        fclose($this->fp);
    }

    public function init()
    {
        $i = 0;
        while (!feof($this->fp)) {
            $c = fgetc($this->fp);
            $this->bytecodes[$i] = ord($c);
            $i++;
        }
        $this->bytecodeLength = count($this->bytecodes);
    }

    protected function parserVersion()
    {
        $this->parseIndex = 0;
        $bytecodeVer = $this->todec();
        return $bytecodeVer;
    }

    public function run()
    {
        $this->parserVersion();
        $this->XDRScript();
    }

    public function runResult()
    {
        echo '----------------ByteCode---------------', CLIENT_EOL;
        echo 'file size :', $this->bytecodeLength, CLIENT_EOL;
        echo 'parse size :', 1 + $this->parseIndex, CLIENT_EOL;
        echo '---------------------------------------', CLIENT_EOL;
    }

    public function getContexts()
    {
        return $this->contexts;
    }
}
