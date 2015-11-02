<?php
/**
 * @license MIT
 */

namespace KynxTest\V8js;

use Kynx\V8js\Handlebars as BaseHandlebars;
use PHPUnit_Framework_TestCase as TestCase;

class Handlebars extends BaseHandlebars
{

    public function evalJavascript($javascript)
    {
        return $this->v8->executeString(
            '(' . $javascript . ')',
            __CLASS__ . '::' . __METHOD__ . '()'
        );
    }

    /**
     * @param TestCase $testCase
     */
    public function setTestCase(TestCase $testCase)
    {
        $this->v8->testCase = $testCase;
        $this->v8->executeString(
            'function equals(actual, expected, message) {
                kynx.testCase.assertEquals(expected, actual, message);
            }
            equal = equals',
            __CLASS__ . '::' . __METHOD__ . '()'
        );
    }
}
