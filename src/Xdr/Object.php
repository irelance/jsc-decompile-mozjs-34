<?php
/**
 * Created by PhpStorm.
 * User: irelance
 * Date: 2017/10/10
 * Time: 上午11:49
 */

namespace Irelance\Mozjs34\Xdr;


trait Object
{
    public function xdrObjectExtra($objectType)
    {
        $result = 'xdr';
        switch ($objectType) {
            case 'CK_BlockObject':
            case 'CK_WithObject':
            case 'CK_JSFunction':
            case 'CK_JSObject':
                $result .= $objectType;
                break;
            default:
                $result .= 'CK_Not';
                break;
        }
        return $this->$result();
    }

    protected function xdrCK_Not()
    {
        return [];
    }

    protected function xdrCK_BlockObject()
    {
        $result = [
            'enclosingStaticScopeIndex' => $this->todec(),
            'count' => $this->todec(),
            'offset' => $this->todec(),
            'atoms' => [],
        ];
        for ($i = 0; $i < $result['count']; $i++) {
            $result['atoms'][] = [
                'atom' => $this->XDRAtom(),
                'aliased' => $this->todec(),
            ];
        }

        return $result;
    }

    protected function xdrCK_WithObject()
    {
        return [
            'enclosingStaticScopeIndex' => $this->todec(),
        ];
    }

    protected function xdrCK_JSFunction()
    {
        return array_merge([
            'funEnclosingScopeIndex' => $funEnclosingScopeIndex = $this->todec(),
        ],$this->XDRInterpretedFunction());
    }

    public function xdrCK_JSObject()
    {
        $result = [];
        $result['isArray'] = $isArray = $this->todec();
        $this->todec();//isArray ? length : kind
        $result['capacity'] = $capacity = $this->todec();
        $initialized = $this->todec();
        $result['initialized'] = [];
        for ($i = 0; $i < $initialized; $i++) {
            $result['initialized'][] = $tmpValue = $this->xdrConst();
        }
        $nslot = $this->todec();
        $result['nslot'] = [];
        for ($i = 0; $i < $nslot; $i++) {
            $idType = $this->todec();
            if ($idType == JSID_TYPE_STRING) {
                $key = $this->XDRAtom();
            } else {
                $key = $this->todec();
            }
            $val = $this->xdrConst();
            $result['nslot'][$key] = $val;
        }
        $isSingletonTyped = $this->todec();
        $frozen = $this->todec();
        if ($isArray) {
            $copyOnWrite = $this->todec();
        }
        return $result;
    }

}
