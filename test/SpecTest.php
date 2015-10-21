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
    public function testSpec($name, $template, $data, $partials, $helpers, $compileOptions, $expected, $skipped)
    {
        if ($skipped) {
            $this->markTestSkipped($name . ':' . $skipped);
            return;
        }
        // echo "$name\n";
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
        $skipped = false;
        foreach (['data', 'helpers'] as $sec) {
            if (isset($case[$sec]) && is_array($case[$sec])) {
                foreach ($case[$sec] as $name => $value) {
                    if (!empty($value['!code'])) {
                        list($j, $p, $skipped) = $this->evaluateCode($value);
                        if ($j) {
                            $js[$sec][$name] = $j;
                        }
                        if ($p) {
                            $php[$sec][$name] = $p;
                        }
                        if (!$skipped && !($j || $p)) {
                            $skipped = 'No code could be evaluated';
                        }
                        unset($case[$sec][$name]);
                    }
                }
            }
        }
        if (empty($js) && empty($php)) {
            $tests[] = $this->makeTest($testName, $case, $skipped);
        }
        if (!empty($js)) {
            $tests[] = $this->makeTest($testName, $case, '', $js, ' - js');
        }
        if (!empty($php)) {
            $tests[] = $this->makeTest($testName, $case, '', $php, ' - php');
        }
        return $tests;
    }

    private function makeTest($testName, $case, $skipped, $override = [], $suffix = '')
    {
        foreach (['data', 'helpers'] as $sec) {
            if ((isset($case[$sec]) && is_array($case[$sec])) || (isset($override[$sec]) && is_array($override[$sec]))) {
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
            'expected' => isset($case['expected']) ? $case['expected'] : '',
            'skipped' => $skipped
        ];
    }

    private function evaluateCode($code)
    {
        $js = $php = $skipped = false;
        if (isset($code['php']) && strstr($code['php'], 'Utils::')) {
            $skipped = "Code calls Utils class";
        }
        // some php function include calls to static class 'Utils', which we don't have
        elseif (isset($code['php'])) {
            // turn array references into object properties ($options['data'] -> $options->data
            $code['php'] = preg_replace('/\[[\'"](.*)[\'"]\]/U', '->$1', $code['php']);
            eval('$php = ' . $code['php'] . ';');

            /*
            if (preg_match('/function\s*\(([^\)]*)\)\s*\{\s*(.*)}/s', $code['php'], $matches)) {
                $php = create_function($matches[1], $matches[2]);
                echo is_callable($php) ? "callable\n" : "not callable\n"; die();
            }
            */
        }
        if (!empty($code['javascript'])) {
            //echo "--JS: " . $code['javascript'] . "\n";
            //$js = $this->v8->executeString('(' . $code['javascript'] . ')');
        }
        return [$js, $php, $skipped];
    }
}