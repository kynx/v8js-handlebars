# v8js-handlebars

A thin wrapper around the [Handlebars](http://handlebarsjs.com) javascript library, utilising the 
[V8Js](https://github.com/phpv8/v8js) extension to compile and render templates from PHP with Google's V8 javascript
engine.

## Features

* Pre-compile templates for fast rendering
* Feature complete: if it works in handlebarsjs, it works in PHP
* Use the same javascript helpers client-side as on the server
* Use existing PHP code for helpers server-side (if you have to)
* Faithfully mirrors the handlebars [API](http://handlebarsjs.com/reference.html): less stuff to learn

Use it as a renderer for your templating system or to compile templates as part of your asset pipeline.


## Quickstart

```php
Handlebars::registerHandlebarsExtension(file_get_contents('/path/to/handlebars.js'));

$handlebars = new Handlebars();
$template = $handlebars->compile('<h1>Hello {{ name }}!</h1>');
echo $template(['name' => 'world']);
```

Outputs `<h1>Hello world!</h1>`.


## Requirements

You must have the "ext-v8js" extension installed to use this library. This is available from the 
[pecl](http://pecl.php.net/package/v8js). Mac users should be able to install via [brew](http://brew.sh):

```
brew install php56-v8js
```

(Or at least they will once my [pull request](https://github.com/Homebrew/homebrew-php/pull/2466) is merged).

Installing v8js on other platforms is a little more complicated: none of the major Linux distros ship with a version of 
V8 > 3.24.6, which V8Js depends on. See the V8Js [README.Linux](https://github.com/phpv8/v8js/blob/master/README.Linux.md) 
or [README.Win32](https://github.com/phpv8/v8js/blob/master/README.Win32.md) for details.


## Installation

Install via [composer](http://getcomposer.org):

```
composer require "kynx/v8js-handlebars"
```

## Usage

Before the library can be used, register the handlebars source with V8Js. This *must* be done before you instantiate 
the handlebars class. The default composer install pulls in handlebars.js under `components/handlebars`:

```php
Handlebars::registerHandlebarsExtension(file_get_contents('components/handlebars/handlebars.js'));
```

This will register the full handlebars class which you will need to both compile and render templates. If all your 
templates and partials are pre-compiled, you can just load the runtime instead:

```php
Handlebars::registerHandlebarsExtension(file_get_contents('components/handlebars/handlebars.runtime.js'), true);
```

The `Handlebars` class contains (almost) all the API operations detailed in the Handlebars 
[reference](http://handlebarsjs.com/reference.html). The only difference is that you can use PHP arrays and objects as
arguments, as well as strings containing valid javascript.

The [test suite](tests/HandlebarsTest.php) is the best place to look for examples of usage, but below is a taster.

### compile($template, $options = [])

Compiling a template returns a callable object that can be used for immediate rendering:

```php
$handlebars = new Handlebars();
$template = $handlebars->compile('<h1>{{ name.first }} {{ name.last }}</h1>');
echo $template(['name' => ['first' => 'Slarty', 'last' => 'Bartfast']]);

// outputs "<h1>Slarty Bartfast</h1>"
```

See the handlebars API documentation for the `options` that can be passed.

### precompile($template, $options = [])

Pre-compiling a template does all the hard work. The returned javascript string can be used from both PHP on the server 
and by handlebars running in the client browser, so is a good candidate for caching. Once done, call the `template()` 
method to get a callable you can pass your data to: 

```php
$handlebars = new Handlebars();
$compiled = $handlebars->precompile('<h1>{{ name.first }} {{ name.last }}</h1>');

// cache template for later use...
```

### template($template, $options = [])
    
If you've got a compiled template, call `template()` to get a callable that will render it. While `compile()` and 
`precompile()` require the full handlebars source, this method (and the others below) can be used with just the runtime,
which you get by passing `true` as the first parameter to the constructor. The full handlebars will work as well.

```php
$handlebars = new Handlebars(true);
$template = $handlebars->template($compiled);
echo $template(['name' => ['first' => 'Slarty', 'last' => 'Bartfast']]);
```

### registerPartial($name, $partial = false)

Registering a single partial:

```php
$handlebars = new Handlebars();
$handlebars->registerPartial('my_partial', '<h1>{{ test }}</h1>');
```
     
Registering multiple partials at once, using a javascript string:

```php
$handlebars = new Handlebars();
$handlebars->registerPartial('{
    partial1 : "<h1>{{ test }}</h1>",
    partial2 : "<h2>{{ test }}</h2>"
}');
```
    
Doing the same thing with a PHP array:

```php
$handlebars = new Handlebars();
$handlebars->registerPartial([
    'partial1' => '<h1>{{ test }}</h1>',
    'partial2' => '<h2>{{ test }}</h2>'
]);
```

As with full templates, partials can be pre-compiled and cached. If you are using the runtime, _only_ pre-compiled 
partials will work:

```php
$handlebars = new Handlebars(true);
$handlebars->registerPartial('my_partial', $compiled);
```

### registerHelper($name, $helper = false)

[Helpers](http://handlebarsjs.com/block_helpers.html) can be either javascript functions, PHP callables or a PHP class 
containing callable methods. If you want to re-use them client-side, use javascript functions. 

Registering a javascript helper:

```php
$handlebars = new Handlebars();
$handlebars->registerHelper('bold', 'function(options) {
    return new Handlebars.SafeString(
        "<div class=\\"mybold\\">"
        + options.fn(this)
        + "</div>");
}');
```

The chances are you'll be keeping your javascript helpers in a separate file. So long as this contains a single object
something like `{ helper1 : function(...) {...}, helper2 : function (...) {...} }`, you can register them all at once:

```php
$handlebars = new Handlebars();
$handlebars->registerHelper(file_get_contents('/path/to/helpers.js'));
```

The signature for PHP helpers is slightly different from javascript ones:

```php
$handlebars = new Handlebars();
$handlebars->registerHelper('bold', function($self, $options) {
    return '<div class="mybold">'
        + $options->fn($self)
        + '</div>';
});
```

Note the `$self` variable passed as the first argument. With javascript helpers, `this` always contains the current 
context. In PHP it won't be available, so we wrap your helper and pass `this` as the first argument. Roll on PHP7's 
[Closure::call()](http://php.net/manual/en/closure.call.php) syntax :)

And yes, `$options->fn($self)` is calling a javascript function from PHP. Such is the magic of V8Js.
 
You can also pass an array of PHP callables or a class to `registerHelper()`.

### create($runtime = false, $extensions = [], $report_uncaught_exceptions = true)

In javascript Handlebars, this creates an isolated Handlebars environment with its own partials and helpers. 

There is an odd feature with V8Js extensions where they can actually persist between requests. This has some advantages: 
we don't have to reload the handlebars source with each request. But it does introduce a potential problem: things one 
script adds to the main Handlebars object might suddenly appear in another request.

To avoid polluting the main Handlebars instance, we always call `Handlebars.create()` when instantiating a new Handlebars 
class. The following are identical:

```php
$handlebars = new Handlebars();
$handlebars = Handlebars::create();
```

## Methods not part of the Handlebars API

### __construct($runtime = false, $extensions = [], $report_uncaught_exceptions = true)

The `$extensions` parameter allows you to specify other extensions registered via `V8Js::registerExtension()` that 
should be available to your javascript. `$report_uncaught_exceptions` specifies whether V8Js will throw javascript 
errors as PHP exceptions. We recommend leaving it at the default.
  
### setLogger(LoggerInterface $logger)
  
Enables you to set a [PSR-3 compatible](https://github.com/php-fig/fig-standards/blob/master/accepted/PSR-3-logger-interface.md) 
logger to capture the output of the `{{ log }}` built-in helper. For example:

```php
$logger = new Zend\Log\Logger;
$writer = new Zend\Log\Writer\Stream('php://output');
$logger->addWriter($writer);

$handlebars = new Handlebars();
$handlebars->setLogger($logger);
$template = $handlebars->compile('{{ log "Ouch!" level="debug" }}');
$template([], ['data' => ['level' => 'debug']]);

// Would output "Ouch!"
```

### Handlebars::isRegistered($runtime = false)

Static method that returns true if the handlebars source has been registered as a V8Js extension. Since the handlebars 
extension can persist between requests, you can use this to avoid the file-system hit and registration costs:

```php
if (!Handlebars::isRegistered()) {
    Handlebars::registerHandlebarsExtension(file_get_contents('components/handlebars/handlebars.js'));
}
$handlebars = new Handlebars();
```

### Handlebars::registerHandlebarsExtension($source, $runtime)

As demonstrated above, static method that registers the handlebars source as a V8Js extension. Pass `true` as the second 
argument if you are registering the runtime version.


## Handlebars API not implemented

The following exist in the Handlebars API, but haven't been implemented.

* `Handlebars.noConflict()` - this enables loading different versions of Handlebars into the global space. I can't see
  a use case for this in v8js-handlebars.
* `Handlebars.SafeString()`, `Handlebars.escapeExpression()` and `Handlebars.Util`. These are for use within helpers and 
  are available to any javascript helpers you write, but are not exposed in the PHP API.
* `Handlebars.log()` - see `setLogger()` above.


## Exceptions

This library does not declare any exceptions. It does some mild sanity checking when registering extensions, partials
and helpers and will throw the odd `InvalidArgumentException` if it doesn't like what it sees, or a 
`BadMethodCallException` if you're trying to do something with the runtime that requires compilation.

The exceptions you're most likely to want to handle gracefully will occur when you actually render your template. 
Calling `$template($data)` is executing a javascript function. Problems here will throw a 
[`V8JsScriptException`](http://www.php.net/manual/en/class.v8jsexception.php), which contains some information on what 
the engine choked on.

