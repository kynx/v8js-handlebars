<?php
/**
 * @author: matt
 * @copyright: 2015 Claritum Limited
 * @license: Commercial
 */

namespace KynxTest\V8js;

use Kynx\V8js\Handlebars;
use PHPUnit_Framework_TestCase as TestCase;
use Prophecy\Argument;
use Psr\Log\LoggerInterface;
use V8Js;
use V8JsScriptException;

class SpecTest extends TestCase
{
    /**
     * @var Handlebars
     */
    private $hb;
    private static $counter = 0;
    private static $counters = [];

    public function setUp()
    {
        $handlebarsSource = file_get_contents(dirname(__DIR__) . '/components/handlebars/handlebars.js');
        Handlebars::registerHandlebarsExtension($handlebarsSource);
        $this->hb = new Handlebars();
        $this->hb->setTestCase($this);
    }

    /**
     * @dataProvider specProvider
     */
    public function testSpec($case)
    {
        if ($case['type'] == 'php') {
            $this->markTestSkipped("Still working on php...");
        }
        if (!isset($case['name'])) {
            var_dump($case); die();
        }
        //echo "$name\n"; flush();
        $name = $case['name'] . ($case['type'] ? ' - ' . $case['type'] : '');
        if (empty(self::$counters[$name])) {
            self::$counters[$name] = 0;
        }
        self::$counter++;
        self::$counters[$name]++;
        $testId = $name . ' #' . self::$counters[$name];

        $spec = $this->prepareSpec($case);

        /*
        if ($testId == 'inline partials - should define inline partials for block #1') {
            var_dump($case);
            var_dump($spec);
            die();
        }
        */

        if (!$spec) {
            $this->markTestSkipped("Couldn't evaluate test '" . $name . "'");
        }

        $level = null;
        $messages = [];
        $expectedMessages = [];
        if (isset($spec['log'])) {
            $expectedMessages = is_array($spec['log']['message']) ? $spec['log']['message'] : [$spec['log']['message']];
            $logger = $this->prophesize(LoggerInterface::class);
            $logger->log(Argument::any(), Argument::any())->will(function ($args) use (&$level, &$messages) {
                    $level = $args[0];
                    $messages[] = $args[1];
                })
                ->shouldBeCalledTimes(count($expectedMessages));
            $this->hb->setLogger($logger->reveal());
        }

        if (!empty($spec['globalPartials'])) {
            $this->hb->registerPartial($spec['globalPartials']);
        }
        if (!empty($spec['globalHelpers'])) {
            $this->hb->registerHelper($spec['globalHelpers']);
        }
        if (!empty($spec['globalDecorators'])) {
            $this->hb->registerDecorator($spec['globalDecorators']);
        }
        $template = $this->hb->compile($spec['template'], $spec['compileOptions']);
        if ($spec['exception']) {
            $this->setExpectedException(V8JsScriptException::class);
        }
        $actual = $template($spec['data'], $spec['options']);
        if (!$spec['exception']) {
            if (is_array($spec['expected'])) {
                $this->assertContains($actual, $spec['expected'], $testId . ': Output does not match');
            } else {
                $this->assertEquals($spec['expected'], $actual, $testId . ': Output does not match');
            }
        }
        if (isset($spec['log'])) {
            $eLevel = isset($spec['log']['level']) ? $spec['log']['level'] : 'info';
            $this->assertEquals($eLevel, $level, $testId . ': Log level does not match');
            $this->assertEquals($expectedMessages, $messages, $testId . ': Log message does not match');
        }
    }

    private function prepareSpec($spec)
    {
        $default = [
            'partials' => [],
            'helpers' => [],
            'decorators' => [],
            'data' => [],
            'options' => [],
            'compileOptions' => [],
            'globalPartials' => [],
            'globalHelpers' => [],
            'globalDecorators' => [],
            'expected' => null,
            'exception' => false,
            'message' => '',
        ];
        $spec = array_merge($default, $spec);
        if (!empty($spec['helpers'])) {
            $spec['options']['helpers'] = $spec['helpers'];
        }
        if (!empty($spec['partials'])) {
            $spec['options']['partials'] = $spec['partials'];
        }
        if (!empty($spec['decorators'])) {
            $spec['options']['decorators'] = $spec['decorators'];
        }
        return $this->evalCode($spec, $spec['type']);
    }

    private function evalCode($node, $type)
    {
        foreach ($node as $k => $v) {
            if ("$k" == '!code') {
                if (isset($node[$type])) {
                    if ($type == 'javascript') {
                        return $this->hb->evalJavascript('(' . $node[$type] . ')');
                    } else {
                        $php = preg_replace('/\[[\'"](.*)[\'"]\]/U', '->$1', $node[$type]);
                        eval('$php = ' . $php . ';');
                        return $php;
                    }
                }
                return false;
            }
            if (is_array($v)) {
                $node[$k] = $this->evalCode($v, $type);
                if ($node[$k] === false) {
                    return false;
                }
            }
        }
        return $node;
    }

    public function specProvider()
    {
        $specDir = __DIR__ . '/spec';
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
            $new = $case;
            if ($this->hasCode($case, $type)) {
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

    private function hasCode($node, $type)
    {
        foreach ($node as $k => $v) {
            if ("$k" == '!code') {
                return isset($node[$type]);
            }
            if (is_array($v) && $this->hasCode($v, $type)) {
                return true;
            }
        }
        return false;
    }
}
