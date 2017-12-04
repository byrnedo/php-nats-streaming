<?php

namespace NatsStreaming;

use Exception;
use Nats\Message;
use NatsStreaming\Exceptions\SubscribeException;
use NatsStreaming\Exceptions\TimeoutException;
use NatsStreaming\Exceptions\UnsubscribeException;
use NatsStreaming\Helpers\NatsHelper;
use NatsStreaming\Helpers\TimeHelpers;
use NatsStreamingProtos\StartPosition;
use NatsStreamingProtos\SubscriptionRequest;
use NatsStreamingProtos\SubscriptionResponse;
use NatsStreamingProtos\UnsubscribeRequest;

class Subscription
{

    /**
     * @var string
     */
    private $subject = '';
    /**
     * @var string
     */
    private $qGroup = '';
    /**
     * @var string
     */
    private $inbox = '';
    /**
     * @var string
     */
    private $ackInbox = '';
    /**
     * @var SubscriptionOptions
     */
    private $opts = null;
    private $cb = null;

    /**
     * @var string
     */
    private $sid = '';

    /**
     * @var Connection
     */
    private $stanCon = null;

    private $active = false;

    private $processedMessages = 0;

    /**
     * Subscription constructor.
     * @param $subject
     * @param $qGroup
     * @param $inbox
     * @param $opts
     * @param $msgCb
     * @param $stanCon
     * @throws Exception
     * @throws SubscribeException
     * @throws TimeoutException
     */
    public function __construct($subject, $qGroup, $inbox, $opts, $msgCb, $stanCon)
    {
        $this->subject = $subject;
        $this->qGroup = $qGroup;
        $this->inbox = $inbox;
        $this->opts = $opts;
        $this->cb = function ($message) use ($msgCb) {
            $this->processedMessages ++;
            $msgCb($message);
        };

        $this->stanCon = $stanCon;

        $this->sid = $this->stanCon->natsCon()->subscribe($this->getInbox(), function ($message) {
            $this->processMsg($message);
        });

        $subRequest = new SubscriptionRequest();
        $subRequest->setSubject($this->getSubject());
        $subRequest->setQGroup($this->qGroup);
        $subRequest->setClientID($this->stanCon->options->getClientID());
        $subRequest->setAckWaitInSecs($this->opts->getAckWaitSecs());
        $subRequest->setMaxInFlight($this->opts->getMaxInFlight());
        $subRequest->setDurableName($this->opts->getDurableName());
        $subRequest->setInbox($this->getInbox());
        $subRequest->setStartPosition($this->opts->getStartAt());

        switch ($subRequest->getStartPosition()->value()) {
            case StartPosition::SequenceStart_VALUE:
                $subRequest->setStartSequence($this->opts->getStartSequence());
                break;
            case StartPosition::TimeDeltaStart_VALUE:
                $nowNano = TimeHelpers::unixTimeNanos();
                $subRequest->setStartTimeDelta($nowNano - $this->opts->getStartMicroTime() * 1000);
                break;
        }

        $data = $subRequest->toStream()->getContents();

        /**
         * @var $resp SubscriptionResponse
         */
        $resp = null;
        try {
            $natsReq = new TrackedNatsRequest($this->stanCon, $this->stanCon->getSubRequests(), $data, function ($message) use (&$resp) {
                $resp = SubscriptionResponse::fromStream($message->getBody());
            });

            $natsReq->wait();
        } catch (\Exception $e) {
            $this->stanCon->natsCon()->unsubscribe($this->getSid());
            throw $e;
        }

        if (!$resp) {
            $this->stanCon->natsCon()->unsubscribe($this->getSid());
            throw new TimeoutException('no response for subscribe request');
        }

        if ($resp->getError()) {
            $this->stanCon->natsCon()->unsubscribe($this->getSid());
            throw new SubscribeException($resp->getError());
        }


        $this->setAckInbox($resp->getAckInbox());
    }


    /**
     * @return mixed
     */
    public function getSubject()
    {
        return $this->subject;
    }


    /**
     * @return mixed
     */
    public function getQGroup()
    {
        return $this->qGroup;
    }


    /**
     * @return mixed
     */
    public function getInbox()
    {
        return $this->inbox;
    }

    /**
     * @param mixed $ackInbox
     * @return Subscription
     */
    public function setAckInbox($ackInbox)
    {
        $this->ackInbox = $ackInbox;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getAckInbox()
    {
        return $this->ackInbox;
    }


    /**
     * @return SubscriptionOptions
     */
    public function getOpts()
    {
        return $this->opts;
    }


    /**
     * @return null|callable
     */
    public function getCb()
    {
        return $this->cb;
    }


    /**
     * @param string $sid
     * @return Subscription
     */
    public function setSid($sid)
    {
        $this->sid = $sid;
        return $this;
    }

    /**
     * @return string
     */
    public function getSid()
    {
        return $this->sid;
    }

    /**
     * @return Connection
     */
    public function getStanCon()
    {
        return $this->stanCon;
    }


    /**
     * Unsubscribe removes interest in the subscription.
     * For durables, it means that the durable interest is also removed from
     * the server. Restarting a durable with the same name will not resume
     * the subscription, it will be considered a new one.
     */
    public function unsubscribe()
    {

        $this->closeOrUnsubscribe(false);
    }

    /**
     * Close removes this subscriber from the server, but unlike Unsubscribe(),
     * the durable interest is not removed. If the client has connected to a server
     * for which this feature is not available, Close() will return a ErrNoServerSupport
     * error.
     */
    public function close()
    {

        $this->closeOrUnsubscribe(true);
    }

    /**
     * @param bool $doClose
     * @throws Exception
     * @throws TimeoutException
     * @throws UnsubscribeException
     */
    private function closeOrUnsubscribe($doClose)
    {

        $this->stanCon->natsCon()->unsubscribe($this->sid);

        $req = new UnsubscribeRequest();
        $req->setClientID($this->stanCon->options->getClientID());
        $req->setSubject($this->subject);
        $req->setInbox($this->ackInbox);


        $reqSubject = $this->stanCon->getUnsubRequests();

        if ($doClose) {
            $reqSubject = $this->stanCon->getSubCloseRequests();
            if (!$reqSubject) {
                throw new UnsubscribeException("not supported by server");
            }
        }

        /**
         * @var $resp SubscriptionResponse
         */
        $resp = null;

        $natsReq = new TrackedNatsRequest($this->stanCon, $reqSubject, $req->toStream()->getContents(), function ($message) use (&$resp) {
            /**
             * @var $message Message
             */
            $resp = SubscriptionResponse::fromStream($message->getBody());
        });

        $natsReq->wait();

        if ($resp == null) {
            throw new TimeoutException();
        }

        if ($resp->getError()) {
            throw new UnsubscribeException($resp->getError());
        }
    }

    /**
     * Callback which handles every MsgProto and finds the related callback for that message
     *
     * @param $rawMessage Message
     */
    private function processMsg($rawMessage)
    {

        $newMessage = Msg::fromStream($rawMessage->getBody());

        // smuggle ack subject
        $newMessage->setSub($this);

        $consumeNow = $this->active || $this->stanCon->isWaiting();

        if ($consumeNow) {
            $cb = $this->getCb();
            $cb($newMessage);
        } else {
            MessageCache::pushMessage($this->getSid(), $newMessage);
        }

        // ack it anyway
        $isManualAck = $this->getOpts()->isManualAck();
        if (!$isManualAck) {
            $newMessage->ack();
        }
    }

    public function dispatchCachedMessages($messages = 0)
    {

        $cachedMsgs = MessageCache::popMessages($this->getSid(), $messages);
        $msgsDone = 0;

        if ($cachedMsgs) {
            $cb = $this->getCb();
            foreach ($cachedMsgs as $msg) {
                $cb($msg);
                $msgsDone ++;
            }
        }

        return $msgsDone;
    }

    public function wait($messages = 1)
    {


        $msgsDone = $this->dispatchCachedMessages($messages);


        $this->active = true;

        if ($msgsDone < $messages) {
            $messagesLeft = $messages - $msgsDone;

            $quota = $this->processedMessages + $messagesLeft;

            while (NatsHelper::socketInGoodHealth($this->stanCon->natsCon()) && $this->active) {
                $this->stanCon->natsCon()->wait(1);

                if ($this->processedMessages >= $quota) {
                    break;
                }
            }
        }

        $this->active = false;
    }
}
