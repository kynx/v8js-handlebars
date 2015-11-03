<?php
/**
 * @license MIT
 * @copyright 2015 Matt Kynaston
 */

namespace Kynx\V8js;

use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerInterface;
use V8Js;

/**
 * Thin wrapper around handlebars.js
 * @link http://handlebarsjs.com/reference.html
 */
class Handlebars implements LoggerAwareInterface
{
    const EXTN_HANDLEBARS = 'handlebars';
    const EXTN_RUNTIME = 'handlebars-runtime';

    /**
     * @var V8Js
     */
    protected $v8;
    private $isRuntime;

    /**
     * @param bool $runtime      Set to true to use registered handlebars runtime
     * @param array $extensions  Array of extensions registered via V8Js::registerExtension() to make available
     * @param bool $report_uncaught_exceptions  You want this on
     */
    public function __construct($runtime = false, $extensions = [], $report_uncaught_exceptions = true)
    {
        $extension = $runtime ? self::EXTN_RUNTIME : self::EXTN_HANDLEBARS;
        if (!self::isRegistered($runtime)) {
            throw new \InvalidArgumentException(sprintf("Extension '%s' not registered", $extension));
        }

        $this->isRuntime = $runtime;
        $this->v8 = new V8Js('kynx', [], array_merge($extensions, [$extension]), $report_uncaught_exceptions);
        $this->v8->helpers = new \stdClass();
        $this->v8->decorators = new \stdClass();

        // function for calling PHP closures with a JS scope
        $this->v8->scopedCall = function($newthis, $closure, $arguments) {
            $cl = \Closure::bind($closure, $newthis);
            return call_user_func_array($cl, get_object_vars($arguments));
        };

        $this->v8->executeString('
            // Always work on private copy to avoid polluting extension (which may persist between requests)
            kynx.Handlebars = Handlebars.create();
            kynx.wrapCallables = function(obj) {
                var k, o = {};
                if (!obj) {
                    return obj;
                }
                if (obj.toString() == "[object Closure]") {
                    return function() {
                        return kynx.scopedCall(this, obj, arguments);
                    }
                }
                if (typeof obj == "object" && !obj.hasOwnProperty("length")) {
                    for (k in obj) {
                        if (obj.hasOwnProperty(k)) {
                            o[k] = kynx.wrapCallables(obj[k]);
                        }
                    }
                    return o;
                }
                return obj;
            }

            // Handlebars does a lot of checking against obj.toString() == "[object Object]", which does not work
            // with V8Js objects ("[object stdClass]" / "[object Array]"). This helper works around that.
            kynx.jsObject = function(obj) {
              if (typeof obj == "object") {
                var k, o = {};
                for (k in obj) {
                  if (obj.hasOwnProperty(k)) {
                    o[k] = obj[k];
                  }
                }
                return o;
              } else {
                return obj;
              }
            }
            ',
            __CLASS__ . '::' . __METHOD__
        );

    }

    /**
     * Returns true if the handlebars has been registered with V8Js
     * @param bool|false $runtime
     * @return bool
     */
    public static function isRegistered($runtime = false)
    {
        $extension = $runtime ? self::EXTN_RUNTIME : self::EXTN_HANDLEBARS;
        return isset(V8Js::getExtensions()[$extension]);
    }

    /**
     * Registers handlebars script with V8Js
     *
     * This *must* be called before the first call to self::create()
     * @param string $source
     * @param boolean $runtime
     */
    public static function registerHandlebarsExtension($source, $runtime = false)
    {
        if (!self::isRegistered($runtime)) {
            $extension = $runtime ? self::EXTN_RUNTIME : self::EXTN_HANDLEBARS;
            V8Js::registerExtension($extension, $source, [], false);
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
        return new Handlebars($runtime, $extensions, $report_uncaught_exceptions);
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
        return $this->v8->executeString(
            '(function(template, options) {
                var compiled = kynx.Handlebars.compile(template, options);
                return function(context, execOptions) {
                    // force zero-length arrays into objects
                    if (context && context.length === 0) {
                        context = {};
                    }
                    return compiled(kynx.wrapCallables(context), kynx.wrapCallables(execOptions));
                }
             })(kynx.template, kynx.options)',
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
        return $this->v8->executeString(
            'kynx.Handlebars.precompile(kynx.template, kynx.options)',
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
        return $this->v8->executeString(
            '(function(templateSpec) {
                var template = kynx.Handlebars.template(templateSpec);
                return function(context, execOptions) {
                    // force zero-length arrays into objects
                    if (context && context.length === 0) {
                        context = {};
                    }
                    return template(kynx.wrapCallables(context), kynx.wrapCallables(execOptions));
                }
             })(' . $templateSpec . ')',
            __CLASS__ . '::' . __METHOD__ . '()'
        );
    }

    /**
     * Registers partials accessible by any template in the environment
     * @param string|array|object $name
     * @param string|bool $partial
     */
    public function registerPartial($name, $partial = false)
    {
        $partials = [];
        if (is_array($name)) {
            $partials = $name;
        } elseif (is_object($name)) {
            $partials = get_object_vars($name);
        }

        if (count($partials)) {
            $this->registerPhpArray('partial', $partials);
        } elseif ($partial === false) {
            $this->registerJavascriptObject('partial', $name);
        } else {
            $this->register('partial', $name, $partial);
        }
    }

    /**
     * Unregisters a previously registered partial
     * @param string $name
     */
    public function unregisterPartial($name)
    {
        $this->unregister('partial', $name);
    }

    /**
     * Registers helpers accessible by any template in the environment
     * @param string|array|object $name
     * @param string|callable|bool $helper
     */
    public function registerHelper($name, $helper = false)
    {
        if (is_object($name)) {
            throw new \BadMethodCallException("Can't call " . __METHOD__ . " with object argument");
        }
        if (is_array($name)) {
            foreach ($name as $n => $helper) {
                $this->registerHelper($n, $helper);
            }
        } elseif ($helper === false) {
            $this->registerJavascriptObject('helper', $name);
        } elseif (is_callable($helper)) {
            $this->registerJavascriptFunction('helper', $name, $this->wrapHelperCallable($name, $helper));
        } else {
            $this->registerJavascriptFunction('helper', $name, $helper);
        }
    }

    /**
     * Unregisters a previously registered helper
     * @param string $name
     */
    public function unregisterHelper($name)
    {
        $this->unregister('helper', $name);
    }

    /**
     * Registers a decorator accessible by any template in the environment
     * @param string|object $name
     * @param string|callable|bool $decorator
     */
    public function registerDecorator($name, $decorator = false)
    {
        if (is_object($name)) {
            throw new \BadMethodCallException("Can't call " . __METHOD__ . " with object argument");
        }
        if (is_array($name)) {
            foreach ($name as $n => $decorator) {
                $this->registerDecorator($n, $decorator);
            }
        } elseif ($decorator === false) {
            $this->registerJavascriptObject('decorator', $name);
        } elseif (is_callable($decorator)) {
            $this->registerJavascriptFunction('decorator', $name, $this->wrapDecoratorCallable($name, $decorator));
        } else {
            $this->registerJavascriptFunction('decorator', $name, $decorator);
        }
    }

    /**
     * Unregisters a previously registered decorator
     * @param string $name
     */
    public function unregisterDecorator($name)
    {
        $this->unregister('decorator', $name);
    }

    /**
     * Sets logger
     * @param LoggerInterface $logger
     * @return null|void
     */
    public function setLogger(LoggerInterface $logger)
    {
        $this->v8->logger = $logger;
        $this->v8->executeString(
            'kynx.lookupLogLevel = function(level) {
               if (level == "warning") {
                 level = "warn";
               }
               return kynx.Handlebars.logger.lookupLevel(level);
             }
             kynx.lookupLogMethod = function(level) {
               var method = kynx.Handlebars.logger.methodMap[level];
               if (method == "warn") {
                 method = "warning";
               }
               return method || level;
             }
             kynx.Handlebars.log = function(level, message) {
               level = kynx.lookupLogLevel(level);
               var i, args = [];
               for (i=1; i<arguments.length; i++) {
                 kynx.logger.log(kynx.lookupLogMethod(level), arguments[i]);
               }
             }',
            __CLASS__ . '::' . __METHOD__ . '()'
        );
    }

    private function wrapHelperCallable($name, $helper)
    {
        $this->v8->helpers->{$name} = $helper;
        return "(kynx.wrapCallables(kynx.helpers['$name']))";
    }

    private function wrapDecoratorCallable($name, $decorator)
    {
        $this->v8->decorators->{$name} = $decorator;
        return "(kynx.wrapCallables(kynx.decorators['$name'])";

    }

    private function register($type, $name, $script = false)
    {
        $method = 'register' . ucfirst($type);
        $this->v8->name = $name;
        $this->v8->script = $script;
        return $this->v8->executeString(
            'kynx.Handlebars.' . $method . '(kynx.name, kynx.script)',
            __CLASS__ . '::' . __METHOD__ . '()'
        );
    }

    private function registerPhpArray($type, $array)
    {
        $method = 'register' . ucfirst($type);
        $this->v8->script = $array;
        return $this->v8->executeString(
            'kynx.Handlebars.' . $method . '(kynx.jsObject(kynx.script))',
            __CLASS__ . '::' . __METHOD__ . '()'
        );

    }

    private function registerJavascriptObject($type, $javascript)
    {
        $method = 'register' . ucfirst($type);
        return $this->v8->executeString(
            'kynx.Handlebars.' . $method . '(' . $javascript . ')',
            __CLASS__ . '::' . __METHOD__ . '()'
        );
    }

    private function registerJavascriptFunction($type, $name, $javascript = false)
    {
        $method = 'register' . ucfirst($type);
        $script = $this->v8->executeString(
            '(' . $javascript . ')',
            __CLASS__ . '::' . __METHOD__ . '()'
        );
        return $this->register($type, $name, $script);
    }

    private function unregister($type, $name)
    {
        $method = 'unregister' . ucfirst($type);
        $this->v8->name = $name;
        $this->v8->executeString(
            'kynx.Handlebars.' . $method . '(kynx.name)',
            __CLASS__ . '::' . __METHOD__ . '()'
        );
    }
}
