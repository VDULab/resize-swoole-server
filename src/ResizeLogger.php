<?php

namespace ResizeServer;

use Wa72\SimpleLogger\EchoLogger;

class ResizeLogger extends EchoLogger
{
    const TARGET_TRACE_LEVEL = 6;
    const MAX_DEBUG_LENGHT = 1000;
    /**
    * Interpolates context values into the message placeholders.
    *
    * @author PHP Framework Interoperability Group
    *
    * @param string $message
    * @param array $context
    * @return string
    */
    protected function interpolate($message, array $context)
    {
        if (!isset($context['class'])) {
            $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, self::TARGET_TRACE_LEVEL + 1);
            if (isset($trace[self::TARGET_TRACE_LEVEL])) {
                $context['class'] = $trace[self::TARGET_TRACE_LEVEL]['class'] . '::' . $trace[self::TARGET_TRACE_LEVEL]['function'];
            } else {
                $context['class'] = __CLASS__;
            }
        }

        if (false === strpos($message, '{')) {
            return '[' . $context['class'] . '] ' . $message;
        }
        
        $replacements = array();
        foreach ($context as $key => $val) {
            if (null === $val || is_scalar($val) || (\is_object($val) && method_exists($val, '__toString'))) {
                $replacements["{{$key}}"] = substr($val, 0, self::MAX_DEBUG_LENGHT);
            } elseif ($val instanceof \DateTimeInterface) {
                $replacements["{{$key}}"] = $val->format(\DateTime::RFC3339);
            } elseif (\is_object($val) || is_array($val)) {
                $replacements["{{$key}}"] = json_encode($val);
            } else {
                $replacements["{{$key}}"] = '['.\gettype($val).']';
            }
        }

        return strtr("[{class}] " . $message, $replacements);
    }
}
