<?php
/**
 * @license MIT
 */

namespace KynxTest\V8js;

class HandlebarsHelper
{
    public function helper1($self, $context, $options)
    {
        $ret = '';
        for ($i=0; $i<count($context); $i++) {
            $ret .= '<h1>' . $options->fn($context[$i]) . '</h1>';
        }
        return $ret;
    }

    public function helper2($self, $context, $options)
    {
        $ret = '';
        for ($i=0; $i<count($context); $i++) {
            $ret .= '<h2>' . $options->fn($context[$i]) . '</h2>';
        }
        return $ret;
    }
}
