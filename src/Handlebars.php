<?php
/**
 * @license MIT
 */

namespace Kynx\V8js;

use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerInterface;
use V8Js;

/**
 * Thin wrapper around handlebars.js
 * @link http://handlebarsjs.com/reference.html
 */
final class Handlebars implements LoggerAwareInterface
{
    const EXTN_HANDLEBARS = 'handlebars';
    const EXTN_RUNTIME = 'handlebars-runtime';

    /**
     * @var V8Js
     */
    private $v8;
    private $isRuntime;

    /**
     * Private constructor. Always get instance via self::create() factory
     * @param array $extensions
     * @param bool|true $report_uncaught_exceptions
     */
    private function __construct($extensions, $report_uncaught_exceptions = true)
    {
        $this->v8 = new V8Js('phpHb', [], $extensions, $report_uncaught_exceptions);
        $this->isRuntime = in_array(self::EXTN_RUNTIME, $extensions);
    }

    /**
     * Registers handlebars script with V8Js
     *
     * This *must* be called before the first call to self::create()
     * @param $handlebarsSource
     */
    public static function register($handlebarsSource)
    {
        if (empty(V8Js::getExtensions()[self::EXTN_HANDLEBARS])) {
            V8Js::registerExtension(self::EXTN_HANDLEBARS, $handlebarsSource, array(), false);
        }
    }

    /**
     * Registers handlebars runtime with V8Js
     *
     * This *must* be called before the first call to self::create().
     * @param $runtimeSource
     */
    public static function registerRuntime($runtimeSource)
    {
        if (empty(V8Js::getExtensions()[self::EXTN_RUNTIME])) {
            V8Js::registerExtension(self::EXTN_RUNTIME, $runtimeSource, array(), false);
        }
    }

    /**
     * Returns configured Handlebars instance
     * @param boolean $runtime
     * @param array $extensions
     * @param bool|true $report_uncaught_exceptions
     * @return Handlebars
     */
    public static function create($runtime = false, $extensions = [], $report_uncaught_exceptions = true)
    {
        $extension = $runtime ? self::EXTN_RUNTIME : self::EXTN_HANDLEBARS;
        if (empty(V8Js::getExtensions()[$extension])) {
            throw new \InvalidArgumentException(sprintf("Extension '%s' not registered", $extension));
        }

        $extensions = array_merge($extensions, [$extension]);
        return new Handlebars($extensions, $report_uncaught_exceptions);
    }

    /**
     * Compiles a template so it can be executed immediately
     * @param string $template
     * @param array $options
     * @return callable
     */
    public function compile($template, $options = [])
    {
        if ($this->isRuntime) {
            throw new \BadMethodCallException("Cannot compile templates using runtime");
        }

        $this->v8->template = $template;
        $this->v8->options = $options ?: [];
        return $this->v8->executeString('Handlebars.compile(phpHb.template, phpHb.options)',
            __CLASS__ . '::' . __METHOD__ . '()'
        );
    }

    /**
     * Precompiles a given template so it can be sent to the client and executed without compilation
     * @param string $template
     * @param array $options
     * @return mixed
     */
    public function precompile($template, $options = [])
    {
        if ($this->isRuntime) {
            throw new \BadMethodCallException("Cannot precompile templates using runtime");
        }

        $this->v8->template = $template;
        $this->v8->options = $options;
        return $this->v8->executeString('Handlebars.precompile(phpHb.template, phpHb.options)',
            __CLASS__ . '::' . __METHOD__ . '()'
        );
    }

    /**
     * Sets up a template that was precompiled with self::precompile()
     * @param $templateSpec
     * @return callable
     */
    public function template($templateSpec)
    {
        return $this->v8->executeString('Handlebars.template(' . $templateSpec . ')',
            __CLASS__ . '::' . __METHOD__ . '()'
        );
    }

    /**
     * Registers partials accessible by any template in the environment
     * @param string $name
     * @param string $partial
     */
    public function registerPartial($name, $partial)
    {
        $this->registerScript('partial', $name, $partial);
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
     * @param string|callable $helper
     */
    public function registerHelper($name, $helper)
    {
        if (is_object($helper)) {
            $this->registerScript('helper', $name, $helper);
        }
        else {
            $this->registerJs('helper', $name, $helper);
        }
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

    /**
     * Sets logger
     * @param LoggerInterface $logger
     * @todo Do JS log levels match PHP ones, or do we need to map them?
     */
    public function setLogger(LoggerInterface $logger)
    {
        $this->v8->logger = $logger;
        $this->v8->executeString('Handlebars.log = phpHb.logger',
            __CLASS__ . '::' . __METHOD__ . '()'
        );
    }

    private function registerScript($type, $name, $script)
    {
        $method = 'register' . ucfirst($type);
        $this->v8->name = $name;
        $this->v8->script = $script;
        return $this->v8->executeString('Handlebars.' . $method . '(phpHb.name, phpHb.script)',
            __CLASS__ . '::' . __METHOD__ . '()'
        );
    }

    private function registerJs($type, $name, $javascript)
    {
        $method = 'register' . ucfirst($type);
        $this->v8->name = $name;
        return $this->v8->executeString('Handlebars.' . $method . '(phpHb.name, ' . $javascript . ')',
            __CLASS__ . '::' . __METHOD__ . '()'
        );
    }

    private function unregisterScript($type, $name)
    {
        $method = 'unregister' . ucfirst($type);
        $this->v8->name = $name;
        $this->v8->executeString('Handlebars.' . $method . '(phpHb.name)',
            __CLASS__ . '::' . __METHOD__ . '()'
        );
    }
}
