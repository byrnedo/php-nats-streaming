<?php

namespace NatsStreaming;

use Exception;
use Nats\Message;
use NatsStreaming\Exceptions\TimeoutException;
use NatsStreaming\Exceptions\UnsubscribeException;
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

    private $messagesReceived = 0;
    private $messagesWitnessed = 0;

    /**
     * Subscription constructor.
     * @param $subject
     * @param $qGroup
     * @param $inbox
     * @param $opts
     * @param $cb
     * @param $stanCon
     */
    public function __construct($subject, $qGroup, $inbox, $opts, $cb, $stanCon)
    {
        $this->subject = $subject;
        $this->qGroup = $qGroup;
        $this->inbox = $inbox;
        $this->opts = $opts;
        $this->cb = function($message)use($cb){
            $this->messagesReceived ++;
            $cb($message);
        };
        $this->stanCon = $stanCon;
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
    public function unsubscribe(){

        $this->closeOrUnsubscribe(false);
    }

    /**
     * Close removes this subscriber from the server, but unlike Unsubscribe(),
     * the durable interest is not removed. If the client has connected to a server
     * for which this feature is not available, Close() will return a ErrNoServerSupport
     * error.
     */
    public function close() {

        $this->closeOrUnsubscribe(true);

    }

    /**
     * @param bool $doClose
     * @throws Exception
     * @throws TimeoutException
     * @throws UnsubscribeException
     */
    private function closeOrUnsubscribe($doClose) {

        $this->stanCon->natsConn()->unsubscribe($this->sid);

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

        $this->stanCon->doTrackedRequest($reqSubject, $req->toStream()->getContents(), function($message) use (&$resp) {
            /**
             * @var $message Message
             */
            $resp = SubscriptionResponse::fromStream($message->getBody());
        });

        if ($resp == null) {
            throw new TimeoutException();
        }

        if ($resp->getError()) {
            throw new UnsubscribeException($resp->getError());
        }
    }

    public function wait($messages = 1)
    {

        $initialWitnessed = $this->messagesWitnessed;
        while(true) {

            $countPreRead = $this->messagesReceived;
            if (($countPreRead - $initialWitnessed) >= $messages) {
                return;
            }

            if ($this->messagesWitnessed < $countPreRead) {
                $this->messagesWitnessed++;
                continue;
            }

            if (!$this->socketInGoodHealth()) {
                return;
            }
            $this->stanCon->natsCon->wait(1);

            $countPostRead = $this->messagesReceived;
            if ($countPostRead > $countPreRead) {
                // we got one
                $this->messagesWitnessed ++;
            }
        }
    }

    private function socketInGoodHealth(){
        return $this->stanCon->socketInGoodHealth();
    }

}
