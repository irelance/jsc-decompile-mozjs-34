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
        $funEnclosingScopeIndex = $this->todec();
        $this->XDRInterpretedFunction();//todo get the information
        return [
            'funEnclosingScopeIndex' => $funEnclosingScopeIndex,
        ];
    }

    public function xdrCK_JSObject()
    {
        $isArray = $this->todec();
        if ($isArray) {
            $length = $this->todec();
        } else {
            $kind = $this->todec();
        }
        $capacity = $this->todec();
        $initialized = $this->todec();
        for ($i = 0; $i < $initialized; $i++) {
            $tmpValue = $this->xdrConst();
        }
        $nslot = $this->todec();
        for ($i = 0; $i < $nslot; $i++) {
            $idType = $this->todec();
            if ($idType == JSID_TYPE_STRING) {
                $key = $this->XDRAtom();
            } else {
                $key = $this->todec();
            }
            $val = $this->xdrConst();
        }
        $isSingletonTyped = $this->todec();
        $frozen = $this->todec();
        if ($isArray) {
            $copyOnWrite=$this->todec();
        }
        return [];
    }

}
