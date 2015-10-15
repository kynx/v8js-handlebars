<?php
/**
 * @copyright 2015 Matt Kynaston <matt@kynx.org>
 * @license MIT
 */

namespace KynxTest\V8js;

use Kynx\V8js\Handlebars;
use PHPUnit_Framework_TestCase as TestCase;
use V8Js;

class HandlebarsTest extends TestCase
{
    protected $baseDir;

    public function setUp()
    {
        $this->baseDir = dirname(__DIR__);
        $handlebarsSource = file_get_contents($this->baseDir . '/components/handlebars/handlebars-built.js');
        $runtimeSource = file_get_contents($this->baseDir . '/components/handlebars/handlebars.runtime.js');
        Handlebars::register($handlebarsSource);
        Handlebars::registerRuntime($runtimeSource);
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
        $handlebarsSource = file_get_contents($this->baseDir . '/components/handlebars/handlebars-built.js');
        Handlebars::register($handlebarsSource);
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
        $hb->registerHelper('helper', function($this, $options) {
            return '<h1>' . $options->fn($this) . '</h1>';
        });
        $template = $hb->compile('{{#helper}}{{ content }}{{/helper}}');
        $result = $template(['content' => '**content output**']);
        $this->assertEquals('<h1>**content output**</h1>', $result);
    }

    /**
     * @expectedException \V8JsScriptException
     * @expectedExceptionMessage Error: "helper" not defined in [object Array]
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



}
