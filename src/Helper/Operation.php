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
                if ($this->isDebug) {
                    echo '[', $operationKeys[$i], ']', $operation['name'], json_encode($operation['params']), ' pop: ', $operation['pop'], ' push: ', $operation['push'], ' byte: ', $operation['length'], CLIENT_EOL;
                }
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

    protected function findFirstOperation($name, $start = null, $end = null)
    {
        if (is_string($name)) {
            $name = [$name];
        }
        $operationKeys = array_keys($this->operations);
        $operationKeysFlip = array_flip($operationKeys);
        $start = is_null($start) ? 0 : $operationKeysFlip[$start];
        $end = is_null($end) ? count($operationKeys) : $operationKeysFlip[$end];
        for ($i = $start; $i < $end; $i++) {
            $operation = $this->operations[$operationKeys[$i]];
            if (in_array($operation['name'], $name)) {
                return $operation;
            }
        }
        return false;
    }

    public function revealOperation($operation, $conditons = [])
    {
        switch ($operation['name']) {
            case 'JSOP_RETRVAL':
            case 'JSOP_ENDINIT':
            case 'JSOP_NOP':
                break;
            case 'JSOP_RETURN':
                $value = $this->getLogicValue($operation, $this->popStack());
                $this->writeScript($operation['parserIndex'], 'return ' . $value . ';');
                break;
            //change stack
            case 'JSOP_POP':
            case 'JSOP_SETRVAL':
                $rVal = $this->popStack();
                if ($rVal && $script = $rVal->getScript()) {
                    $this->writeScript($operation['parserIndex'], $script . ';');
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
            //control goto
            case 'JSOP_GOTO':
                if (!empty($conditons)) {
                    if (isset($conditons['type']) && $conditons['type'] == 'switch') {
                        $gotoIndex = $operation['parserIndex'] + $operation['params']['offset'];
                        if ($gotoIndex >= $conditons['defaultIndex']) {
                            $this->writeScript($operation['parserIndex'], 'break;');
                            $this->writeScript($gotoIndex, '}', +1);
                            return;
                        }
                    }
                }
                $this->_getBranchContinue($operation) || $this->_getBranchBreak($operation);
                break;
            //control branch
            case 'JSOP_IFEQ':
                $value = $this->getLogicValue($operation, $this->popStack());
                //storage stack for k=x?a:b
                $stackCopy = serialize($this->stack);
                $this->appendScript($operation['parserIndex'], 'if(' . $value . '){');
                if ($operation['params']['offset'] > 0) {
                    $elseStart = $this->gotoNextOperation($operation);
                    $this->revealOperations($operation['parserIndex'] + $operation['length'], $elseStart['parserIndex']);
                    $gotoOperation = $this->_getBranchGoto($operation);
                    while ($this->_getBranchContinue($gotoOperation) || $this->_getBranchBreak($gotoOperation)) {
                        $gotoOperation = $this->findFirstOperation('JSOP_GOTO', $gotoOperation['parserIndex'] + $gotoOperation['length']);
                        if (!$gotoOperation) {
                            break;
                        }
                    }
                    $hasNextOperation = false;
                    $nextOperationIndex = false;
                    if ($gotoOperation) {
                        $hasNextOperation = $gotoOperation['name'];
                        $nextOperationIndex = $gotoOperation['parserIndex'];
                        if ($elseIfOperation = $this->findFirstOperation(
                            'JSOP_IFEQ',
                            $gotoOperation['parserIndex'],
                            $gotoOperation['parserIndex'] + $gotoOperation['params']['offset']
                        )
                        ) {
                            $hasNextOperation = $elseIfOperation['name'];
                            $nextOperationIndex = $elseIfOperation['parserIndex'];
                        }
                    }
                    switch ($hasNextOperation) {
                        case 'JSOP_IFEQ':
                            $this->writeScript($nextOperationIndex, '} else ');
                            break;
                        case 'JSOP_GOTO':
                            $this->writeScript($nextOperationIndex, '} else {');
                        default:
                            $this->appendScript($operation['parserIndex'] + $operation['params']['offset'], '}', +1);
                            break;
                    }
                    $oldCount = count(unserialize($stackCopy));
                    $newCount = count($this->stack);
                    if ($oldCount != $newCount) {
                        if (($newCount - $oldCount) != 1) {
                            exit("[error] JSOP_IFEQ Unknown Type");
                        }
                        $this->dropScript($nextOperationIndex);
                        $this->dropScript($operation['parserIndex']);
                        $this->dropScript($operation['parserIndex'] + $operation['params']['offset'], +1);
                        if (!$gotoOperation) {
                            exit("[error] JSOP_IFEQ No Goto ");
                        }
                        $setIndex = $gotoOperation['parserIndex'] + $gotoOperation['params']['offset'];
                        $setOperation = $this->operations[$setIndex];
                        switch ($setOperation['name']) {
                            case 'JSOP_SETELEM':
                            case 'JSOP_SETNAME':
                            case 'JSOP_SETCONST':
                            case 'JSOP_SETPROP':
                            case 'JSOP_INITPROP':
                                $endOperation = $this->getNextOperation($setOperation);
                                break;
                            default://todo find all ending operation
                                $endOperation = $this->findFirstOperation(
                                    ['JSOP_SETRVAL', 'JSOP_GOTO', 'JSOP_POP', 'JSOP_RETURN', 'JSOP_IFEQ', 'JSOP_IFNE',
                                    ],
                                    $setIndex
                                );
                                break;
                        }
                        if (!$endOperation) {
                            exit("[error] JSOP_IFEQ Unknown endOperation " . $operation['parserIndex']);
                        }
                        $this->revealOperations($setIndex, $endOperation['parserIndex'], [], false);
                        $left = $this->popStack();
                        $this->stack = unserialize($stackCopy);
                        $this->revealOperations($gotoOperation['parserIndex'] + 5, $endOperation['parserIndex']);
                        $right = $this->popStack();
                        $this->pushStack(['type' => 'script', 'script' => $value . '?' . $left->getScript() . ':' . $right->getScript()]);
                        return;
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
                $value = $this->getLogicValue($operation, $this->popStack());
                $this->writeScript($operation['parserIndex'], 'if(' . $value . ')');
                break;
            case 'JSOP_LOOPHEAD':
                $gotoOperation = $this->_getLoopGoto($operation);
                $isDo = !$gotoOperation;
                if ($isDo) {
                    $this->writeScript($operation['parserIndex'], 'do{', -1);
                    $entryOperation = $this->operations[$operation['parserIndex'] + $operation['length']];
                    $ifOperation = $entryOperation;
                    do {
                        $ifOperation = $this->_getLoopLogic($ifOperation);
                    } while ($this->operations[$ifOperation['parserIndex'] + $ifOperation['params']['offset']]['parserIndex'] != $operation['parserIndex']);
                } else {
                    $entryOperation = $this->operations[$gotoOperation['parserIndex'] + $gotoOperation['params']['offset']];
                    $ifOperation = $this->_getLoopLogic($entryOperation);
                }
                $this->revealOperations($entryOperation['parserIndex'], $ifOperation['parserIndex'] + $ifOperation['length']);
                $this->revealOperations($operation['parserIndex'] + $operation['length'], $entryOperation['parserIndex']);
                if ($isDo) {
                    $this->writeScript(
                        $ifOperation['parserIndex'],
                        str_replace('if(', '} while(', $this->getScript($ifOperation['parserIndex'])['value'])
                    );
                } else {
                    $this->writeScript(
                        $operation['parserIndex'],
                        str_replace('if(', 'while(', $this->getScript($ifOperation['parserIndex'])['value']) . '{',
                        -1
                    );
                    $this->writeScript($ifOperation['parserIndex'], '}');
                }
                break;
            case 'JSOP_LOOPENTRY':
                break;
            //For-In Statement
            case 'JSOP_ITER':
                $val = $this->popStack();
                $this->pushStack($val);
                break;
            case 'JSOP_ITERNEXT':
                $this->pushStack(['type' => 'script', 'name' => '_iternext']);
                break;
            case 'JSOP_MOREITER':
                $val = $this->popStack();
                $this->pushStack([]);
                $this->pushStack(['type' => 'script', 'script' => $val->getValue() . ' has _iternext']);
                break;
            case 'JSOP_ENDITER':
                $val = $this->popStack();
                break;
            //math
            case 'JSOP_BITNOT':
            case 'JSOP_NEG':
                $value = $this->getLogicValue($operation, $this->popStack());
                $this->pushStack(['script' => '(' . $operation['image'] . $value . ')', 'type' => 'script']);
                break;
            case 'JSOP_POS':
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
                $rVal = $right->getValue();
                $left = $this->popStack();
                $lVal = $left->getValue();
                if ($left->type == 'logic') {
                    $lVal = $this->_combineLogicByParserIndex($left->operation['parserIndex']);
                }
                if ($right->type == 'logic') {
                    $rVal = $this->_combineLogicByParserIndex($right->operation['parserIndex']);
                }
                $this->pushStack(['script' => '(' . $lVal . ' ' . $operation['image'] . ' ' . $rVal . ')', 'type' => 'script']);
                break;
            case 'JSOP_OR':
                $script = $this->popStack();
                $gotoIndex = $operation['parserIndex'] + $operation['params']['offset'];
                $this->logicStacks[$operation['parserIndex']] = ['type' => 'or', 'goto' => $gotoIndex, 'value' => $script->getValue()];
                $this->pushStack(['type' => 'logic', 'operation' => $operation]);
                break;
            case 'JSOP_AND':
                $script = $this->popStack();
                $gotoIndex = $operation['parserIndex'] + $operation['params']['offset'];
                $this->logicStacks[$operation['parserIndex']] = ['type' => 'and', 'goto' => $gotoIndex, 'value' => $script->getValue()];
                $this->pushStack(['type' => 'logic', 'operation' => $operation]);
                break;
            case 'JSOP_NOT':
                $script = $this->popStack();
                $this->logicStacks[$operation['parserIndex']] = ['type' => 'not', 'value' => $script->getValue()];
                $this->pushStack(['type' => 'logic', 'operation' => $operation]);
                break;
            //typeof
            case 'JSOP_TYPEOF':
            case 'JSOP_TYPEOFEXPR':
                $name = $this->popStack();
                $this->pushStack(['script' => 'typeof ' . $name->getValue(), 'type' => 'script']);
                break;
            //instanceof
            case 'JSOP_INSTANCEOF':
                $rVal = $this->popStack();
                $lVal = $this->popStack();
                $this->pushStack(['script' => $lVal->getValue() . ' instanceof ' . $rVal->getValue(), 'type' => 'script']);
                break;
            //Function
            case 'JSOP_NEW':
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
                $write = '';
                if (!empty($this->logicStacks)) {
                    $write .= $this->_combineLogicByGotoIndex($operation['parserIndex'] + $operation['length']);
                }
                if ($operation['name'] == 'JSOP_NEW') {
                    $write = 'new ';
                }
                $write .= $_callee->name ? $_callee->name : ('(' . $_callee->getScript() . ')');
                $write .= '(';
                if ($_argc) {
                    for ($i = $_argc - 1; $i >= 0; $i--) {
                        //$argv = $_argv[$i]->getScript() ? $_argv[$i]->getScript() : $_argv[$i]->name;//todo
                        $argv = $_argv[$i]->getValue();
                        $write .= $argv . ',';
                    }
                    $write = substr($write, 0, strlen($write) - 1);
                }
                $write .= ')';
                $this->pushStack(['type' => 'script', 'script' => $write]);
                break;
            //Object
            case 'JSOP_NEWINIT':
                $this->pushStack(['value' => [], 'type' => 'object']);
                break;
            case 'JSOP_INITPROP':
                $name = $this->atoms[$operation['params']['nameIndex']];
                $val = $this->popStack();
                $array = $this->popStack();
                $array->value[$name] = $val;
                $this->pushStack($array);
                break;
            case 'JSOP_DELPROP':
                $obj = $this->popStack();
                $this->pushStack(['type' => 'script', 'script' => 'delete ' . $obj->getValue() . '.' . $this->atoms[$operation['params']['nameIndex']]]);
                break;
            case 'JSOP_SETPROP':
                $value = $this->getLogicValue($operation, $this->popStack());
                $name = $this->popStack();
                $this->pushStack(['type' => 'script', 'script' => $name->getValue() . '.' . $this->atoms[$operation['params']['nameIndex']] . '=' . $value]);
                break;
            case 'JSOP_GETPROP':
            case 'JSOP_CALLPROP':
                $obj = $this->popStack();
                $this->pushStack(['name' => $obj->getValue() . '.' . $this->atoms[$operation['params']['nameIndex']]]);
                break;
            case 'JSOP_INITELEM':
            case 'JSOP_INITELEM_INC':
                $val = $this->popStack();
                $name = $this->popStack();
                $array = $this->popStack();
                $array->value[$name->getValue()] = $val;
                $this->pushStack($array);
                break;
            case 'JSOP_DELELEM':
                $propval = $this->popStack();
                $obj = $this->popStack();
                $this->pushStack(['type' => 'script', 'script' => 'delete ' . $obj->getValue() . '[' . $propval->getValue() . ']']);
                break;
            case 'JSOP_SETELEM':
                $value = $this->getLogicValue($operation, $this->popStack());
                $propval = $this->popStack();
                $obj = $this->popStack();
                $this->pushStack(['type' => 'script', 'script' => $obj->getValue() . '[' . $propval->getValue() . ']=' . $value]);
                break;
            case 'JSOP_GETELEM':
            case 'JSOP_CALLELEM':
                $propval = $this->popStack();
                $obj = $this->popStack();
                $this->pushStack(['name' => $obj->getValue() . '[' . $propval->getValue() . ']']);
                break;
            //Array
            case 'JSOP_NEWARRAY':
                $this->pushStack(['value' => [], 'type' => 'array']);
                break;
            case 'JSOP_INITELEM_ARRAY':
                $val = $this->popStack();
                $array = $this->popStack();
                $array->value[] = $val;
                $this->pushStack($array);
                break;
            case 'JSOP_LENGTH':
                $name = $this->popStack();
                $this->pushStack(['type' => 'script', 'script' => $name->getValue() . '.length']);
                break;
            //压入变量
            case 'JSOP_NAME':
            case 'JSOP_BINDNAME':
            case 'JSOP_IMPLICITTHIS':
                $this->pushStack(['name' => $this->atoms[$operation['params']['nameIndex']]]);
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
                $this->pushStack(['value' => 'true', 'type' => 'boolean']);
                break;
            case 'JSOP_FALSE':
                $this->pushStack(['value' => 'false', 'type' => 'boolean']);
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
                $this->pushStack(['value' => $this->atoms[$operation['params']['atomIndex']], 'type' => 'string']);
                break;
            case 'JSOP_REGEXP':
                $this->pushStack(['value' => '/' . $this->regexps[$operation['params']['regexpIndex']]['source'] . '/', 'type' => 'regexp']);
                break;
            case 'JSOP_OBJECT'://todo
                $this->pushStack(['value' => [], 'type' => 'object']);
                break;
            case 'JSOP_LAMBDA_ARROW':
                $_this = $this->popStack();
                $object = $this->objects[$operation['params']['funcIndex']];
                $this->pushStack(['value' => $object['contextIndex'], 'type' => 'function']);
                break;
            case 'JSOP_LAMBDA':
                $object = $this->objects[$operation['params']['funcIndex']];
                $this->pushStack(['value' => $object['contextIndex'], 'type' => 'function']);
                break;
            //赋值
            case 'JSOP_SETNAME':
                $value = $this->getLogicValue($operation, $this->popStack());
                $name = $this->popStack();
                $name->value = $value;
                $name->script = $name->getValue() . ' = ' . $value;
                $name->type = 'script';
                $this->pushStack($name);
                break;
            case 'JSOP_SETCONST':
                $value = $this->getLogicValue($operation, $this->popStack());
                $name = $this->atoms[$operation['params']['nameIndex']];
                $this->pushStack([
                    'type' => 'script',
                    'name' => $name->value,
                    'value' => $value,
                    'script' => $name->value . ' = ' . $value,
                ]);
                break;
            //delete
            case 'JSOP_DELNAME':
                $this->pushStack(['type' => 'script', 'script' => 'delete ' . $this->atoms[$operation['params']['nameIndex']]]);
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
            case 'JSOP_ARGUMENTS':
                $this->pushStack(['name' => 'arguments']);
                break;
            case 'JSOP_REST':
                $this->pushStack(['name' => 'rest']);
                break;
            case 'JSOP_SETARG':
                $val = $this->popStack();
                $this->pushStack([
                    'type' => 'script',
                    'name' => '__ARG_' . $operation['params']['argno'] . '__',
                    'script' => '__ARG_' . $operation['params']['argno'] . '__ = ' . $val->getValue(),
                ]);
                break;
            case 'JSOP_GETARG':
                //todo try to get arguments
                $this->pushStack(['name' => '__ARG_' . $operation['params']['argno'] . '__']);
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
                $this->pushStack(['value' => [], 'type' => 'object']);
                break;
            //This
            case 'JSOP_THIS':
                $this->pushStack(['name' => 'this']);
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
                $raw = $this->popStack();
                $value = $raw->name ?: $this->getLogicValue($operation, $raw);
                $localno = $operation['params']['localno'];
                $name = '_local' . $localno;
                $localVar = [
                    'type' => 'script',
                    'name' => $name,
                    'value' => $value,
                    'script' => $name . '=' . $value,
                ];
                $this->decompile->setLocalVariable($localno, $localVar);
                $this->pushStack($localVar);
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
                $value = $this->getLogicValue($operation, $this->popStack());
                $aliasedVar = ['type' => 'aliasedVar', 'name' => '_aliased' . rand(1000, 9999)];
                $this->decompile->setAliasedVariable($operation['params']['hops'], $operation['params']['slot'], $aliasedVar);
                $this->pushStack(['type' => 'script', 'name' => $aliasedVar['name'], 'script' => $aliasedVar['name'] . '=' . $value]);
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
            case 'JSOP_DEBUGLEAVEBLOCK':
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
            $this->writeScript($start, $caseWrite, -1);
            $this->revealOperations($start, $contentEndings[$i], ['type' => 'switch', 'defaultIndex' => $defaultIndex,]);
            $i++;
        }
        $this->writeScript($defaultIndex, 'default:');
    }

    protected function getLogicValue($operation, Stack $val)
    {
        $value = $val->getValue();
        if (!empty($this->logicStacks)) {
            $value = $this->_combineLogicByGotoIndex($operation['parserIndex'] + $operation['length']) . $value;
        }
        return $value;
    }

    protected function _getLoopGoto($operation)
    {
        $gotoIndex = $operation['parserIndex'] - 5;
        if (!isset($this->operations[$gotoIndex])) {
            return false;
        }
        $gotoOperation = $this->operations[$gotoIndex];
        if ($gotoOperation['name'] !== 'JSOP_GOTO') {
            return false;
        }
        return $gotoOperation;
    }

    protected function _getLoopLogic($entry)
    {
        $if = $entry;
        do {
            $if = $this->findFirstOperation(
                ['JSOP_IFNE', 'JSOP_IFEQ'],
                $if['parserIndex'] + $if['length']
            );
            if (!$if) {
                return false;
            }
        } while ($if['params']['offset'] > 0);
        return $if;
    }

    protected function _getBranchGoto($operation)
    {
        $gotoIndex = $operation['parserIndex'] + $operation['params']['offset'] - 5;
        if (!isset($this->operations[$gotoIndex])) {
            return false;
        }
        $gotoOperation = $this->operations[$gotoIndex];
        if ($gotoOperation['name'] !== 'JSOP_GOTO') {
            return false;
        }
        return $gotoOperation;
    }

    protected function _getBranchBreak($goto)
    {
        if ($goto['params']['offset'] > 0) {
            if ($this->hasOperation($goto['parserIndex'], $goto['parserIndex'] + $goto['params']['offset'], 'JSOP_LOOPENTRY')) {
                $this->writeScript($goto['parserIndex'], 'break;');
                return true;
            }
        }
        return false;
    }

    protected function _getBranchContinue($goto)
    {
        if ($goto['params']['offset'] < 0) {
            $nextOperation = $this->gotoNextOperation($goto);
            if ($nextOperation['name'] == 'JSOP_LOOPENTRY') {
                $this->writeScript($goto['parserIndex'], 'continue;');
                return true;
            }
        }
        return false;
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

    protected function _getLogicScript($stack)
    {
        $value = '';
        $isNot = $stack['type'] == 'not';
        if ($isNot) {
            $value .= '!(';
        }
        $value .= $stack['value'];
        if ($isNot) {
            $value .= ')';
        }
        switch ($stack['type']) {
            case 'and':
                $value .= ' && ';
                break;
            case 'or':
                $value .= ' || ';
                break;
        }
        return $value;
    }

    protected function _combineLogicUnit()
    {
        $logicStackKeys = array_keys($this->logicStacks);
        $logicStackKeysCount = count($logicStackKeys);
        $tree = ['type' => 'script'];
        for ($i = 0; $i < $logicStackKeysCount; $i++) {
            $logicStack = $this->logicStacks[$logicStackKeys[$i]];
            if (is_null($logicStack['value'])) {
                $preStack = $this->logicStacks[$logicStackKeys[$i - 1]];
                $this->logicStacks[$logicStackKeys[$i]]['value'] = $preStack['type'] == 'not' ? ('!(' . $preStack['value'] . ')') : $preStack['value'];
                unset($this->logicStacks[$logicStackKeys[$i - 1]]);
            } elseif (!isset($logicStackKeys[$i + 1])) {//if last one
                $preStack = $this->logicStacks[$logicStackKeys[$i - 1]];
                $value = $this->_getLogicScript($preStack) . $this->_getLogicScript($logicStack);
                $this->logicStacks[$logicStackKeys[$i - 1]] = ['value' => $value, 'type' => 'script'];
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

    protected function _combineLogicByGotoIndex($index)
    {
        krsort($this->logicStacks);
        $executeFlag = true;
        $execute = [];
        $storage = [];
        foreach ($this->logicStacks as $key => $value) {
            if (isset($value['goto']) && $value['goto'] > $index) {
                $executeFlag = false;
            }
            if ($executeFlag) {
                $execute[$key] = $value;
            } else {
                $storage[$key] = $value;
            }
        }
        $result = '';
        if (!empty($execute)) {
            $this->logicStacks = $execute;
            $result = $this->_combineLogic();
        }
        $this->logicStacks = $storage;
        return $result;
    }

    protected function _combineLogicByParserIndex($index)
    {
        ksort($this->logicStacks);
        $executeFlag = true;
        $execute = [];
        $storage = [];
        foreach ($this->logicStacks as $key => $value) {
            if ($key > $index) {
                $executeFlag = false;
            }
            if ($executeFlag) {
                $execute[$key] = $value;
            } else {
                $storage[$key] = $value;
            }
        }
        $result = '';
        if (!empty($execute)) {
            $this->logicStacks = $execute;
            $result = $this->_combineLogic();
        }
        $this->logicStacks = $storage;
        return $result;
    }
}
