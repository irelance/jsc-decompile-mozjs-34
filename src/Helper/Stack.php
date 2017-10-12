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
    public $isJson = false;
    public $type;
    public $value;//raw input value

    public function __construct(array $props)
    {
        foreach ($props as $prop => $value) {
            $this->$prop = $value;
        }
    }

    public function getValue()
    {
        if ($this->isJson) {
            return json_encode($this->value);
        }
        return $this->value;
    }
}
