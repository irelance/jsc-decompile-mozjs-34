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
    private $fp;
    private $CRLF;
    protected $parseIndex = 0;

    protected $buildId = '';
    protected $contexts = [];

    public $bytecodes = [];
    public $bytecodeLength = 0;

    public function __construct($filename)
    {
        $this->fp = fopen($filename, 'rb');
        $this->CRLF = php_sapi_name() == 'cli' ? "\n" : "<hr>";
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

    public function printOpcodes()
    {
        /** @var Context $context * */
        foreach ($this->contexts as $index => $context) {
            $opcodes = $context->getOperations();
            echo '------------------' . $index . '------------------', $this->CRLF;
            foreach ($opcodes as $opcode) {
                $op = Constant::_Opcode[$opcode['id']];
                echo $op['name'], ' ', $op['len'], ' ', $op['use'], ' ', $op['def'], ' :';
                echo implode(', ', $opcode['params']), $this->CRLF;
            }
            echo '----------------------------------------', $this->CRLF;
        }
    }

    public function printAtoms()
    {
        /** @var Context $context * */
        foreach ($this->contexts as $index => $context) {
            $atoms = $context->getAtoms();
            echo '------------------' . $index . '------------------', $this->CRLF;
            echo implode(' ', $atoms), $this->CRLF;
            echo '----------------------------------------', $this->CRLF;
        }
    }

    public function printSummaries()
    {
        /** @var Context $context * */
        foreach ($this->contexts as $index => $context) {
            $summaries = $context->getSummaries();
            echo '------------------' . $index . '------------------', $this->CRLF;
            foreach ($summaries as $key => $val) {
                echo $key, ': ', $val, $this->CRLF;
            }
            echo '----------------------------------------', $this->CRLF;
        }
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
        echo '----------------ByteCode---------------', $this->CRLF;
        echo 'file size :', $this->bytecodeLength, $this->CRLF;
        echo 'parse size :', $this->parseIndex, $this->CRLF;
        echo '---------------------------------------', $this->CRLF;
    }
}
