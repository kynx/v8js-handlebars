<?php
/**
 * @license MIT
 */

namespace Kynx\V8js;

use Kynx\V8js\Exception\ScriptException;

final class Handlebars
{
    const EXTN_HANDLEBARS = 'handlebars';
    const EXTN_RUNTIME = 'handlebars-runtime';

    /**
     * @var \V8Js
     */
    private $v8;

    /**
     * @var \V8JSObject
     */
    private $jsTemplate;

    /**
     * Private constructor. Always get instance via self::create() factory
     * @param string $extension
     * @param array $variables
     * @param array $extensions
     * @param bool|true $report_uncaught_exceptions
     */
    private function __construct($extension, $variables, $extensions, $report_uncaught_exceptions)
    {
        $extensions = array_merge($extensions, [$extension]);
        $this->v8 = new \V8Js('phpHb', $variables, $extensions, $report_uncaught_exceptions);
    }

    /**
     * Registers handlebars script with V8Js
     *
     * This *must* be called before the first call to self::create()
     * @param $handlebarsSource
     */
    public static function register($handlebarsSource)
    {
        \V8Js::registerExtension(self::EXTN_HANDLEBARS, $handlebarsSource, array(), false);
    }

    /**
     * Registers handlebars runtime with V8Js
     *
     * This *must* be called before the first call to self::create().
     * @param $runtimeSource
     */
    public static function registerRuntime($runtimeSource)
    {
        \V8Js::registerExtension(self::EXTN_RUNTIME, $runtimeSource, array(), false);
    }

    /**
     * Returns configured Handlebars instance
     * @param string $extension
     * @param array $variables
     * @param array $extensions
     * @param bool|true $report_uncaught_exceptions
     * @return Handlebars
     */
    public static function create($extension = self::EXTN_HANDLEBARS, $variables = [], $extensions = [], $report_uncaught_exceptions = true)
    {
        if (!in_array($extension, [self::EXTN_HANDLEBARS, self::EXTN_RUNTIME])) {
            throw new \InvalidArgumentException(
                sprintf("Extension should be one of ['%s', '%s'], '%s' given",
                    self::EXTN_HANDLEBARS, self::EXTN_RUNTIME, $extension
                )
            );
        }
        if (!in_array($extension, \V8Js::getExtensions())) {
            throw new \InvalidArgumentException(
                sprintf("Extension '%s' not registered", $extension)
            );
        }
        return new Handlebars($extension, $variables, $extensions, $report_uncaught_exceptions);
    }

    /**
     * Runs template with given context, returning result
     * @param array $context
     * @param array $options
     * @return string
     */
    public function __invoke($context, $options = [])
    {
        return $this->jsTemplate($context, $options);
    }

    /**
     * Compiles a template so it can be executed immediately
     * @param string $template
     * @param array $options
     */
    public function compile($template, $options = [])
    {
        if (!in_array(self::EXTN_HANDLEBARS, $this->v8->getExtensions())) {
            throw new \BadMethodCallException("Cannot compile templates without full handlebars extension");
        }
        try {
            $this->v8->template = $template;
            $this->v8->options = $options ?: [];
            $this->jsTemplate = $this->v8->executeString('Handlebars.compile(phpHb.template, phpHb.options)');
        }
        catch (\V8JsScriptException $e) {
            throw new ScriptException($e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * Precompiles a given template so it can be sent to the client and executed without compilation
     * @param string $template
     * @param array $options
     * @return mixed
     */
    public function precompile($template, $options = [])
    {
        if (!in_array(self::EXTN_HANDLEBARS, $this->v8->getExtensions())) {
            throw new \BadMethodCallException("Cannot precompile templates without full handlebars extension");
        }
        try {
            $this->v8->template = $template;
            $this->v8->options = $options;
            return $this->v8->executeString('Handlebars.precompile(phpHb.template, phpHb.options)');
        }
        catch (\V8JsScriptException $e) {
            throw new ScriptException($e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * Sets up a template that was precompiled with self::precompile()
     * @param $templateSpec
     */
    public function template($templateSpec)
    {
        try {
            $this->v8->templateSpec = $templateSpec;
            $this->jsTemplate = $this->v8->executeScript('Handlebars.template(phpHb.templateSpec)');
        }
        catch (\V8JsScriptException $e) {
            throw new ScriptException($e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * Registers partials accessible by any template in the environment
     * @param string $name
     * @param string $partial
     */
    public function registerPartial($name, $partial)
    {
        $this->registerscript('partial', $name, $partial);
    }

    /**
     * Unregisters a previously registered partial
     * @param string $name
     */
    public function unregisterPartial($name)
    {
        $this->unregisterScript('partial', $name);
    }

    /**
     * Registers helpers accessible by any template in the environment
     * @param string $name
     * @param $helper
     */
    public function registerHelper($name, $helper)
    {
        $this->registerScript('helper', $name, $helper);
    }

    /**
     * Unregisters a previously registered helper.
     * @param string $name
     */
    public function unregisterHelper($name)
    {
        $this->unregisterScript('helper', $name);
    }

    /**
     * Registers a decorator accessible by any template in the environment
     * @param $name
     * @param $decorator
     */
    public function registerDecorator($name, $decorator)
    {
        $this->registerScript('decorator', $name, $decorator);
    }

    /**
     * Unregisters a previously registered decorator
     * @param string $name
     */
    public function unregisterDecorator($name)
    {
        $this->unregisterScript('decorator', $name);
    }

    private function registerScript($type, $name, $script)
    {
        $method = 'register' . ucfirst($type);
        $this->v8->name = $name;
        try {
            if (is_object($script)) {
                // PHP object
                $this->v8->script = $script;
                $this->v8->executeString('Handlebars.' . $method . '(phpHb.name, phpHb.script)');
            } elseif (is_string($script)) {
                // @fixme How to check this is valid?
                $this->v8->executeString('Handlebars.' . $script . '(phpHb.name, ' . $script . ')');
            }
        }
        catch (\V8JsScriptException $e) {
            throw new ScriptException($e->getMessage(), $e->getCode(), $e);
        }
        throw new \BadMethodCallException("Invalid ' . $type . ':" . gettype($script));
    }

    private function unregisterScript($type, $name)
    {
        $method = 'unregister' . ucfirst($type);
        $this->v8->name = $name;
        $this->v8->executeString('Handlebars.' . $method . '(phpHb.name)');
    }
}
