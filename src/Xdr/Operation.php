<?php
/**
 * Created by PhpStorm.
 * User: irelance
 * Date: 2017/10/11
 * Time: 上午11:21
 */

namespace Irelance\Mozjs34\Xdr;

use Irelance\Mozjs34\Constant;

trait Operation
{
    public function parserOperation()
    {
        $op = Constant::_Opcode[$this->bytecodes[$this->parseIndex]];
        $result = [
            'id' => $op['val'],
            'name' => $op['op'],
            'parserIndex' => $this->parseIndex,
            'params' => [],
            'length' => $op['len'],
            'image' => $op['image'],
            'push' => $op['def'],
            'pop' => $op['use'],
            'isCover' => false,
        ];
        $this->parseIndex++;
        switch ($op['op']) {
            case 'JSOP_ENTERWITH':
                $result['params']['staticWithIndex'] = $this->bigEndian2Dec(4);
                break;
            case 'JSOP_GOTO':
            case 'JSOP_IFEQ':
            case 'JSOP_IFNE':
            case 'JSOP_OR':
            case 'JSOP_AND':
            case 'JSOP_LABEL':
            case 'JSOP_GOSUB':
            case 'JSOP_CASE':
            case 'JSOP_DEFAULT':
            case 'JSOP_BACKPATCH':
                $result['params']['offset'] = $this->uInt32ToInt32($this->bigEndian2Dec(4));
                break;
            case 'JSOP_CALL':
            case 'JSOP_FUNAPPLY':
            case 'JSOP_NEW':
            case 'JSOP_FUNCALL':
            case 'JSOP_EVAL':
                $result['params']['argc'] = $this->bigEndian2Dec(2);
                break;
            case 'JSOP_GETARG':
            case 'JSOP_SETARG':
                $result['params']['argno'] = $this->bigEndian2Dec(2);
                break;
            case 'JSOP_PICK':
                $result['params']['n'] = $this->bigEndian2Dec(1);
                break;
            case 'JSOP_POPN':
                $result['params']['n'] = $this->bigEndian2Dec(2);
                break;
            case 'JSOP_DUPAT':
                $result['params']['n'] = $this->bigEndian2Dec(3);
                break;
            case 'JSOP_SETCONST':
            case 'JSOP_DELNAME':
            case 'JSOP_DELPROP':
            case 'JSOP_GETPROP':
            case 'JSOP_SETPROP':
            case 'JSOP_INITPROP':
            case 'JSOP_NAME':
            case 'JSOP_INITPROP_GETTER':
            case 'JSOP_INITPROP_SETTER':
            case 'JSOP_BINDNAME':
            case 'JSOP_SETNAME':
            case 'JSOP_DEFCONST':
            case 'JSOP_DEFVAR':
            case 'JSOP_GETINTRINSIC':
            case 'JSOP_SETINTRINSIC':
            case 'JSOP_BINDINTRINSIC':
            case 'JSOP_GETGNAME':
            case 'JSOP_SETGNAME':
            case 'JSOP_CALLPROP':
            case 'JSOP_GETXPROP':
            case 'JSOP_BINDGNAME':
            case 'JSOP_LENGTH':
            case 'JSOP_IMPLICITTHIS':
                $result['params']['nameIndex'] = $this->bigEndian2Dec(4);
                break;
            case 'JSOP_DOUBLE':
                $result['params']['constIndex'] = $this->bigEndian2Dec(4);
                break;
            case 'JSOP_STRING':
                $result['params']['atomIndex'] = $this->bigEndian2Dec(4);
                break;
            case 'JSOP_OBJECT':
            case 'JSOP_CALLSITEOBJ':
            case 'JSOP_NEWARRAY_COPYONWRITE':
                $result['params']['objectIndex'] = $this->bigEndian2Dec(4);
                break;
            case 'JSOP_DEFFUN':
            case 'JSOP_LAMBDA':
            case 'JSOP_LAMBDA_ARROW':
                $result['params']['funcIndex'] = $this->bigEndian2Dec(4);
                break;
            case 'JSOP_REGEXP':
                $result['params']['regexpIndex'] = $this->bigEndian2Dec(4);
                break;
            case 'JSOP_PUSHBLOCKSCOPE':
                $result['params']['staticBlockObjectIndex'] = $this->bigEndian2Dec(4);
                break;
            case 'JSOP_TABLESWITCH':
                $result['params']['len'] = $this->uInt32ToInt32($this->bigEndian2Dec(4));
                $result['params']['low'] = $this->uInt32ToInt32($this->bigEndian2Dec(4));
                $result['params']['high'] = $this->uInt32ToInt32($this->bigEndian2Dec(4));
                $result['params']['offset'] = [];
                $end = $result['params']['high'] - $result['params']['low'];
                for ($i = 0; $i <= $end; $i++) {
                    $result['params']['offset'][$i] = $this->uInt32ToInt32($this->bigEndian2Dec(4));
                }
                break;
            case 'JSOP_ITER':
                $result['params']['flags'] = $this->bigEndian2Dec(1);
                break;
            case 'JSOP_GETLOCAL'://todo uint32_t localno but op len is 4
            case 'JSOP_SETLOCAL'://todo uint32_t localno but op len is 4
                $result['params']['localno'] = $this->bigEndian2Dec(3);
                break;
            case 'JSOP_INT8':
                $result['params']['val'] = $this->uIntToInt($this->bigEndian2Dec(1), 8);
                break;
            case 'JSOP_UINT16':
                $result['params']['val'] = $this->bigEndian2Dec(2);
                break;
            case 'JSOP_UINT24':
                $result['params']['val'] = $this->bigEndian2Dec(3);
                break;
            case 'JSOP_INT32':
                $result['params']['val'] = $this->uInt32ToInt32($this->bigEndian2Dec(4));
                break;
            case 'JSOP_NEWINIT':
                $result['params']['kind'] = $this->bigEndian2Dec(1);
                $result['params']['extra'] = $this->bigEndian2Dec(3);
                break;
            case 'JSOP_NEWARRAY':
                $result['params']['length'] = $this->bigEndian2Dec(3);
                break;
            case 'JSOP_NEWOBJECT':
                $result['params']['baseobjIndex'] = $this->bigEndian2Dec(4);
                break;
            case 'JSOP_INITELEM_ARRAY':
                $result['params']['index'] = $this->bigEndian2Dec(3);
                break;
            case 'JSOP_LINENO':
                $result['params']['lineno'] = $this->bigEndian2Dec(2);
                break;
            case 'JSOP_GETALIASEDVAR':
            case 'JSOP_SETALIASEDVAR':
                $result['params']['hops'] = $this->bigEndian2Dec(1);
                $result['params']['slot'] = $this->bigEndian2Dec(3);
                break;
            case 'JSOP_LOOPENTRY':
                $result['params']['BITFIELD'] = $this->bigEndian2Dec(1);
                $result['params']['depth'] = $result['params']['BITFIELD'] - 128;
                break;
        }
        return $result;
    }
}
