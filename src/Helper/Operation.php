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
    protected $logicStacks = [];

    protected function popControlStack($type)
    {
        $type .= 'Stacks';
        return array_pop($this->$type);
    }

    protected function pushControlStack($type, $data = [])
    {
        $type .= 'Stacks';
        array_push($this->$type, $data);
    }

    protected function gotoNextOperation($nextOperation)
    {
        while (isset($nextOperation['params']['offset'])) {
            $goto = $nextOperation['parserIndex'] + $nextOperation['params']['offset'];
            if (!isset($this->operations[$goto])) {
                return false;
            }
            $nextOperation = $this->operations[$goto];
        }
        return $nextOperation;
    }

    public function getNextOperation($operation)
    {
        $nextIndex = $operation['parserIndex'] + $operation['length'];
        if (!isset($this->operations[$nextIndex])) {
            return false;
        }
        return $this->operations[$nextIndex];
    }

    public function revealOperations($start, $end, $conditons = [], $isCover = true)
    {
        $operationKeys = array_keys($this->operations);
        $operationKeysFlip = array_flip($operationKeys);
        $start = $operationKeysFlip[$start];
        $end = $operationKeysFlip[$end];
        for ($i = $start; $i < $end; $i++) {
            $operation = $this->operations[$operationKeys[$i]];
            if (!$operation['isCover']) {
                $this->revealOperation($operation, $conditons);
                $this->operations[$operationKeys[$i]]['isCover'] = $isCover;
            }
        }
    }

    protected function hasOperation($start, $end, $name)
    {
        $operationKeys = array_keys($this->operations);
        $operationKeysFlip = array_flip($operationKeys);
        $start = $operationKeysFlip[$start];
        $end = $operationKeysFlip[$end];
        for ($i = $start + 1; $i < $end; $i++) {
            $operation = $this->operations[$operationKeys[$i]];
            if ($operation['name'] == $name) {
                return true;
            }
        }
        return false;
    }

    protected function findFirstOperationIndex($name, $start = null, $end = null)
    {
        $operationKeys = array_keys($this->operations);
        $operationKeysFlip = array_flip($operationKeys);
        $start = is_null($start) ? 0 : $operationKeysFlip[$start];
        $end = is_null($end) ? count($operationKeys) : $operationKeysFlip[$end];
        for ($i = $start; $i < $end; $i++) {
            $operation = $this->operations[$operationKeys[$i]];
            if ($operation['name'] == $name) {
                return $operationKeys[$i];
            }
        }
        return false;
    }

    public function revealOperation($operation, $conditons = [])
    {
        $op = Constant::_Opcode[$operation['id']];
        switch ($op['op']) {
            case 'JSOP_RETRVAL':
            case 'JSOP_ENDINIT':
            case 'JSOP_NOP':
                break;
            case 'JSOP_RETURN':
                $val = $this->popStack();
                $this->writeScript($operation['parserIndex'], 'return ' . $val->getValue() . ';');
                break;
            //change stack
            case 'JSOP_POP':
            case 'JSOP_SETRVAL':
                $rVal = $this->popStack();
                if ($rVal) {
                    $this->writeScript($operation['parserIndex'], $rVal->getValue() . ';');
                }
                break;
            case 'JSOP_POPN':
                $n = $operation['params']['n'];
                for ($i = 0; $i < $n; $i++) {
                    $this->popStack();
                }
                break;
            case 'JSOP_DUP':
                $val = $this->popStack();
                $this->pushStack($val);
                $this->pushStack($val);
                break;
            case 'JSOP_DUP2':
                $val1 = $this->popStack();
                $val2 = $this->popStack();
                $this->pushStack($val2);
                $this->pushStack($val1);
                $this->pushStack($val2);
                $this->pushStack($val1);
                break;
            case 'JSOP_DUPAT':
                $number = $operation['params']['n'];
                $temp = [];
                for ($i = 0; $i < $number; $i++) {
                    $temp[$i] = $this->popStack();
                }
                for ($i = $number - 1; $i >= 0; $i--) {
                    $this->pushStack($temp[$i]);
                }
                $this->pushStack($temp[$number - 1]);
                break;
            case 'JSOP_PICK':
                $number = $operation['params']['n'];
                $temp = [];
                for ($i = 0; $i < $number; $i++) {
                    $temp[$i] = $this->popStack();
                }
                $nTh = $this->popStack();
                for ($i = $number - 1; $i >= 0; $i--) {
                    $this->pushStack($temp[$i]);
                }
                $this->pushStack($nTh);
                break;
            case 'JSOP_SWAP':// v1, v2 => v2, v1
                $val1 = $this->popStack();
                $val2 = $this->popStack();
                $this->pushStack($val1);
                $this->pushStack($val2);
                break;
            //math
            case 'JSOP_ADD':
            case 'JSOP_SUB':
            case 'JSOP_MUL':
            case 'JSOP_DIV':
            case 'JSOP_MOD':
            case 'JSOP_BITOR':
            case 'JSOP_BITXOR':
            case 'JSOP_BITAND':
            case 'JSOP_RSH':
            case 'JSOP_LSH':
            case 'JSOP_URSH':
                $right = $this->popStack();
                $left = $this->popStack();
                $this->pushStack(['value' => '(' . $left->getValue() . $operation['image'] . $right->getValue() . ')', 'type' => 'script']);
                break;
            case 'JSOP_BITNOT':
            case 'JSOP_NEG':
                $val = $this->popStack();
                $this->pushStack(['value' => '(' . $operation['image'] . $val->getValue() . ')', 'type' => 'script']);
                break;
            case 'JSOP_POS':
                break;
            //control goto
            case 'JSOP_GOTO':
                if (!empty($conditons)) {
                    if (isset($conditons['type']) && $conditons['type'] == 'switch') {
                        $gotoIndex = $operation['parserIndex'] + $operation['params']['offset'];
                        if ($gotoIndex >= $conditons['defaultIndex']) {
                            $this->writeScript($operation['parserIndex'], 'break;');
                            $this->storageScript[$gotoIndex * 2 + 1] = ['value' => '}'];
                            return;
                        }
                    }
                }
                if ($operation['params']['offset'] < 0) {
                    $nextOperation = $this->gotoNextOperation($operation);
                    $nextOp = Constant::_Opcode[$nextOperation['id']];
                    if ($nextOp['op'] == 'JSOP_LOOPENTRY') {
                        $this->writeScript($operation['parserIndex'], 'continue;');
                        return;
                    }
                }
                break;
            //control branch
            case 'JSOP_IFEQ':
                $value = $this->getLogicValue($this->popStack());
                //storage stack for k=x?a:b
                $stack = $this->stack;
                $this->writeScript($operation['parserIndex'], 'if(' . $value . '){');
                $elseStart = $this->gotoNextOperation($operation);
                $this->revealOperations($operation['parserIndex'] + $operation['length'], $elseStart['parserIndex']);
                $this->writeScriptEndings($operation['parserIndex'] + $operation['params']['offset'], '}');
                $this->writeScript($elseStart['parserIndex'], 'else');
                $oldCount = count($stack);
                $newCount = count($this->stack);
                if ($oldCount != $newCount) {
                    try {
                        if (($newCount - $oldCount) != 1) {
                            throw new \Exception("[error] JSOP_IFEQ Unknown Type");
                        }
                        $this->dropScript($operation['parserIndex']);
                        $this->dropScript($elseStart['parserIndex']);
                        $this->dropScriptEndings($operation['parserIndex'] + $operation['params']['offset']);
                        $gotoIndex = $operation['parserIndex'] + $operation['params']['offset'] - 5;
                        $gotoOperation = $this->operations[$gotoIndex];
                        $setIndex = $gotoIndex + $gotoOperation['params']['offset'];
                        $setOperation = $this->operations[$setIndex];
                        $stack = $this->stack;
                        switch ($setOperation['name']) {
                            case 'JSOP_SETELEM':
                            case 'JSOP_SETNAME':
                            case 'JSOP_SETCONST':
                            case 'JSOP_SETPROP':
                                $this->revealOperation($setOperation);
                                break;
                            default:
                                $endIndex = $this->findFirstOperationIndex('JSOP_SETRVAL', $setIndex);
                                if (!$endIndex) {
                                    throw new \Exception("[error] JSOP_IFEQ Unknown setOperation " . $setOperation['name']);
                                }
                                $this->revealOperations($setIndex, $endIndex, [], false);
                                break;
                        }
                        $script = $this->popStack();
                        $this->writeScript($operation['parserIndex'], $value . '?' . $script->getValue() . ':');
                        $this->stack = $stack;
                        $this->popStack();
                    } catch (\Exception $e) {
                        $this->writeScript($operation['parserIndex'] - 1, $e);
                        $this->stack = $stack;
                    }
                    return;
                }
                if (isset($this->operations[$elseStart['parserIndex'] - 5]) && $this->operations[$elseStart['parserIndex'] - 5]['name'] == 'JSOP_GOTO') {
                    $goto = $this->operations[$elseStart['parserIndex'] - 5];
                    if ($goto['params']['offset'] > 0) {
                        if ($this->hasOperation($goto['parserIndex'], $goto['parserIndex'] + $goto['params']['offset'], 'JSOP_LOOPENTRY')) {
                            $this->writeScript($goto['parserIndex'], 'break;');
                        } else {
                            $this->writeScript($goto['parserIndex'] + $goto['params']['offset'], '}');
                        }
                    }
                }
                break;
            case 'JSOP_TABLESWITCH':
                //case type is int
                $val = $this->popStack();
                $this->writeScript($operation['parserIndex'], 'switch(' . $val->getValue() . '){');
                $caseCurrent = $operation['params']['low'];
                $contents = [];
                foreach ($operation['params']['offset'] as $offset) {
                    if ($offset) {
                        $contentIndex = $operation['parserIndex'] + $offset;
                        if (!isset($contents[$contentIndex])) {
                            $contents[$contentIndex] = [];
                        }
                        $contents[$contentIndex][] = ['value' => 'case ' . $caseCurrent . ':'];
                    }
                    $caseCurrent++;
                }
                $defaultIndex = $operation['parserIndex'] + $operation['params']['len'];
                $this->_renderSwitch($contents, $defaultIndex);
                break;
            //control branch switch case type is mix
            case 'JSOP_CONDSWITCH':
                $name = $this->popStack();
                $this->writeScript($operation['parserIndex'], 'switch(' . $name->getValue() . '){');
                $this->pushStack(['type' => 'switch', 'value' => []]);
                break;
            case 'JSOP_CASE':
                $val = $this->popStack();
                $switch = $this->popStack();
                $contentIndex = $operation['parserIndex'] + $operation['params']['offset'];
                //var_dump($switch);
                if (!isset($switch->value[$contentIndex])) {
                    $switch->value[$contentIndex] = [];
                }
                $switch->value[$contentIndex][] = ['value' => 'case ' . $val->getValue() . ':'];
                $this->pushStack($switch);
                break;
            case 'JSOP_DEFAULT':
                $switch = $this->popStack();
                $this->_renderSwitch($switch->value, $operation['parserIndex'] + $operation['params']['offset']);
                break;
            //control loop
            case 'JSOP_IFNE':
                $value = $this->getLogicValue($this->popStack());
                $this->writeScript($operation['parserIndex'], 'while(' . $value . ')');
                break;
            case 'JSOP_LOOPHEAD':
                $this->writeScript($operation['parserIndex'], '{');
                break;
            case 'JSOP_LOOPENTRY':
                $this->writeScript($operation['parserIndex'], 'JSOP_LOOPENTRY');
                break;
            //For-In Statement
            case 'JSOP_ITER':
                $val = $this->popStack();
                $this->pushStack($val);
                break;
            case 'JSOP_ITERNEXT':
                $this->pushStack(['type' => 'script', 'value' => '@iternext']);
                break;
            case 'JSOP_MOREITER':
                $val = $this->popStack();
                $this->pushStack([]);
                $this->pushStack(['type' => 'script', 'value' => $val->value . ' has @iternext']);
                break;
            case 'JSOP_ENDITER':
                $val = $this->popStack();
                break;
            //logic
            case 'JSOP_EQ':
            case 'JSOP_NE':
            case 'JSOP_LT':
            case 'JSOP_LE':
            case 'JSOP_GT':
            case 'JSOP_GE':
            case 'JSOP_STRICTEQ':
            case 'JSOP_STRICTNE':
            case 'JSOP_IN':
                $right = $this->popStack();
                $left = $this->popStack();
                $this->pushStack(['value' => $left->getValue() . ' ' . $operation['image'] . ' ' . $right->getValue(), 'type' => 'script']);
                break;
            case 'JSOP_OR':
                $script = $this->popStack();
                $gotoIndex = $operation['parserIndex'] + $operation['params']['offset'];
                $this->logicStacks[$operation['parserIndex']] = ['type' => 'or', 'goto' => $gotoIndex, 'value' => $script->getValue()];
                $this->pushStack([]);
                break;
            case 'JSOP_AND':
                $script = $this->popStack();
                $gotoIndex = $operation['parserIndex'] + $operation['params']['offset'];
                $this->logicStacks[$operation['parserIndex']] = ['type' => 'and', 'goto' => $gotoIndex, 'value' => $script->getValue()];
                $this->pushStack([]);
                break;
            case 'JSOP_NOT':
                $script = $this->popStack();
                $this->logicStacks[$operation['parserIndex']] = ['type' => 'not', 'value' => $script->getValue()];
                $this->pushStack([]);
                break;
            //typeof
            case 'JSOP_TYPEOF':
            case 'JSOP_TYPEOFEXPR':
                $name = $this->popStack();
                $this->pushStack(['value' => 'typeof ' . $name->getValue(), 'type' => 'script']);
                break;
            //instanceof
            case 'JSOP_INSTANCEOF':
                $rVal = $this->popStack();
                $lVal = $this->popStack();
                $this->pushStack(['value' => $lVal->getValue() . ' instanceof ' . $rVal->getValue(), 'type' => 'script']);
                break;
            //Function
            case 'JSOP_FUNAPPLY'://apply
            case 'JSOP_FUNCALL'://call
            case 'JSOP_EVAL'://eval
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
                $write = $_callee->value . '(';
                if ($_argc) {
                    for ($i = $_argc - 1; $i >= 0; $i--) {
                        $write .= $_argv[$i]->getValue() . ',';
                    }
                    $write = substr($write, 0, strlen($write) - 1);
                }
                $write .= ')';
                $this->pushStack(['type' => 'script', 'value' => $write]);
                break;
            case 'JSOP_NEW':
                $_argc = $operation['params']['argc'];
                $_argv = [];
                for ($i = 0; $i < $_argc; $i++) {
                    $_argv[] = $this->popStack();
                }
                $_this = $this->popStack();
                $_callee = $this->popStack();
                $write = 'new ' . $_callee->value . '(';
                if ($_argc) {
                    for ($i = $_argc - 1; $i >= 0; $i--) {
                        $write .= $_argv[$i]->getValue() . ',';
                    }
                    $write = substr($write, 0, strlen($write) - 1);
                }
                $write .= ');';
                $this->pushStack(['value' => $write, 'type' => 'script']);
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
            case 'JSOP_INITELEM_INC':
                $val = $this->popStack();
                $name = $this->popStack();
                $array = $this->popStack();
                $array->value[$name->value] = $val->value;
                $this->pushStack($array);
                break;
            case 'JSOP_SETELEM':
                $value = $this->getLogicValue($this->popStack());
                $propval = $this->popStack();
                $obj = $this->popStack();
                $this->pushStack(['type' => 'script', 'value' => $obj->value . '[' . $propval->getValue() . ']=' . $value]);
                break;
            case 'JSOP_CALLPROP':
                $name = $this->popStack();
                var_dump($name);
                $this->pushStack(['type' => 'script', 'value' => $name->value . '.' . $this->atoms[$operation['params']['nameIndex']]]);
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
            case 'JSOP_LENGTH':
                $name = $this->popStack();
                $this->pushStack(['type' => 'script', 'value' => $name->value . '.length']);
                break;
            //压入变量 ['isJson'=>false,'value'=>'xxxx']
            case 'JSOP_NAME':
            case 'JSOP_BINDNAME':
            case 'JSOP_IMPLICITTHIS':
                $this->pushStack(['value' => $this->atoms[$operation['params']['nameIndex']], 'type' => '__var__']);
                break;
            //定义变量
            case 'JSOP_DEFVAR':
                $this->writeScript($operation['parserIndex'], 'var ' . $this->atoms[$operation['params']['nameIndex']] . ';');
                break;
            case 'JSOP_DEFCONST':
                $this->writeScript($operation['parserIndex'], 'const ' . $this->atoms[$operation['params']['nameIndex']] . ';');
                break;
            case 'JSOP_DEFFUN':
                $object = $this->objects[$operation['params']['funcIndex']];
                $this->writeScript($operation['parserIndex'], 'function ' . $object['name'] . '(){ __INDEX_' . $object['contextIndex'] . '__ }');
                break;
            //定义常量 ['isJson'=>false,'value'=>'xxxx']
            case 'JSOP_TRUE':
                $this->pushStack(['value' => 'true', 'type' => 'true']);
                break;
            case 'JSOP_FALSE':
                $this->pushStack(['value' => 'false', 'type' => 'false']);
                break;
            case 'JSOP_NULL':
                $this->pushStack(['value' => 'null', 'type' => 'null']);
                break;
            case 'JSOP_VOID':
                $val = $this->popStack();
            case 'JSOP_UNDEFINED':
                $this->pushStack(['value' => 'undefined', 'type' => 'undefined']);
                break;
            case 'JSOP_ZERO':
                $this->pushStack(['value' => 0, 'type' => 'number']);
                break;
            case 'JSOP_ONE':
                $this->pushStack(['value' => 1, 'type' => 'number']);
                break;
            case 'JSOP_INT8':
            case 'JSOP_UINT16':
            case 'JSOP_UINT24':
            case 'JSOP_INT32':
                $this->pushStack(['value' => $operation['params']['val'], 'type' => 'number']);
                break;
            case 'JSOP_DOUBLE':
                $this->pushStack(['value' => $this->consts[$operation['params']['constIndex']]['value'], 'type' => 'number']);
                break;
            case 'JSOP_STRING':
                $this->pushStack(['isJson' => true, 'value' => $this->atoms[$operation['params']['atomIndex']], 'type' => 'string']);
                break;
            case 'JSOP_REGEXP':
                $this->pushStack(['value' => '/' . $this->regexps[$operation['params']['regexpIndex']]['source'] . '/', 'type' => 'regexp']);
                break;
            case 'JSOP_OBJECT'://todo
                $this->pushStack(['isJson' => true, 'value' => [], 'type' => 'object']);
                break;
            case 'JSOP_LAMBDA_ARROW':
                $val = $this->popStack();
                $object = $this->objects[$operation['params']['funcIndex']];
                $this->pushStack(['value' => '() => { __INDEX_' . $object['contextIndex'] . '__ }', 'type' => 'function']);
                break;
            case 'JSOP_LAMBDA':
                $object = $this->objects[$operation['params']['funcIndex']];
                $this->pushStack(['value' => 'function ' . $object['name'] . '(){ __INDEX_' . $object['contextIndex'] . '__ }', 'type' => 'function']);
                break;
            //赋值
            case 'JSOP_SETNAME':
                $value = $this->getLogicValue($this->popStack());
                $name = $this->popStack();
                $this->pushStack(['value' => $name->value . ' = ' . $value, 'type' => 'script']);
                break;
            case 'JSOP_SETCONST':
                $value = $this->getLogicValue($this->popStack());
                $name = $this->atoms[$operation['params']['nameIndex']];
                $this->pushStack(['value' => $name->value . ' = ' . $value, 'type' => 'script']);
                break;
            //delete
            case 'JSOP_DELNAME':
                $this->pushStack(['type' => 'script', 'value' => 'delete ' . $this->atoms[$operation['params']['nameIndex']]]);
                break;
            //todo list
            //Other
            case 'JSOP_TOSTRING':
                $val = $this->popStack();
                $this->pushStack([]);
                break;
            case 'JSOP_LINENO':
                break;
            //With Statement
            case 'JSOP_ENTERWITH':
                $val = $this->popStack();
                break;
            case 'JSOP_LEAVEWITH':
                break;
            //Arguments
            case 'JSOP_CALLEE':
                $this->pushStack([]);
                break;
            case 'JSOP_REST':
                $this->pushStack([]);
                break;
            case 'JSOP_ARGUMENTS':
                $this->pushStack([]);
                break;
            case 'JSOP_GETARG':
                //todo try to get arguments
                $this->pushStack(['type' => 'script', 'value' => '__ARG_' . $operation['params']['argno'] . '__']);
                break;
            case 'JSOP_SETARG':
                $val = $this->popStack();
                $this->pushStack([]);
                break;
            //Array
            case 'JSOP_NEWARRAY_COPYONWRITE':
                $this->pushStack([]);
                break;
            case 'JSOP_HOLE':
                $this->pushStack([]);
                break;
            case 'JSOP_ARRAYPUSH':
                $val = $this->popStack();
                $array = $this->popStack();
                break;
            //Object
            case 'JSOP_TOID':
                $val = $this->popStack();
                $this->pushStack([]);
                break;
            case 'JSOP_MUTATEPROTO':
                $val = $this->popStack();
                $val = $this->popStack();
                $this->pushStack([]);
                break;
            case 'JSOP_CALLELEM':
                $propval = $this->popStack();
                $obj = $this->popStack();
                $this->pushStack(['type' => 'script', 'value' => $obj->value . '[' . $propval->getValue() . ']']);
                break;
            case 'JSOP_GETXPROP':
                $val = $this->popStack();
                $this->pushStack([]);
                break;
            case 'JSOP_CALLSITEOBJ':
                $this->pushStack([]);
                break;
            case 'JSOP_INITPROP_GETTER':
                $val = $this->popStack();
                $val = $this->popStack();
                $this->pushStack([]);
                break;
            case 'JSOP_INITPROP_SETTER':
                $val = $this->popStack();
                $val = $this->popStack();
                $this->pushStack([]);
                break;
            case 'JSOP_INITELEM_GETTER':
                $val = $this->popStack();
                $val = $this->popStack();
                $val = $this->popStack();
                $this->pushStack([]);
                break;
            case 'JSOP_INITELEM_SETTER':
                $val = $this->popStack();
                $val = $this->popStack();
                $val = $this->popStack();
                $this->pushStack([]);
                break;
            case 'JSOP_NEWOBJECT':
                $this->pushStack(['isJson' => true, 'value' => [], 'type' => 'object']);
                break;
            case 'JSOP_DELPROP':
                $obj = $this->popStack();
                $this->pushStack(['type' => 'script', 'value' => 'delete ' . $obj->value . '.' . $this->atoms[$operation['params']['nameIndex']]]);
                break;
            case 'JSOP_DELELEM':
                $propval = $this->popStack();
                $obj = $this->popStack();
                $this->pushStack(['type' => 'script', 'value' => 'delete ' . $obj->value . '[' . $propval->getValue() . ']']);
                break;
            case 'JSOP_GETPROP':
                $obj = $this->popStack();
                $this->pushStack(['type' => 'script', 'value' => $obj->value . '.' . $this->atoms[$operation['params']['nameIndex']]]);
                break;
            case 'JSOP_SETPROP':
                $value = $this->getLogicValue($this->popStack());
                $name = $this->popStack();
                $this->pushStack(['type' => 'script', 'value' => $name->value . '.' . $this->atoms[$operation['params']['nameIndex']] . '=' . $value]);
                break;
            case 'JSOP_GETELEM':
                $propval = $this->popStack();
                $obj = $this->popStack();
                $this->pushStack(['type' => 'script', 'value' => $obj->value . '[' . $propval->getValue() . ']']);
                break;
            //This
            case 'JSOP_THIS':
                $this->pushStack(['type' => 'script', 'value' => 'this']);
                break;
            //Function
            case 'JSOP_SETCALL':
                break;
            case 'JSOP_RUNONCE':
                break;
            case 'JSOP_SPREADCALL':
                $val = $this->popStack();
                $val = $this->popStack();
                $val = $this->popStack();
                $this->pushStack([]);
                break;
            case 'JSOP_SPREADNEW':
                $val = $this->popStack();
                $val = $this->popStack();
                $val = $this->popStack();
                $this->pushStack([]);
                break;
            case 'JSOP_SPREADEVAL':
                $val = $this->popStack();
                $val = $this->popStack();
                $val = $this->popStack();
                $this->pushStack([]);
                break;
            //Free Variables
            case 'JSOP_BINDGNAME':
                $this->pushStack([]);
                break;
            case 'JSOP_GETGNAME':
                $this->pushStack([]);
                break;
            case 'JSOP_SETGNAME':
                $val = $this->popStack();
                $val = $this->popStack();
                $this->pushStack([]);
                break;
            //Local Variables
            case 'JSOP_GETLOCAL'://todo
                $localno = $operation['params']['localno'];
                $this->pushStack($this->decompile->getLocalVariable($localno));
                break;
            case 'JSOP_SETLOCAL'://todo
                $value = $this->getLogicValue($this->popStack());
                $localno = $operation['params']['localno'];
                $localVar = ['type' => 'localVar', 'value' => '_local' . $localno];
                $this->decompile->setLocalVariable($localno, $localVar);
                $this->pushStack(['type' => 'script', 'value' => $localVar['value'] . '=' . $value]);
                break;
            //Generator
            case 'JSOP_GENERATOR':
                break;
            case 'JSOP_YIELD':
                $val = $this->popStack();
                $this->pushStack(['type' => 'script', 'value' => 'yield ' . $val->getValue()]);
                break;
            //Aliased Variables
            case 'JSOP_GETALIASEDVAR'://todo
                $aliasedVar = $this->decompile->getAliasedVariable($operation['params']['hops'], $operation['params']['slot']);
                $this->pushStack($aliasedVar);
                break;
            case 'JSOP_SETALIASEDVAR'://todo
                $value = $this->getLogicValue($this->popStack());
                $aliasedVar = ['type' => 'aliasedVar', 'value' => '_aliased' . rand(1000, 9999)];
                $this->decompile->setAliasedVariable($operation['params']['hops'], $operation['params']['slot'], $aliasedVar);
                $this->pushStack(['type' => 'script', 'value' => $aliasedVar['value'] . '=' . $value]);
                break;
            //Exception Handling
            case 'JSOP_TRY':
                $this->writeScript($operation['parserIndex'], 'try{');
                break;
            case 'JSOP_THROW':
                $val = $this->popStack();
                $this->writeScript($operation['parserIndex'], 'throw ' . $val->getValue());
                break;
            case 'JSOP_GOSUB':
                break;
            case 'JSOP_EXCEPTION':
                $exception = ['type' => 'exception', 'value' => '@error'];
                $this->writeScript($operation['parserIndex'], '}catch(' . $exception['value'] . '){');
                $this->pushStack($exception);
                break;
            case
            'JSOP_DEBUGLEAVEBLOCK':
                $this->writeScript($operation['parserIndex'], '}
        ');
                break;
            case 'JSOP_FINALLY':
                $this->writeScript($operation['parserIndex'], 'finally{
        ');
                $this->pushStack([]);
                $this->pushStack([]);
                break;
            case 'JSOP_RETSUB':
                $this->writeScript($operation['parserIndex'], '}');
                $val = $this->popStack();
                $val = $this->popStack();
                break;
            //todo
            case 'JSOP_DEBUGGER':
                break;
            case 'JSOP_THROWING':
                $val = $this->popStack();
                break;
            //Block-local Scope
            case 'JSOP_POPBLOCKSCOPE':
                break;
            case 'JSOP_PUSHBLOCKSCOPE':
                break;
            //Jumps
            case 'JSOP_BACKPATCH':
                break;
            case 'JSOP_LABEL':
                break;
            //Intrinsics
            case 'JSOP_BINDINTRINSIC':
                $this->pushStack([]);
                break;
            case 'JSOP_GETINTRINSIC':
                $this->pushStack([]);
                break;
            case 'JSOP_SETINTRINSIC':
                $val = $this->popStack();
                $val = $this->popStack();
                $this->pushStack([]);
                break;

        }
    }

    protected function _renderSwitch($contents, $defaultIndex)
    {
        $contentEndings = array_keys($contents);
        $contentEndings[] = $defaultIndex;
        $i = 1;
        foreach ($contents as $start => $content) {
            $caseWrite = '';
            foreach ($content as $case) {
                $caseWrite .= $case['value'];
            }
            $this->writeScript($start, $caseWrite);
            $this->revealOperations($start, $contentEndings[$i], ['type' => 'switch', 'defaultIndex' => $defaultIndex,]);
            $i++;
        }
        $this->writeScript($defaultIndex, 'default:');
    }

    protected function getLogicValue(Stack $val)
    {
        $value = $val->getValue();
        if (!empty($this->logicStacks)) {
            $value = $this->_combineLogic() . $value;
        }
        return $value;
    }

    protected function _combineLogic()
    {
        ksort($this->logicStacks);
        while (count($this->logicStacks) > 1) {
            $this->_combineLogicUnit();
        }
        $result = array_pop($this->logicStacks);
        switch ($result['type']) {
            case 'not':
                $result['value'] = '!(' . $result['value'] . ')';
                break;
            case 'and':
                $result['value'] = $result['value'] . '&&';
                break;
            case 'or':
                $result['value'] = $result['value'] . '||';
                break;
        }
        return $result['value'];
    }

    protected function _combineLogicUnit()
    {
        $logicStackKeys = array_keys($this->logicStacks);
        $logicStackKeysCount = count($logicStackKeys);
        $tree = ['type' => 'script'];
        for ($i = 0; $i < $logicStackKeysCount; $i++) {
            $logicStack = $this->logicStacks[$logicStackKeys[$i]];
            if (
                is_null($logicStack['value']) &&
                ($preStack = $this->logicStacks[$logicStackKeys[$i - 1]]) &&
                $preStack['type'] == 'not'
            ) {
                $this->logicStacks[$logicStackKeys[$i]]['value'] = '!(' . $preStack['value'] . ')';
                unset($this->logicStacks[$logicStackKeys[$i - 1]]);
            } elseif (!isset($logicStackKeys[$i + 1])) {
                $preStack = $this->logicStacks[$logicStackKeys[$i - 1]];
                $this->logicStacks[$preStack['goto']] = $logicStack;
                unset($this->logicStacks[$logicStackKeys[$i]]);
            } elseif (!isset($logicStack['goto'])) {
                continue;
            } elseif ($logicStackKeys[$i + 1] == $logicStack['goto']) {
                $nextLogicStack = $this->logicStacks[$logicStackKeys[$i + 1]];
                $isNot = $nextLogicStack['type'] == 'not' ? '!' : '';
                switch ($logicStack['type']) {
                    case 'and':
                        $tree['value'] = $isNot . '(' . $logicStack['value'] . ' && ' . $nextLogicStack['value'] . ')';
                        break;
                    case 'or':
                        $tree['value'] = $isNot . '(' . $logicStack['value'] . ' || ' . $nextLogicStack['value'] . ')';
                        break;
                    case 'script':
                        $tree['value'] = $logicStack['value'] . $nextLogicStack['value'];
                        break;
                }
                if (isset($nextLogicStack['goto'])) {
                    $tree['goto'] = $nextLogicStack['goto'];
                    $tree['type'] = $nextLogicStack['type'];
                }
                unset($this->logicStacks[$logicStackKeys[$i]]);
                $this->logicStacks[$logicStackKeys[$i + 1]] = $tree;
                break;
            }
        }
    }
}
