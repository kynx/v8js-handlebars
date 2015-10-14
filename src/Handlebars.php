<?php
/**
 * @license MIT
 */

namespace Kynx\V8js;

final class Handlebars
{
    /**
     * @var \V8Js
     */
    private $v8;

    public function __construct($runtime = false, $variables = [], $extensions = [], $report_uncaught_exceptions = true)
    {
        $extension = $runtime ? 'handlebars-runtime' : 'handlebars';
        $extensions = array_merge($extensions, [$extension]);
        $this->v8 = new \V8Js('phpHb', $variables, $extensions, $report_uncaught_exceptions);
    }

    public static function register($handlebarsSource)
    {
        \V8Js::registerExtension('handlebars', $handlebarsSource, array(), false);
    }

    public static function registerRuntime($runtimeSource)
    {
        \V8Js::registerExtension('handlebars-runtime', $runtimeSource, array(), false);
    }

    public static function create($runtime = false, $variables = [], $extensions = [], $report_uncaught_exceptions = true)
    {
        return new Handlebars($runtime, $variables, $extensions, $report_uncaught_exceptions);
    }

    public function precompile($template, $options = false)
    {
        $this->v8->template = $template;
        $this->v8->options = $options ?: [];
        return $this->v8->executeString('Handlebars.precompile(phpHb.template, phpHb.options)');
    }

    public function registerPartial($name, $partial)
    {
        $this->v8->name = $name;
        if (is_object($partial)) {
            // PHP object
            $this->v8->partial = $partial;
            return $this->v8->executeString('Handlebars.registerPartial(phpHb.name, phpHb.partial)');
        }
        elseif (is_string($partial)) {
            // @fixme How to check this is valid?
            return $this->v8->executeString('Handlebars.registerPartial(phpHb.name, ' . $partial . ')');
        }
        throw new \BadMethodCallException("Invalid partial '" . $partial . "'");
    }

    public function unregisterPartial($name)
    {
        $this->v8->name = $name;
        return $this->v8->executeString('Handlebars.unregisterPartial(phpHb.name)');
    }

    public function registerHelper($name, $helper)
    {

    }

    public function render($templateSpec, $variables = [])
    {
        $this->v8->templateSpec = $templateSpec;
        $this->v8->variables = $variables;
        return $this->v8->executeString('template = Handlebars.template(phpHb.templateSpec); template(phpHb.variables)');
    }
}
