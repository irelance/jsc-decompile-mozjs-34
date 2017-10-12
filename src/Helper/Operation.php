<?php
/**
 * Created by PhpStorm.
 * User: irelance
 * Date: 2017/10/11
 * Time: 上午11:06
 */

namespace Irelance\Mozjs34\Helper;


use Irelance\Mozjs34\Constant;

/**
 * @method Stack popStack()
 */
trait Operation
{
    public function revealOperation($operation)
    {
        $op = Constant::_Opcode[$operation['id']];
        switch ($op['op']) {
            case 'JSOP_POP':
            case 'JSOP_SETRVAL':
                $rVal = $this->popStack();
                if ($rVal->type == 'script') {
                    $this->writeScript($rVal->value);
                }
                break;
            case 'JSOP_RETRVAL':
            case 'JSOP_ENDINIT':
                break;
            case 'JSOP_RETURN':
                $val = $this->popStack();
                $this->writeScript('return ' . $val->getValue() . ';');
                break;
            //control
            case 'JSOP_IFEQ':
                $val = $this->popStack();
                $this->writeScript('return ' . $val->getValue() . ';');
                break;
            //logic
            case 'JSOP_EQ':
                $right = $this->popStack();
                $left = $this->popStack();
                $this->pushStack(['value' => $left->getValue() . '==' . $right->getValue(), 'type' => 'script']);
                break;
            //typeof
            case 'JSOP_TYPEOF':
            case 'JSOP_TYPEOFEXPR':
                $name = $this->popStack();
                $this->pushStack(['value' => 'typeof ' . $name->getValue(), 'type' => 'script']);
                break;
            //Function
            case 'JSOP_CALL':
                $_argc = $operation['params']['argc'];
                $_argv = [];
                for ($i = 0; $i < $_argc; $i++) {
                    $_argv[] = $this->popStack();
                }
                $_this = $this->popStack();
                $_callee = $this->popStack();
                if ($_callee->type == 'function') {
                    $_callee->value = '(' . $_callee->value . ')';
                }
                $this->pushStack([]);
                $write = $_callee->value . '(';
                if ($_argc) {
                    for ($i = $_argc - 1; $i >= 0; $i--) {
                        $write .= $_argv[$i]->getValue() . ',';
                    }
                    $write = substr($write, 0, strlen($write) - 1);
                }
                $write .= ');';
                $this->writeScript($write);
                break;
            //Object
            case 'JSOP_NEWINIT':
                $this->pushStack(['isJson' => true, 'value' => [], 'type' => 'object']);
                break;
            case 'JSOP_INITPROP':
                $name = $this->atoms[$operation['params']['nameIndex']];
                $val = $this->popStack();
                $array = $this->popStack();
                $array->value[$name] = $val->value;
                $this->pushStack($array);
                break;
            case 'JSOP_INITELEM':
                $val = $this->popStack();
                $name = $this->popStack();
                $array = $this->popStack();
                $array->value[$name->value] = $val->value;
                $this->pushStack($array);
                break;
            //Array
            case 'JSOP_NEWARRAY':
                $this->pushStack(['isJson' => true, 'value' => [], 'type' => 'object']);
                break;
            case 'JSOP_INITELEM_ARRAY':
                $val = $this->popStack();
                $array = $this->popStack();
                $array->value[] = $val->value;
                $this->pushStack($array);
                break;
            //压入变量 ['isJson'=>false,'value'=>'xxxx']
            case 'JSOP_NAME':
            case 'JSOP_BINDNAME':
            case 'JSOP_IMPLICITTHIS':
                $this->pushStack(['value' => $this->atoms[$operation['params']['nameIndex']], 'type' => '__var__']);
                break;
            //定义变量
            case 'JSOP_DEFVAR':
                $this->writeScript('var ' . $this->atoms[$operation['params']['nameIndex']] . ';');
                break;
            case 'JSOP_DEFCONST':
                $this->writeScript('const ' . $this->atoms[$operation['params']['nameIndex']] . ';');
                break;
            case 'JSOP_DEFFUN':
                $object = $this->objects[$operation['params']['funcIndex']];
                $this->writeScript('function ' . $object['name'] . '(){ __INDEX_' . $object['context']->index . '__ }');
                break;
            //定义常量 ['isJson'=>false,'value'=>'xxxx']
            case 'JSOP_UNDEFINED':
                $this->pushStack(['value' => js_undefined_str, 'type' => 'undefined']);
                break;
            case 'JSOP_ZERO':
                $this->pushStack(['value' => 0, 'type' => 'number']);
                break;
            case 'JSOP_ONE':
                $this->pushStack(['value' => 1, 'type' => 'number']);
                break;
            case 'JSOP_INT8':
                $this->pushStack(['value' => $operation['params']['val'], 'type' => 'number']);
                break;
            case 'JSOP_INT32':
                $this->pushStack(['value' => $operation['params']['val'], 'type' => 'number']);
                break;
            case 'JSOP_DOUBLE':
                $this->pushStack(['value' => $this->consts[$operation['params']['constIndex']]['value'], 'type' => 'number']);
                break;
            case 'JSOP_STRING':
                $this->pushStack(['isJson' => true, 'value' => $this->atoms[$operation['params']['atomIndex']], 'type' => 'string']);
                break;
            case 'JSOP_LAMBDA':
                $object = $this->objects[$operation['params']['funcIndex']];
                $this->pushStack(['value' => 'function ' . $object['name'] . '(){ __INDEX_' . $object['context']->index . '__ }', 'type' => 'function']);
                break;
            //赋值
            case 'JSOP_SETNAME':
                $val = $this->popStack();
                $name = $this->popStack();
                $this->writeScript($name->value . '=' . $val->getValue() . ';');
                $this->pushStack($val);
                break;
            case 'JSOP_SETCONST':
                $val = $this->popStack();
                $name = $this->atoms[$operation['params']['nameIndex']];
                $this->writeScript($name . '=' . $val->getValue() . ';');
                $this->pushStack($val);
                break;
        }
    }
}
