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
        $dirName = __DIR__ . '/zordius';
        $this->allowArrayObjectTypes = getenv('ALLOW_ARRAY_OBJECT_TYPES');
        $handlebarsSource = file_get_contents(dirname(__DIR__) . '/components/handlebars/handlebars.js');
        Handlebars::registerHandlebarsExtension($handlebarsSource);
    }

    /**
     * @dataProvider dataProvider
     */
    public function testSpec($name, $template, $data, $partials, $helpers, $compileOptions, $expected)
    {
        $this->counter++;
        $hb = new Handlebars();
        if (!empty($partials)) {
            $hb->registerPartial($partials);
        }
        if (!empty($helpers)) {
            $hb->registerHelper($helpers);
        }
        $template = $hb->compile($template, $compileOptions);
        $actual = $template($data);
        $this->assertEquals($expected, $actual, $name . ' ' . $this->counter);
    }

    public function dataProvider()
    {
        $this->v8 = new V8Js();
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
        $testName = $case['description'] . ' - ' . $case['it'];
        $js = $php = [];
        if (isset($case['data']) && is_array($case['data'])) {
            foreach ($case['data'] as $name => $value) {
                if (!empty($value['!code'])) {
                    list($j, $p) = $this->evaluateCode($value);
                    if ($j) {
                        $js['data'][$name] = $j;
                    }
                    if ($p) {
                        $php['data'][$name] = $p;
                    }
                    unset($case['data'][$name]);
                }
            }
        }
        if (!empty($case['helpers'])) {
            foreach ($case['helpers'] as $name => $value) {
                if (!empty($helper['!code'])) {
                    list($j, $p) = $this->evaluateCode($value);
                    if ($j) {
                        $js['helpers'][$name] = $j;
                    }
                    if ($php) {
                        $php['helpers'][$name] = $p;
                    }
                    unset($case['helpers'][$name]);
                }
            }
        }
        if (empty($js) && empty($php)) {
            $tests[] = $this->makeTest($testName, $case);
        }
        if (!empty($js)) {
            $tests[] = $this->makeTest($testName, $case, $js, ' - js');
        }
        if (!empty($php)) {
            $tests[] = $this->makeTest($testName, $case, $php, ' - php');
        }
        return $tests;
    }

    private function makeTest($testName, $case, $override = [], $suffix = '')
    {
        foreach (['data', 'helpers'] as $sec) {
            if (isset($case[$sec]) && (is_array($case[$sec]) || is_array($override[$sec]))) {
                if (empty($case[$sec])) {
                    $case[$sec] = [];
                }
                if (empty($override[$sec])) {
                    $override[$sec] = [];
                }
                $case[$sec] = array_merge($case[$sec], $override[$sec]);
            }
        }
        if (empty($case['partials'])) {
            $case['partials'] = [];
        }
        if (empty($case['compileOptions'])) {
            $case['compileOptions'] = [];
        }

        return [
            'name' => $testName . $suffix,
            'template' => $case['template'],
            'data' => array_key_exists('data', $case) ? $case['data'] : [],
            'partials' => $case['partials'],
            'helpers' => empty($case['helpers']) ? [] : $case['helpers'],
            'compileOptions' => $case['compileOptions'],
            'expected' => isset($case['expected']) ? $case['expected'] : ''
        ];
    }

    private function evaluateCode($code)
    {
        $js = $php = false;
        if (!empty($code['php'])) {
            $php = eval($code['php'] . ';');
        }
        if (!empty($code['javascript'])) {
            //echo "--JS: " . $code['javascript'] . "\n";
            //$js = $this->v8->executeString('(' . $code['javascript'] . ')');
        }
        return [$js, $php];
    }
}