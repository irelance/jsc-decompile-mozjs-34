<?php
/**
 * Created by PhpStorm.
 * User: irelance
 * Date: 2017/10/10
 * Time: 上午11:30
 */

namespace Irelance\Mozjs34\Xdr;

use Irelance\Mozjs34\Constant;


trait Common
{
    protected function getRawHex($length)
    {
        $end = $this->parseIndex + $length;
        $result = '';
        for (; $this->parseIndex < $end; $this->parseIndex++) {
            $result .= sprintf('%02s', dechex($this->bytecodes[$this->parseIndex]));
        }
        return $result;
    }

    protected function todec($length = 4)//length include start
    {
        return $this->littleEndian2Dec($length);
    }

    protected function littleEndian2Dec($length)
    {
        $result = '';
        for ($i = $this->parseIndex + $length - 1; $i >= $this->parseIndex; $i--) {
            $result .= sprintf('%02s', dechex($this->bytecodes[$i]));
        }
        $this->parseIndex += $length;
        return hexdec($result);
    }

    protected function bigEndian2Dec($length)
    {
        $end = $this->parseIndex + $length;
        $result = '';
        for (; $this->parseIndex < $end; $this->parseIndex++) {
            $result .= sprintf('%02s', dechex($this->bytecodes[$this->parseIndex]));
        }
        return hexdec($result);
    }

    protected function uInt32ToInt32($num)
    {
        return unpack('l', pack('L', $num))[1];
    }

    protected function uIntToInt($num, $bit)
    {
        $half = 1 << ($bit - 1);
        if ($num <= $half) {
            return $num;
        }
        $max = (1 << $bit) - 1;
        return -(($num - 1) ^ $max);
    }

    public function xdrConst()
    {
        $type = $this->todec();
        $const = [
            'type' => Constant::_ConstTag[$type],
        ];
        switch ($type) {
            case 0:
                $const['value'] = $this->todec();
                break;
            case 1:
                $value = unpack('d', pack('H*', $this->getRawHex(8)));
                $const['value'] = $value[1];
                break;
            case 2:
                $const['value'] = $this->XDRAtom();
                break;
            case 3:
                $const['value'] = true;
                break;
            case 4:
                $const['value'] = false;
                break;
            case 5:
                $const['value'] = null;
                break;
            case 6:
                $object = $this->xdrCK_JSObject();
                $const['value'] = "__OBJECT__";
                $const['extra'] = $object;
                break;
            case 7:
                $const['value'] = "__VOID__";
                break;
            case 8:
                $const['value'] = "__HOLE__";
                break;
            default:
                $const['value'] = "__ERROR__";
                break;
        }
        return $const;
    }

    public function XDRInterpretedFunction()
    {
        $result = ['name' => ''];
        $firstword = $this->todec();
        if ($firstword & Constant::_FirstWordFlag['HasAtom']) {
            $result['name'] = $this->XDRAtom();
        }
        $flagsword = $this->todec();
        if ($firstword & Constant::_FirstWordFlag['IsLazy']) {
            $this->XDRLazyScript();
            $result['type'] = 'lazy';
        } else {
            $result['type'] = 'block';
            $result['contextIndex'] = $this->XDRScript()->index;
        }
        return $result;
    }

    public function XDRLazyFreeVariables()
    {
        //for 0 -> numFreeVariables
        //$atom=$this->XDRAtom();
    }

    public function XDRLazyScript()
    {
        //XDRLazyScript
        $begin = $this->todec();
        $end = $this->todec();
        $lineno = $this->todec();
        $column = $this->todec();
        $packedFields = $this->todec(8);
        $this->XDRLazyFreeVariables();
    }
}
