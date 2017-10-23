<?php
/**
 * Created by PhpStorm.
 * User: irelance
 * Date: 2017/10/12
 * Time: 下午1:43
 */

namespace Irelance\Mozjs34\Helper;


class Stack
{
    public $type;
    public $name;
    public $value;//raw input value
    public $script;//raw input script

    public function __construct(array $props)
    {
        foreach ($props as $prop => $value) {
            $this->$prop = $value;
        }
    }

    public function getValue()
    {
        if ($this->name) {
            return $this->name;
        }
        return $this->getScript();
    }

    public function getScript()
    {
        if ($this->script) {
            return $this->script;
        }
        $this->script = self::renderBase($this);
        return $this->script;
    }

    public static function renderBase(self $input)
    {
        switch ($input->type) {
            case 'script':
                return $input->script;
            case 'function':
                return 'function () { __FUNC_' . $input->value . '__ }';
            case 'string':
                return '"' . $input->value . '"';
            case 'object':
                return self::renderObject($input->value);
            case 'array':
                return self::renderArray($input->value);
            case 'number':
            case 'boolean':
            case 'null':
            case 'undefined':
            case 'regexp':
        }
        return $input->value;
    }

    public static function renderArray($array)
    {
        $result = '[';
        foreach ($array as $item) {
            $result .= $item->getValue() . ',';
        }
        $resultLen = strlen($result);
        if ($resultLen>1) {
            $result = substr($result, 0, $resultLen - 1);
        }
        $result .= ']';
        return $result;
    }

    public static function renderObject($object)
    {
        $result = '{';
        foreach ($object as $key => $value) {
            $result .= $key . ':';
            $result .= $value->getValue() . ',';
        }
        $resultLen = strlen($result);
        if ($resultLen>1) {
            $result = substr($result, 0, $resultLen - 1);
        }
        $result .= '}';
        return $result;
    }
}
