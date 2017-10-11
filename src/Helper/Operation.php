<?php
/**
 * Created by PhpStorm.
 * User: irelance
 * Date: 2017/10/11
 * Time: 上午11:06
 */

namespace Irelance\Mozjs34\Helper;


use Irelance\Mozjs34\Constant;

trait Operation
{
    public function printOperation($operation)
    {
        $op = Constant::_Opcode[$operation['id']];
        switch ($op['op']) {
            case 'JSOP_DEFVAR':
                return 'var ' . $this->atoms[$operation['params']['nameIndex']];
                break;
        }
        return false;
    }
}
