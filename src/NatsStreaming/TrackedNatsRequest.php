<?php

namespace NatsStreaming;


use Nats\Message;
use NatsStreaming\Helpers\NatsHelper;

class TrackedNatsRequest {
    private $sid;
    /**
     * @var \Nats\Connection
     */
    private $natsCon;

    private $active = false;

    /**
     * @var callable
     */
    private $cb;

    private $receivedCount = 0;

    /**
     * TrackedNatsSub constructor.
     * @param $natsCon \Nats\Connection
     * @param $subject
     * @param $data
     * @param $cb
     * @param null|string $replyInbox
     */
    public function __construct($natsCon, $subject, $data, $cb, $replyInbox = null)
    {

        if (! $replyInbox ) {
            $replyInbox = NatsHelper::newInboxSubject();
        }
        $this->cb = $cb;
        $this->natsCon = $natsCon;
        $this->sid = $natsCon->subscribe($replyInbox , function ($newMessage) use (&$resp, &$cb) {
            /**
             * @var $message Message
             */
            $this->receivedCount ++;
            if ($this->active) {
                if ($cb != null) {
                    $cb($newMessage);
                }
            } else {
                MessageCache::pushMessage($message->getSid(), $newMessage);
            }
        });
        $natsCon->unsubscribe($this->sid,1);
        $natsCon->publish($subject, $data, $replyInbox);
    }


    private function dispatchCachedMessages() {

        $cb = $this->cb;
        $cachedMsgs = MessageCache::popMessages($this->getSid());
        if ($cachedMsgs) {
            foreach($cachedMsgs as $msg) {
                $cb($msg);
            }
            // should only get 1 so get out
            return true;
        }

        return false;

    }


    public function wait(){

        if ($this->dispatchCachedMessages()) {
            return;
        } else {
            $this->active = true;

            $quota = $this->receivedCount + 1;
            while(NatsHelper::socketInGoodHealth($this->natsCon) && $this->active) {
                $this->natsCon->wait(1);
                if ($this->receivedCount  >= $quota ) {
                    break;
                }
            }
        }

        $this->active = false;
    }

    /**
     * @return mixed
     */
    public function getSid()
    {
        return $this->sid;
    }
}

