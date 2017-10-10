<?php
/**
 * Created by PhpStorm.
 * User: irelance
 * Date: 2017/10/10
 * Time: 上午11:47
 */

namespace Irelance\Mozjs34\Xdr;
/**
 * @method \Irelance\Mozjs34\Xdr\Common todec(int $length = 4)
 *
 * @property integer $parseIndex
 * @property array $bytecodes
 */
trait Scope
{
    protected function XDRSizedBindingNames()
    {
        $length = $this->todec();
        $result = [];
        for ($i = 0; $i < $length; $i++) {
            $u8 = $this->todec(1);
            $hasAtom = $u8 >> 1;
            if ($hasAtom) {
                $result[] = $this->XDRAtom();
            }
        }
        return $result;
    }

    public function xdrScopeExtra($scopeKind)
    {
        $result = 'xdr';
        switch ($scopeKind) {
            case 'Function':
                $result .= 'Function';
                break;
            case 'FunctionBodyVar':
            case 'ParameterExpressionVar':
                $result .= 'Var';
                break;
            case 'Lexical':
            case 'SimpleCatch':
            case 'Catch':
            case 'NamedLambda':
            case 'StrictNamedLambda':
                $result .= 'Lexical';
                break;
            case 'With':
                $result .= 'With';
                break;
            case 'Eval':
            case 'StrictEval':
                $result .= 'Eval';
                break;
            case 'Global':
            case 'NonSyntactic':
                $result .= 'Global';
                break;
            case 'Module':
            default:
                $result .= 'Not';
        }
        return $this->$result();
    }

    protected function xdrNot()
    {
        return [];
    }

    protected function xdrWith()
    {
        return [];
    }

    protected function xdrLexical()
    {
        return [
            'bindingNames' => $this->XDRSizedBindingNames(),
            'constStart' => $this->todec(),
            'firstFrameSlot' => $this->todec(),
            'nextFrameSlot' => $this->todec()
        ];
    }

    protected function xdrFunction()
    {
        return [
            'bindingNames' => $this->XDRSizedBindingNames(),
            'needsEnvironment' => $this->todec(1),
            'hasParameterExprs' => $this->todec(1),
            'nonPositionalFormalStart' => $this->todec(2),
            'varStart' => $this->todec(2),
            'nextFrameSlot' => $this->todec(),
        ];
    }

    protected function xdrVar()
    {
        return [
            'bindingNames' => $this->XDRSizedBindingNames(),
            'needsEnvironment' => $this->todec(1),
            'firstFrameSlot' => $this->todec(),
            'nextFrameSlot' => $this->todec()
        ];
    }

    protected function xdrGlobal()
    {
        return [
            'bindingNames' => $this->XDRSizedBindingNames(),
            'letStart' => $this->todec(),
            'constStart' => $this->todec(),
        ];
    }

    protected function xdrEval()
    {
        return [
            'bindingNames' => $this->XDRSizedBindingNames(),
            'length' => $this->todec(),
        ];
    }
}
