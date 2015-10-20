<?php
/**
 * @license MIT
 */

namespace KynxTest\V8js;

use Kynx\V8js\Handlebars;
use PHPUnit_Framework_Assert as Assert;
use PHPUnit_Framework_AssertionFailedError as AssertionFailedError;
use PHPUnit_Framework_Test as Test;
use PHPUnit_Framework_TestResult as TestResult;
use PHP_Timer as Timer;

/**
 * Class to test against zorbius' HandlebarsTest fixtures
 *
 * To use this you'll need to `git clone https://github.com/zordius/HandlebarsTest` and do a:
 *     cp /path/to/HandlebarsTest/fixtures/*.{json,txt,tmpl} tests/zordius
 *
 */
class ZordiusTest implements Test
{
    protected $tests = [];
    protected $allowArrayObjectTypes = false;

    public function __construct($zordiusDir = false)
    {
        if ($zordiusDir === false) {
            $zordiusDir = __DIR__ . '/zordius';
        }
        $this->tests = glob($zordiusDir . '/*.json');
        $this->allowArrayObjectTypes = getenv('ALLOW_ARRAY_OBJECT_TYPES');
        $handlebarsSource = file_get_contents(dirname(__DIR__) . '/components/handlebars/handlebars.js');
        Handlebars::registerHandlebarsExtension($handlebarsSource);
    }

    public static function suite()
    {
        return new self();
    }


    public function run(TestResult $result = null)
    {
        if ($result === null) {
            $result = new TestResult();
        }

        foreach ($this->tests as $json) {
            $result->startTest($this);
            Timer::start();
            $stopTime = null;

            $baseName = $tmpl = $context = $actual = '';
            try {
                preg_match('/(.*)(-[0-9]*).json$/', $json, $matches);
                $base = $matches[1] . $matches[2];
                $baseName = basename($base);
                $dirName = dirname($json);

                $hb = new Handlebars();
                $hb->registerPartial([
                    'partial1' => file_get_contents($dirName . '/partial001.tmpl'),
                    'partial001' => file_get_contents($dirName . '/partial001.tmpl'),
                    'partial2' => file_get_contents($dirName . '/partial002.tmpl'),
                    '001-simple-vars' => file_get_contents($dirName . '/001-simple-vars.tmpl'),
                    '017-hb-with' => file_get_contents($dirName . '/017-hb-with.tmpl')
                ]);
                $hb->registerHelper('helper1', "function (url, text) {
                    return '<a href=\"' + url + '\">' + text + '</a>';
                }");
                $hb->registerHelper('helper2', "function (options) {
                    return '<a href=\"' + options.hash.url + '\">' + options.hash.text + '</a>(' + options.hash['ur\"l'] + ')';
                }");
                $hb->registerHelper('helper3', "function () {
                    var options = arguments[arguments.length-1];
                    return options.fn(['test1', 'test2', 'test3']);
                }");
                $hb->registerHelper('helper4', "function () {
                    var options = arguments[arguments.length-1];

                    if (typeof options.hash.val !== 'undefined') {
                        this.helper4_value = options.hash.val % 2;
                        return options.fn(this);
                    }
                    if (typeof options.hash.odd !== 'undefined') {
                        return options.fn([1,3,5,7,9]);
                    }
                    return '';
                }");
                $tmpl = file_get_contents($matches[1] . '.tmpl');
                $context = json_decode(file_get_contents($json), true);
                $expected = file_get_contents($base . '.txt');

                $template = $hb->compile($tmpl);
                $actual = $template($context);

                if ($this->allowArrayObjectTypes) {
                    // V8Js objects have a different string representation. If these appear in the output there
                    // is something wrong with the template in the first place: they are safe to ignore
                    $actual = str_replace('[object Array]', '[object Object]', $actual);
                }

                Assert::assertEquals($expected, $actual, "'$baseName' failed");
            }
            catch (AssertionFailedError $e) {
                $stopTime = Timer::stop();
                $result->addFailure($this, $e, $stopTime);
            }
            catch (\V8JsScriptException $e) {
                $stopTime = Timer::stop();
                $result->addFailure($this, new AssertionFailedError("'$baseName' failed", 0, $e), $stopTime);
            }
            catch (\Exception $e) {
                $stopTime = Timer::stop();
                $result->addError($this, $e, $stopTime);
            }

            if ($stopTime === null) {
                $stopTime = Timer::stop();
            }

            $result->endTest($this, $stopTime);
        }

        return $result;
    }

    public function count()
    {
        return 1;
    }
}
