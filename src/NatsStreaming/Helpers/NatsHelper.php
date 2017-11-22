<?php


namespace NatsStreaming\Helpers;

use Nats\Connection;

class NatsHelper
{

    /**
     * @param string $prefix
     * @return string
     */
    public static function newInboxSubject($prefix = '_INBOX.')
    {
        return uniqid($prefix);
    }

    /**
     *
     * @param $natsCon Connection
     * @return bool
     */
    public static function socketInGoodHealth($natsCon)
    {

        $streamSocket = $natsCon->getStreamSocket();
        if (!$streamSocket) {
            return false;
        }
        $info = stream_get_meta_data($streamSocket);
        $ok = is_resource($streamSocket) === true && feof($streamSocket) === false && empty($info['timed_out']) === true;
        return $ok;
    }
}
