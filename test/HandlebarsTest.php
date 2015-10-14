<?php
/**
 * @copyright 2015 Matt Kynaston <matt@kynx.org>
 * @license MIT
 */

namespace KynxTest\V8js;

use Kynx\V8js\Handlebars;
use PHPUnit_Framework_TestCase as TestCase;

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
        $registered = \V8Js::getExtensions();
        $this->assertArrayHasKey('handlebars', $registered);
        $this->assertArrayHasKey('handlebars-runtime', $registered);
        $this->assertFalse($registered['handlebars']['auto_enable']);
        $this->assertEmpty($registered['handlebars']['deps']);
        $this->assertFalse($registered['handlebars-runtime']['auto_enable']);
        $this->assertEmpty($registered['handlebars-runtime']['deps']);
    }

    public function testReRegistration()
    {
        $handlebarsSource = file_get_contents($this->baseDir . '/components/handlebars/handlebars-built.js');
        Handlebars::register($handlebarsSource);
        $registered = \V8Js::getExtensions();
        $this->assertArrayHasKey('handlebars', $registered);
    }

    public function testPrecompile()
    {
        $hb = Handlebars::create();
        $compiled = $hb->precompile('<h1>{{ test }}</h1>');
        $expected = <<<EOT
{"compiler":[7,">= 4.0.0"],"main":function(container,depth0,helpers,partials,data) {
        var helper;

        return "<h1>"
        + container.escapeExpression(((helper = (helper = helpers.test || (depth0 != null ? depth0.test : depth0)) != null ? helper : helpers.helperMissing),(typeof helper === "function" ? helper.call(depth0 != null ? depth0 : {},{"name":"test","hash":{},"data":data}) : helper)))
    + "</h1>";
},"useData":true}
EOT;
        $this->assertEquals(preg_replace('/\s+/', '', $expected), preg_replace('/\s+/', '', $compiled));
    }
}
