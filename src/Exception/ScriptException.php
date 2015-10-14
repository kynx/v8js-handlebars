<?php
/**
 * @author: matt
 * @copyright: 2015 Claritum Limited
 * @license: Commercial
 */

namespace Kynx\V8js\Exception;


final class ScriptException extends \RuntimeException implements ExceptionInterface
{
    private $fileName;
    private $lineNumber;
    private $startColumn;
    private $endColumn;
    private $sourceLine;
    private $jsTrace;

    public function __construct($message = '', $code = '', \V8JsScriptException $e)
    {
        $this->fileName = $e->getJsFileName();
        $this->lineNumber = $e->getJsLineNumber();
        $this->startColumn = $e->getJsStartColumn();
        $this->endColumn = $e->getJsEndColumn();
        $this->sourceLine = $e->getJsSourceLine();
        $this->jsTrace = $e->getJsTrace();
        parent::__construct($message, $code, $e);
    }

    /**
     * @return string
     */
    final public function getJsFileName( )
    {
        return $this->fileName;
    }

    /**
     * @return int
     */
    final public function getJsLineNumber( )
    {
        return $this->lineNumber;
    }
    /**
     * @return int
     */
    final public function getJsStartColumn( )
    {
        return $this->startColumn;
    }
    /**
     * @return int
     */
    final public function getJsEndColumn( )
    {
        return $this->endColumn;
    }

    /**
     * @return string
     */
    final public function getJsSourceLine( )
    {
        return $this->sourceLine;
    }
    /**
     * @return string
     */
    final public function getJsTrace( )
    {
        return $this->jsTrace;
    }
}