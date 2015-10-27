<?php
/**
 * @author: matt
 * @copyright: 2015 Claritum Limited
 * @license: Commercial
 */

namespace KynxTest\V8js;

use Kynx\V8js\Handlebars;
use PHPUnit_Framework_TestCase as TestCase;
use V8js;

class SpecTest extends TestCase
{
    /**
     * @var Handlebars
     */
    private $hb;
    private $counters = [];

    public function setUp()
    {
        $handlebarsSource = file_get_contents(dirname(__DIR__) . '/components/handlebars/handlebars.js');
        Handlebars::registerHandlebarsExtension($handlebarsSource);
        $this->hb = new Handlebars();
    }

    /**
     * @dataProvider specProvider
     */
    public function testSpec($spec)
    {
        if ($spec['type'] == 'php') {
            $this->markTestSkipped("Still working on php...");
        }
        //echo "$name\n"; flush();
        $name = $spec['name'] . ($spec['type'] ? ' - ' . $spec['type'] : '');
        if (empty($this->counters[$name])) {
            $this->counters[$name] = 0;
        }
        $this->counters[$name]++;

        $spec = $this->prepareSpec($spec);
        if (!empty($spec['partials'])) {
            $this->hb->registerPartial($spec['partials']);
        }
        if (!empty($spec['helpers'])) {
            $this->hb->registerHelper($spec['helpers']);
        }
        if (!empty($spec['decorators'])) {
            $this->hb->registerDecorator($spec['decorators']);
        }
        $template = $this->hb->compile($spec['template'], $spec['compileOptions']);
        $actual = $template($spec['data']);
        $this->assertEquals($spec['expected'], $actual, $name - $this->counters[$name]);
    }

    private function prepareSpec($spec)
    {
        $default = [
            'partials' => [],
            'helpers' => [],
            'decorators' => [],
            'data' => [],
            'compileOptions' => [],
            'globalPartials' => [],
            'globalHelpers' => [],
            'globalDecorators' => [],
        ];
        $spec = array_merge($default, $spec);
        foreach (['partials', 'helpers', 'decorators'] as $sec) {
            $spec[$sec] = array_merge($spec['global' . ucfirst($sec)], $spec[$sec]);
        }

        if ($spec['type'] == 'php') {
            foreach ($spec['helpers'] as $name => $helper) {
                $spec['helpers'][$name] = $this->evalPhp($helper);
            }
            foreach ($spec['decorators'] as $name => $decorator) {
                $spec['decorators'][$name] = $this->evalPhp($decorator);
            }
            foreach ($spec['data'] as $name => $inline) {
                if ($this->isFunction($inline)) {
                    $spec['data'][$name] = $this->evalPhp($inline);
                }
            }
        } elseif ($spec['type'] == 'javascript') {
            foreach ($spec['helpers'] as $name => $helper) {
                $spec['helpers'][$name] = $this->evalJavascript($helper);
            }
            foreach ($spec['decorators'] as $name => $decorator) {
                $spec['decorators'][$name] = $this->evalJavascript($decorator);
            }
            foreach ($spec['data'] as $name => $inline) {
                if ($this->isFunction($inline)) {
                    $spec['data'][$name] = $this->evalJavascript($inline);
                }
            }
        }
        return $spec;
    }

    private function isFunction($code)
    {
        return preg_match('/function\s*\(/', $code);
    }

    private function evalPhp($php)
    {
        $php = preg_replace('/\[[\'"](.*)[\'"]\]/U', '->$1', $php);
        eval('$php = ' . $php . ';');
        return $php;
    }

    private function evalJavascript($javascript)
    {
        return $this->hb->evalJavascript('(' . $javascript . ')');
    }

    public function specProvider()
    {
        $specDir = __DIR__ . '/../vendor/jbboehr/handlebars-spec/spec';
        $tests = [];
        $ignore = ['parser.json', 'tokenizer.json'];
        foreach (glob($specDir . '/*.json') as $specFile) {
            if (!in_array(basename($specFile), $ignore)) {
                foreach (json_decode(file_get_contents($specFile), true) as $case) {
                    $tests = array_merge($tests, $this->createTests($case));
                }
            }
        }
        return $tests;
    }

    private function createTests($case)
    {
        $tests = [];
        $case['name'] = $case['description'] . ' - ' . $case['it'];
        foreach (['php', 'javascript'] as $type) {
            $new = $this->searchForCode($case, $type);
            if ($new && $new != $case) {
                $new['type'] = $type;
                $tests[] = [ $new ];
            }
        }
        // no code found
        if (empty($tests)) {
            $case['type'] = '';
            $tests[] = [ $case ];
        }
        return $tests;
    }

    private function searchForCode($node, $type)
    {
        foreach ($node as $k => $v) {
            if ($k == '!code') {
                return isset($node[$type]) ? $node[$type] : false;
            }
            if (is_array($v)) {
                $node[$k] = $this->searchForCode($v, $type);
                if ($node[$k] === false) {
                    return false;
                }
            }
        }
        return $node;
    }


    private function evaluateCode($code)
    {
        $js = $php = $skipped = false;
        if (isset($code['php']) && strstr($code['php'], 'Utils::')) {
            $skipped = "Code calls Utils class";
        }
        // some php function include calls to static class 'Utils', which we don't have
        elseif (isset($code['php'])) {
            /*
            // turn array references into object properties ($options['data'] -> $options->data
            $code['php'] = preg_replace('/\[[\'"](.*)[\'"]\]/U', '->$1', $code['php']);
            eval('$php = ' . $code['php'] . ';');
            */
            /*
            if (preg_match('/function\s*\(([^\)]*)\)\s*\{\s*(.*)}/s', $code['php'], $matches)) {
                $php = create_function($matches[1], $matches[2]);
                echo is_callable($php) ? "callable\n" : "not callable\n"; die();
            }
            */
        }
        if (!empty($code['javascript'])) {
            //echo "--JS: " . $code['javascript'] . "\n";
            $js = $this->hb->evalJavascript('(' . $code['javascript'] . ')');
        }
        return [$js, $php, $skipped];
    }
}
