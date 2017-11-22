<?php


namespace NatsStreaming\Helpers;

class TimeHelpers
{

    /**
     * Returns unix time in nanoseconds
     * could use `date +%S` instead, need to benchmark
     * @return int
     */
    public static function unixTimeNanos()
    {
        list ($micro, $secs) = explode(" ", microtime());
        $nanosOffset = $micro * 1000000000;
        $totalNanos = $secs * 1000000000 + $nanosOffset;
        return (int) $totalNanos;
    }
}
