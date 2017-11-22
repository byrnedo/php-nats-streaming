<?php

namespace NatsStreaming;

class MessageCache
{


    private static $msgsBySidMap = null;

    public static function popMessages($sid, $numMessages = 0)
    {
        if (!isset(self::$msgsBySidMap[$sid])) {
            return [];
        }
        $msgs = self::$msgsBySidMap[$sid];

        if ($numMessages <= 0) {
            self::$msgsBySidMap[$sid] = [];
            return $msgs;
        }

        self::$msgsBySidMap[$sid] = array_slice($msgs, $numMessages);
        return array_slice($msgs, 0, $numMessages);
    }


    public static function pushMessage($sid, $msg)
    {
        if (!is_array(self::$msgsBySidMap)) {
            self::$msgsBySidMap = [];
        }

        self::$msgsBySidMap[$sid][] = $msg;
    }
}
