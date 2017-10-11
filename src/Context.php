<?php

/**
 * Created by PhpStorm.
 * User: irelance
 * Date: 2017/10/1
 * Time: 下午3:25
 */
namespace Irelance\Mozjs34;

use Irelance\Mozjs34\Helper\Reveal;

class Context
{
    use Reveal;
    protected $summaries = [];
    protected $operations = [];
    protected $nodes = [];
    protected $atoms = [];
    protected $consts = [];
    protected $objects = [];
    protected $regexps = [];
    protected $tryNotes = [];
    protected $scopeNotes = [];
    protected $hasLazyScript;

    public function addSummary($key, $value)
    {
        if (isset($this->summaries[$key])) {
            return false;
        }
        $this->summaries[$key] = $value;
        return true;
    }

    public function addOperation(array $operation)
    {
        $this->operations[] = $operation;
    }

    public function addNode($node)
    {
        $this->nodes[] = $node;
    }

    public function addAtom($atom)
    {
        $this->atoms[] = $atom;
    }

    public function addConst($const)
    {
        $this->consts[] = $const;
    }

    public function addObject($classKind, array $extra)
    {
        $this->objects[] = array_merge($extra, ['classKind' => $classKind]);
    }

    public function addRegexp($source, $flagsword)
    {
        $this->regexps[]=[
            'source'=>$source,
            'flagsword'=>$flagsword,
        ];
    }

    public function addTryNote($kind, $stackDepth, $start, $length)
    {
        $this->tryNotes[] = ['kind' => $kind, 'stackDepth' => $stackDepth, 'start' => $start, 'length' => $length];
    }

    public function addScopeNote($index, $start, $length, $parent)
    {
        $this->tryNotes[$index] = ['parent' => $parent, 'start' => $start, 'length' => $length];
    }

    public function addHasLazyScript($packedFields)
    {
        $this->hasLazyScript = $packedFields;
    }

    public function getSummary($key)
    {
        return $this->summaries[$key];
    }
}
