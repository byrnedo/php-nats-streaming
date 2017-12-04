<?php

namespace NatsStreaming;

use Nats\Message;
use NatsStreaming\Helpers\NatsHelper;

class TrackedNatsRequest
{
    private $sid;
    /**
     * @var Connection
     */
    private $stanCon;

    private $waiting = false;


    private $consumed = false;

    /**
     * @var callable
     */
    private $cb;

    private $receivedCount = 0;

    /**
     * TrackedNatsSub constructor.
     * @param $stanCon Connection
     * @param $subject
     * @param $data
     * @param $cb
     * @param null|string $replyInbox
     */
    public function __construct($stanCon, $subject, $data, $cb, $replyInbox = null)
    {

        if (! $replyInbox) {
            $replyInbox = NatsHelper::newInboxSubject();
        }
        $this->cb = $cb;
        $this->stanCon = $stanCon;
        $natsCon = $stanCon->natsCon();

        $this->sid = $natsCon->subscribe($replyInbox, function ($newMessage) use (&$resp, &$cb) {
            /**
             * @var $newMessage Message
             */
            $this->receivedCount ++;
            $consumeNow = $this->waiting || $this->stanCon->isWaiting();
            if ($consumeNow) {
                if ($cb != null) {
                    $cb($newMessage);
                    $this->consumed = true;
                }
            } else {
                MessageCache::pushMessage($newMessage->getSid(), $newMessage);
            }
        });
        $natsCon->unsubscribe($this->sid, 1);
        $natsCon->publish($subject, $data, $replyInbox);
    }


    private function dispatchCachedMessages()
    {

        $cb = $this->cb;
        $cachedMsgs = MessageCache::popMessages($this->getSid());
        if ($cachedMsgs) {
            foreach ($cachedMsgs as $msg) {
                $cb($msg);
                $this->consumed = true;
            }
            // should only get 1 so get out
            return true;
        }

        return false;
    }

    public function gotAck() {
        return $this->consumed > 0;
    }

    public function wait()
    {

        if ($this->consumed) {
            return $this->gotAck();
        }

        if ($this->dispatchCachedMessages()) {
            return $this->gotAck();
        } else {
            $this->waiting = true;

            $quota = $this->receivedCount + 1;
            while (NatsHelper::socketInGoodHealth($this->stanCon->natsCon()) && $this->waiting) {
                $this->stanCon->natsCon()->wait(1);
                if ($this->receivedCount  >= $quota) {
                    break;
                }
            }
        }

        $this->waiting = false;
        return $this->gotAck();
    }


    /**
     * @return mixed
     */
    public function getSid()
    {
        return $this->sid;
    }
}
