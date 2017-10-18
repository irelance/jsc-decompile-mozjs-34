<?php
/**
 * Created by PhpStorm.
 * User: irelance
 * Date: 2017/10/11
 * Time: 上午10:13
 */

namespace Irelance\Mozjs34\Helper;


use Irelance\Mozjs34\Constant;

trait Reveal
{
    public function printSummaries()
    {
        if (count($this->summaries)) {
            echo '---------------Summaries---------------', CLIENT_EOL;
            foreach ($this->summaries as $key => $value) {
                echo $key, ' : ', $value, CLIENT_EOL;
            }
            echo '---------------------------------------', CLIENT_EOL;
        }
    }

    public function printOperations()
    {
        echo '---------------Operations--------------', CLIENT_EOL;
        foreach ($this->operations as $key => $operation) {
            $op = Constant::_Opcode[$operation['id']];
            echo '[', $key, ']', $op['op'], json_encode($operation['params']), ' pop: ', $operation['pop'], ' push: ', $operation['push'], ' byte: ', $operation['length'], CLIENT_EOL;
        }
        echo '---------------------------------------', CLIENT_EOL;
    }

    public function printNodes()
    {
        echo '-----------------Nodes-----------------', CLIENT_EOL;
        echo implode(', ', $this->nodes), CLIENT_EOL;
        echo '---------------------------------------', CLIENT_EOL;
    }

    public function printAtoms()
    {
        if (count($this->atoms)) {
            echo '-----------------Atoms-----------------', CLIENT_EOL;
            foreach ($this->atoms as $key => $value) {
                echo $key, ' : ', $value, CLIENT_EOL;
            }
            echo '---------------------------------------', CLIENT_EOL;
        }
    }

    public function printConsts()
    {
        if (count($this->consts)) {
            echo '-----------------Consts----------------', CLIENT_EOL;
            foreach ($this->consts as $key => $const) {
                echo $key, ' : [', $const['type'], ']', $const['value'], CLIENT_EOL;
            }
            echo '---------------------------------------', CLIENT_EOL;
        }
    }

    public function printObjects()
    {
        if (count($this->objects)) {
            echo '-----------------Objects---------------', CLIENT_EOL;
            foreach ($this->objects as $object) {
                echo json_encode($object), CLIENT_EOL;
            }
            echo '---------------------------------------', CLIENT_EOL;
        }
    }

    public function printRegexps()
    {
        if (count($this->regexps)) {
            echo '-----------------Regexps---------------', CLIENT_EOL;
            foreach ($this->regexps as $regexp) {
                echo $regexp['source'], CLIENT_EOL;
            }
            echo '---------------------------------------', CLIENT_EOL;
        }
    }

    public function printTryNote()
    {
        if (count($this->tryNotes)) {
            echo '-----------------Objects---------------', CLIENT_EOL;
            foreach ($this->tryNotes as $tryNote) {
                echo json_encode($tryNote), CLIENT_EOL;
            }
            echo '---------------------------------------', CLIENT_EOL;
        }
    }

    public function printScopeNote()
    {
        if (count($this->scopeNotes)) {
            echo '---------------Summaries---------------', CLIENT_EOL;
            foreach ($this->scopeNotes as $id => $scopeNote) {
                echo $id, ' : ', json_encode($scopeNote), CLIENT_EOL;
            }
            echo '---------------------------------------', CLIENT_EOL;
        }
    }

    public function printProperties(array $types)
    {
        foreach ($types as $type) {
            $call = 'print' . $type;
            if (method_exists($this, $call)) {
                $this->$call();
            }
        }
    }

    protected function contentPreTreat()
    {
        $whileEntriesMove = [];
        $scriptKeys = array_keys($this->storageScript);
        $scriptKeysCount = count($scriptKeys);
        for ($i = 0; $i < $scriptKeysCount; $i++) {
            $script = $this->storageScript[$scriptKeys[$i]];
            if ($script['value'] == 'JSOP_LOOPENTRY') {
                $nextScript = $this->storageScript[$scriptKeys[$i + 1]];
                if (substr($nextScript['value'], 0, 6) == 'while(') {
                    $whileEntriesMove[] = ['type' => 'while', 'key' => $scriptKeys[$i + 1]];
                }
                unset($this->storageScript[$scriptKeys[$i]]);
            } elseif ($script['value'] == '{') {
                $whileEntriesMove[] = ['type' => '{', 'key' => $scriptKeys[$i]];
            } elseif (substr($script['value'], 0, 6) == 'while(') {
                if ($whileEntriesMove[count($whileEntriesMove) - 1]['key'] != $scriptKeys[$i]) {
                    $this->storageScript[$scriptKeys[$i]]['value'] = '}' . $this->storageScript[$scriptKeys[$i]]['value'] . ';';
                }
            } elseif ($script['value'] == 'else') {
                if (!isset($scriptKeys[$i + 1]) || !isset($this->storageScript[$scriptKeys[$i + 1]])) {
                    $this->storageScript[$scriptKeys[$i]]['value'] = '';
                    continue;
                }
                $nextScript = $this->storageScript[$scriptKeys[$i + 1]];
                if (substr($nextScript['value'], 0, 3) != 'if(') {
                    $this->storageScript[$scriptKeys[$i]]['value'] .= '{';
                }
            }
        }
        $whileEntriesMoveCount = count($whileEntriesMove);
        for ($i = 0; $i < $whileEntriesMoveCount; $i++) {
            $while = $whileEntriesMove[$i];
            if ($while['type'] == 'while') {
                for ($j = $i; $j >= 0; $j--) {
                    if ($whileEntriesMove[$j]['type'] == '{') {
                        break;
                    }
                }
                $entry = $whileEntriesMove[$j];
                $this->storageScript[$entry['key']]['value'] = $this->storageScript[$while['key']]['value'] . $this->storageScript[$entry['key']]['value'];
                $whileEntriesMove[$j]['type'] = 'unset';
                unset($this->storageScript[$while['key']]);
                $this->writeScriptEndings($while['key'] / 2, '}');
            }
        }
        foreach ($whileEntriesMove as $item) {
            if ($item['type'] == '{') {
                $this->storageScript[$item['key']]['value'] = 'do{';
            }
        }
    }

    public function printContent()
    {
        foreach ($this->operations as $key => $operation) {
            if (!$this->operations[$key]['isCover']) {
                $this->revealOperation($operation);
                $this->operations[$key]['isCover'] = true;
            }
        }
        ksort($this->storageScript);
        $this->contentPreTreat();

        ksort($this->storageScript);
        $scriptKeys = array_keys($this->storageScript);
        $scriptKeysCount = count($scriptKeys);
        echo '----------------Content----------------', CLIENT_EOL;
        for ($i = 0; $i < $scriptKeysCount; $i++) {
            $script = $this->storageScript[$scriptKeys[$i]];
            echo '[', $scriptKeys[$i] / 2, ']', $script['value'], CLIENT_EOL;
            //echo $script['value'], CLIENT_EOL;
        }
        echo '---------------------------------------', CLIENT_EOL;
    }
}
