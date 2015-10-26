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
     * @var V8Js
     */
    private $v8;
    private $counter = 0;

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
        $this->markTestSkipped("Still working on this...");
        /*
        echo "$name\n"; flush();
        $this->counter++;
        if (!empty($partials)) {
            $this->hb->registerPartial($partials);
        }
        if (!empty($helpers)) {
            $this->hb->registerHelper($helpers);
        }
        $template = $this->hb->compile($template, $compileOptions);
        $actual = $template($data);
        $this->assertEquals($expected, $actual, $name . ' ' . $this->counter);
        */
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