<?php
/**
 * @license MIT
 * @copyright 2015 Matt Kynaston
 */

namespace KynxTest\V8js;

use Kynx\V8js\Handlebars;
use PHPUnit_Framework_TestCase as TestCase;
use Prophecy\Argument;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use V8Js;

class HandlebarsTest extends TestCase
{
    protected $baseDir;
    protected $logs;

    public function setUp()
    {
        $this->baseDir = dirname(__DIR__);
        $handlebarsSource = file_get_contents($this->baseDir . '/components/handlebars/handlebars.js');
        $runtimeSource = file_get_contents($this->baseDir . '/components/handlebars/handlebars.runtime.js');
        Handlebars::registerHandlebarsExtension($handlebarsSource);
        Handlebars::registerHandlebarsExtension($runtimeSource, true);
    }

    public function testRegistration()
    {
        $registered = V8Js::getExtensions();
        $this->assertArrayHasKey(Handlebars::EXTN_HANDLEBARS, $registered);
        $this->assertArrayHasKey(Handlebars::EXTN_RUNTIME, $registered);
        $this->assertFalse($registered['handlebars']['auto_enable']);
        $this->assertEmpty($registered['handlebars']['deps']);
        $this->assertFalse($registered['handlebars-runtime']['auto_enable']);
        $this->assertEmpty($registered['handlebars-runtime']['deps']);
    }

    public function testReRegistration()
    {
        $handlebarsSource = file_get_contents($this->baseDir . '/components/handlebars/handlebars.js');
        Handlebars::registerHandlebarsExtension($handlebarsSource);
        $registered = V8Js::getExtensions();
        $this->assertArrayHasKey(Handlebars::EXTN_HANDLEBARS, $registered);
    }

    public function testCompile()
    {
        $hb = Handlebars::create();
        $template = $hb->compile('<h1>{{ test }}</h1>');
        $result = $template(['test' => '**context variable**']);
        $this->assertEquals('<h1>**context variable**</h1>', $result);
    }

    /**
     * @expectedException \BadMethodCallException
     */
    public function testCompileRuntime()
    {
        $hb = Handlebars::create(true);
        $template = $hb->compile('<h1>{{ test }}</h1>');
    }

    /**
     * Hrm... looks like Handlebars doesn't throw the exception until the template is invoked
     * @expectedException \V8JsScriptException
     */
    public function testCompileBrokenTemplate()
    {
        $hb = Handlebars::create();
        $template = $hb->compile('<h1>{{ test ');
        $result = $template(['test' => '**context variable**']);
    }

    public function testCompileNoContext()
    {
        $hb = Handlebars::create();
        $template = $hb->compile('<h1>{{ test }}</h1>');
        $result = $template();
        $this->assertEquals('<h1></h1>', $result);
    }

    public function testCompileEmptyContext()
    {
        $hb = Handlebars::create();
        $template = $hb->compile('<h1>{{ test }}</h1>');
        $result = $template([]);
        $this->assertEquals('<h1></h1>', $result);
    }

    /**
     * @expectedException \V8JsScriptException
     */
    public function testCompileEmptyContextStrict()
    {
        $hb = Handlebars::create();
        $template = $hb->compile('<h1>{{ test }}</h1>', ['strict' => true]);
        $result = $template([]);
    }

    public function testPrecompile()
    {
        $hb = Handlebars::create();
        $compiled = $hb->precompile('<h1>{{ test }}</h1>');
        $this->assertContains('{"compiler":', $compiled, "Precompiled template doesn't contain a 'compiler' section");
        $this->assertContains('"main":function(', $compiled, "Precompiled template doesn't contain a 'main' function");
    }

    /**
     * @expectedException \BadMethodCallException
     */
    public function testPrecompileRuntime()
    {
        $hb = Handlebars::create(true);
        $compiled = $hb->precompile('<h1>{{ test }}</h1>');
    }

    /**
     * @expectedException \V8JsScriptException
     */
    public function testPrecompileBrokenTemplate()
    {
        $hb = Handlebars::create();
        $compiled = $hb->precompile('<h1>{{ test ');
    }

    public function testTemplate()
    {
        $hb = Handlebars::create();
        $compiled = $hb->precompile('<h1>{{ test }}</h1>');
        $template = $hb->template($compiled);
        $result = $template(['test' => '**context variable**']);
        $this->assertEquals('<h1>**context variable**</h1>', $result);
    }

    public function testTemplateRuntime()
    {
        $hb = Handlebars::create();
        $compiled = $hb->precompile('<h1>{{ test }}</h1>');
        $runtime = Handlebars::create(true);
        $template = $runtime->template($compiled);
        $result = $template(['test' => '**context variable**']);
        $this->assertEquals('<h1>**context variable**</h1>', $result);
    }

    public function testTemplateNoContext()
    {
        $hb = Handlebars::create();
        $compiled = $hb->precompile('<h1>{{ test }}</h1>');
        $template = $hb->template($compiled);
        $result = $template();
        $this->assertEquals('<h1></h1>', $result);
    }

    public function testTemplateEmptyContext()
    {
        $hb = Handlebars::create();
        $compiled = $hb->precompile('<h1>{{ test }}</h1>');
        $template = $hb->template($compiled);
        $result = $template([]);
        $this->assertEquals('<h1></h1>', $result);
    }

    /**
     * @expectedException \V8JsScriptException
     */
    public function testTemplateEmptyContextStrict()
    {
        $hb = Handlebars::create();
        $compiled = $hb->precompile('<h1>{{ test }}</h1>', ['strict' => true]);
        $template = $hb->template($compiled);
        $result = $template([]);
    }

    public function testRegisterPartial()
    {
        $hb = Handlebars::create();
        $hb->registerPartial('partial', '<h1>{{ test }}</h1>');
        $template = $hb->compile('{{> partial }}');
        $result = $template(['test' => '**context variable**']);
        $this->assertEquals('<h1>**context variable**</h1>', $result);
    }

    public function testReregisterPartial()
    {
        $hb = Handlebars::create();
        $hb->registerPartial('partial', '<h1>{{ test1 }}</h1>');
        $hb->registerPartial('partial', '<h1>{{ test2 }}</h1>');
        $template = $hb->compile('{{> partial }}');
        $result = $template(['test1' => '**first**', 'test2' => '**second**']);
        $this->assertEquals('<h1>**second**</h1>', $result);
    }

    public function testRegisterTwoPartials()
    {
        $hb = Handlebars::create();
        $hb->registerPartial('partial1', '<h1>{{ test1 }}</h1>');
        $hb->registerPartial('partial2', '<h1>{{ test2 }}</h1>');
        $template = $hb->compile('{{> partial1 }}{{> partial2 }}');
        $result = $template(['test1' => '**first**', 'test2' => '**second**']);
        $this->assertEquals('<h1>**first**</h1><h1>**second**</h1>', $result);
    }

    public function testRegisterPartialArray()
    {
        $hb = Handlebars::create();
        $arr = [
            'partial1' => '<h1>{{ test1 }}</h1>',
            'partial2' => '<h1>{{ test2 }}</h1>'
        ];
        $hb->registerPartial($arr);
        $template = $hb->compile('{{> partial1 }}{{> partial2 }}');
        $result = $template(['test1' => '**first**', 'test2' => '**second**']);
        $this->assertEquals('<h1>**first**</h1><h1>**second**</h1>', $result);
    }

    public function testRegisterPartialObject()
    {
        $hb = Handlebars::create();
        $obj = new \stdClass();
        $obj->partial1 = '<h1>{{ test1 }}</h1>';
        $obj->partial2 = '<h1>{{ test2 }}</h1>';
        $hb->registerPartial($obj);
        $template = $hb->compile('{{> partial1 }}{{> partial2 }}');
        $result = $template(['test1' => '**first**', 'test2' => '**second**']);
        $this->assertEquals('<h1>**first**</h1><h1>**second**</h1>', $result);
    }

    public function testRegisterPartialJsString()
    {
        $hb = Handlebars::create();
        $js = '{
            "partial1" : "<h1>{{ test1 }}</h1>",
            "partial2" : "<h1>{{ test2 }}</h1>"
        }';
        $hb->registerPartial($js);
        $template = $hb->compile('{{> partial1 }}{{> partial2 }}');
        $result = $template(['test1' => '**first**', 'test2' => '**second**']);
        $this->assertEquals('<h1>**first**</h1><h1>**second**</h1>', $result);
    }

    public function testRegisterPrecompiledPartial()
    {
        $hb = Handlebars::create();
        $partial = $hb->precompile('<h1>{{ test }}</h1>');
        $hb->registerPartial('partial', $hb->template($partial));
        $template = $hb->compile('{{> partial }}');
        $result = $template(['test' => '**context variable**']);
        $this->assertEquals('<h1>**context variable**</h1>', $result);
    }

    /**
     * @expectedException \V8JsScriptException
     * @expectedExceptionMessage Error: The partial partial could not be compiled when running in runtime-only mode
     */
    public function testRegisterPartialRuntime()
    {
        $hb = Handlebars::create();
        $precompiled = $hb->precompile('{{> partial }}');

        $hb = Handlebars::create(true);
        $hb->registerPartial('partial', '<h1>{{ test }}</h1>');
        $template = $hb->template($precompiled);
        $result = $template(['test' => '**context variable**']);
    }

    /**
     * @expectedException \V8JsScriptException
     * @expectedExceptionMessage Error: The partial partial could not be found
     */
    public function testUnregisterPartial()
    {
        $hb = Handlebars::create();
        $hb->registerPartial('partial', '<h1>{{ test }}</h1>');
        $hb->unregisterPartial('partial');
        $template = $hb->compile('{{> partial }}');
        $result = $template(['test' => '**context variable**']);
    }

    public function testRegisterJsBasicBlockHelper()
    {
        $hb = Handlebars::create();
        $hb->registerHelper('helper', 'function(options) {
            return "<h1>" + options.fn(this) + "</h1>"
        }');
        $template = $hb->compile('{{#helper}}{{ content }}{{/helper}}');
        $result = $template(['content' => '**content output**']);
        $this->assertEquals('<h1>**content output**</h1>', $result);
    }

    public function testRegisterPhpBasicBlockHelper()
    {
        $hb = Handlebars::create();
        $hb->registerHelper('helper', function ($options) {
            return '<h1>' . $options->fn($this) . '</h1>';
        });
        $template = $hb->compile('{{#helper}}{{ content }}{{/helper}}');
        $result = $template(['content' => '**content output**']);
        $this->assertEquals('<h1>**content output**</h1>', $result);
    }

    public function testRegisterPhpIteratorHelper()
    {
        $hb = Handlebars::create();
        $hb->registerHelper('helper', function ($context, $options) {
            $ret = '';
            for ($i=0; $i<count($context); $i++) {
                $ret .= '<h1>' . $options->fn($context[$i]) . '</h1>';
            }
            return $ret;
        });
        $template = $hb->compile('{{#helper contents}}{{ item }}{{/helper}}');
        $result = $template(['contents' => [['item' => '**first**'], ['item' => '**second**']]]);
        $this->assertEquals('<h1>**first**</h1><h1>**second**</h1>', $result);
    }

    /**
     * @expectedException \V8JsScriptException
     * @expectedExceptionMessage Error: "helper" not defined
     */
    public function testUnregisterHelper()
    {
        $hb = Handlebars::create();
        $hb->registerHelper('helper', 'function(options) {
            return "<h1>" + options.fn(this) + "</h1>"
        }');
        $hb->unregisterHelper('helper');
        $template = $hb->compile('{{#helper}}{{ content }}{{/helper}}', ['strict' => true]);
        $result = $template(['content' => '**content output**']);
    }

    // @todo Decorator tests... can't find any simple examples of decorators :(

    public function testSetLogLevel()
    {
        $hb = Handlebars::create();
        $template = $hb->compile('<h1>{{ @level }}</h1>');
        $result = $template([], ['data' => ['level' => 'debug']]);
        $this->assertEquals('<h1>debug</h1>', $result);
    }

    public function testLogError()
    {
        $hb = Handlebars::create();
        $level = $message = null;
        $logger = $this->getLogger($level, $message)->reveal();
        $hb->setLogger($logger);
        $template = $hb->compile('{{ log "Error!" level="error" }}');
        $result = $template([]);
        $this->assertEquals('error', $level);
        $this->assertEquals("Error!", $message);
        $this->assertEquals('', $result);
    }

    public function testLogWarn()
    {
        $hb = Handlebars::create();
        $level = $message = null;
        $logger = $this->getLogger($level, $message)->reveal();
        $hb->setLogger($logger);
        $template = $hb->compile('{{ log "Warning!" level="warn" }}');
        $result = $template([]);
        $this->assertEquals('warning', $level);
        $this->assertEquals("Warning!", $message);
        $this->assertEquals('', $result);
    }

    public function testLogWarning()
    {
        $hb = Handlebars::create();
        $level = $message = null;
        $logger = $this->getLogger($level, $message)->reveal();
        $hb->setLogger($logger);
        $template = $hb->compile('{{ log "Warning!" level="warning" }}');
        $result = $template([]);
        $this->assertEquals('warning', $level);
        $this->assertEquals("Warning!", $message);
        $this->assertEquals('', $result);
    }

    public function testLogInfo()
    {
        $hb = Handlebars::create();
        $level = $message = null;
        $logger = $this->getLogger($level, $message)->reveal();
        $hb->setLogger($logger);
        $template = $hb->compile('{{ log "Info!" level="info" }}');
        $result = $template([]);
        $this->assertEquals('info', $level);
        $this->assertEquals("Info!", $message);
        $this->assertEquals('', $result);
    }

    public function testLogDebug()
    {
        $hb = Handlebars::create();
        $level = $message = null;
        $logger = $this->getLogger($level, $message)->reveal();
        $hb->setLogger($logger);
        $template = $hb->compile('{{ log "Debug!" level="debug" }}');
        $result = $template([]);
        $this->assertEquals('debug', $level);
        $this->assertEquals("Debug!", $message);
        $this->assertEquals('', $result);
    }

    /**
     * @return \Prophecy\Prophecy\ObjectProphecy
     */
    protected function getLogger(&$level, &$message)
    {
        $logger = $this->prophesize(LoggerInterface::class);
        $logger->log(Argument::any(), Argument::any())->will(function ($args) use (&$level, &$message) {
            $level = $args[0];
            $message = $args[1];
        });
        return $logger;
    }
}
