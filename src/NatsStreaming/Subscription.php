<?php

namespace NatsStreaming;

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

    /**
     * Subscription constructor.
     * @param $subject
     * @param $qGroup
     * @param $inbox
     * @param $ackInbox
     * @param $opts
     * @param $cb
     * @param $stanCon
     */
    public function __construct($subject, $qGroup, $inbox, $ackInbox, $opts, $cb, $stanCon)
    {
        $this->subject = $subject;
        $this->qGroup = $qGroup;
        $this->inbox = $inbox;
        $this->ackInbox = $ackInbox;
        $this->opts = $opts;
        $this->cb = $cb;
        $this->stanCon = $stanCon;
    }
    /**
     * @param mixed $subject
     * @return Subscription
     */
    public function setSubject($subject)
    {
        $this->subject = $subject;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getSubject()
    {
        return $this->subject;
    }

    /**
     * @param mixed $qGroup
     * @return Subscription
     */
    public function setQGroup($qGroup)
    {
        $this->qGroup = $qGroup;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getQGroup()
    {
        return $this->qGroup;
    }

    /**
     * @param mixed $inbox
     * @return Subscription
     */
    public function setInbox($inbox)
    {
        $this->inbox = $inbox;
        return $this;
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
     * @param SubscriptionOptions $opts
     * @return Subscription
     */
    public function setOpts($opts)
    {
        $this->opts = $opts;
        return $this;
    }

    /**
     * @return SubscriptionOptions
     */
    public function getOpts()
    {
        return $this->opts;
    }

    /**
     * @param callable $cb
     * @return Subscription
     */
    public function setCb(callable $cb)
    {
        $this->cb = $cb;
        return $this;
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

        $this->stanCon->natsConn()->request($reqSubject, $req->toStream()->getContents(), function($message) use (&$resp) {

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

    /**
     * @param Connection $stanCon
     * @return Subscription
     */
    public function setStanCon($stanCon)
    {
        $this->stanCon = $stanCon;
        return $this;
    }

    /**
     * @return Connection
     */
    public function getStanCon()
    {
        return $this->stanCon;
    }
}
