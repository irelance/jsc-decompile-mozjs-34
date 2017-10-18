<?php
/**
 * Created by PhpStorm.
 * User: irelance
 * Date: 2017/10/10
 * Time: 上午11:30
 */

namespace Irelance\Mozjs34\Xdr;

use Irelance\Mozjs34\Context;
use Irelance\Mozjs34\Constant;

/**
 * @method \Irelance\Mozjs34\Xdr\Common todec(int $length = 4)
 *
 * @property integer $parseIndex
 * @property array $bytecodes
 */
trait Script
{
    protected function getScriptName()
    {
        $this->parseIndex += 5;//todo find the reason
        $result = '';
        while ($this->bytecodes[$this->parseIndex]) {
            $result .= chr($this->bytecodes[$this->parseIndex]);
            $this->parseIndex++;
        }
        $this->parseIndex++;
        return $result;
    }

    protected function parserHeader(Context $context)
    {
        $nargs = $this->todec(2);
        $context->addSummary('nargs', $nargs);
        $context->addSummary('nblocklocals', $this->todec(2));
        $nvars = $this->todec();
        $context->addSummary('nvars', $nvars);
        $context->addSummary('length', $this->todec());
        $context->addSummary('prologLength', $this->todec());
        $context->addSummary('version', $this->todec());
        $context->addSummary('natoms', $this->todec());
        $context->addSummary('nsrcnotes', $this->todec());
        $context->addSummary('nconsts', $this->todec());
        $context->addSummary('nobjects', $this->todec());
        $context->addSummary('nregexps', $this->todec());
        $context->addSummary('ntrynotes', $this->todec());
        $context->addSummary('nblockscopes', $this->todec());
        $context->addSummary('nTypeSets', $this->todec());
        $context->addSummary('funLength', $this->todec());
        $scriptBit = $this->todec();
        $context->addSummary('scriptBits', $scriptBit);
        $scriptBits = array_flip(Constant::_ScriptBits);
        if ($scriptBit & (1 << $scriptBits['OwnSource'])) {
            $context->addSummary('buildPath', $this->getScriptName());
        }
        $nameCount = $nargs + $nvars;
        for ($i = 0; $i < $nameCount; $i++) {
            $atom = $this->XDRAtom();
        }
        for ($i = 0; $i < $nameCount; $i++) {
            $u8 = $this->todec(1);
        }
        $context->addSummary('sourceStart_', $this->todec());
        $context->addSummary('sourceEnd_', $this->todec());
        $context->addSummary('lineno', $this->todec());
        $context->addSummary('column', $this->todec());
        $context->addSummary('nslots', $this->todec());
        $context->addSummary('staticLevel', $this->todec());
    }

    protected function parserScript(Context $context)
    {
        $end = $this->parseIndex + $context->getSummary('length');
        for (; $this->parseIndex < $end;) {
            $context->addOperation($this->parseIndex, $this->parserOperation());
        }
        $this->parseIndex = $end;
    }

    protected function parserSrcNodes(Context $context)
    {
        $end = $this->parseIndex + $context->getSummary('nsrcnotes');
        for (; $this->parseIndex < $end; $this->parseIndex++) {
            $context->addNode($this->bytecodes[$this->parseIndex]);
        }
    }

    protected function parserAtoms(Context $context)
    {
        if ($natoms = $context->getSummary('natoms')) {
            for ($i = 0; $i < $natoms; $i++) {
                $context->addAtom($this->XDRAtom());
            }
        }
    }

    protected function parseConsts(Context $context)
    {
        if ($nconsts = $context->getSummary('nconsts')) {
            for ($i = 0; $i < $nconsts; $i++) {
                $context->addConst($this->xdrConst());
            }
        }
    }

    protected function parserObject(Context $context)
    {
        $context->addObject(
            $classKind = Constant::_Class[$this->todec()],
            $extra = $this->xdrObjectExtra($classKind)
        );
    }

    protected function parserObjects(Context $context)
    {
        if ($nobjects = $context->getSummary('nobjects')) {
            for ($i = 0; $i < $nobjects; $i++) {
                $this->parserObject($context);
            }
        }
    }

    protected function parserRegexps(Context $context)
    {
        if ($nregexps = $context->getSummary('nregexps')) {
            for ($i = 0; $i < $nregexps; $i++) {
                $context->addRegexp(
                    $source = $this->XDRAtom(),
                    $flagsword = $this->todec()
                );
            }
        }
    }

    protected function parserTryNotes(Context $context)
    {
        if ($ntrynotes = $context->getSummary('ntrynotes')) {
            for ($i = 0; $i < $ntrynotes; $i++) {
                $context->addTryNote(
                    $kind = $this->todec(1),
                    $stackDepth = $this->todec(),
                    $start = $this->todec(),
                    $length = $this->todec()
                );
            }
        }
    }

    protected function parserScopeNotes(Context $context)
    {
        if ($nscopeNotes = $context->getSummary('nblockscopes')) {
            for ($i = 0; $i < $nscopeNotes; $i++) {
                $context->addScopeNote(
                    $index = $this->todec(),
                    $start = $this->todec(),
                    $length = $this->todec(),
                    $parent = $this->todec()
                );
            }
        }
    }

    protected function parserHasLazyScript(Context $context)
    {
        $scriptBits = array_flip(Constant::_ScriptBits);
        $HasLazyScript = $scriptBits['HasLazyScript'];
        if ($context->getSummary('scriptBits') & (1 << $HasLazyScript)) {
            $packedFields = $this->todec(8);
            $context->addHasLazyScript($packedFields);
            $this->XDRLazyFreeVariables();
        }
    }

    public function XDRScript()
    {
        $context = new Context();
        $index = count($this->contexts);
        $this->contexts[] = $context;
        $context->index = $index;
        $this->parseScriptIndex = $index;
        $context->decompile = $this;
        $this->parserHeader($context);
        $this->parserScript($context);
        $this->parserSrcNodes($context);
        $this->parserAtoms($context);
        $this->parseConsts($context);
        $this->parserObjects($context);
        $this->parserRegexps($context);
        $this->parserTryNotes($context);
        $this->parserScopeNotes($context);
        $this->parserHasLazyScript($context);
        return $context;
    }
}
