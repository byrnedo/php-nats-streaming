<?php


namespace NatsStreaming;

use Exception;
use Nats\Message;
use Nats\Php71RandomGenerator;
use NatsStreaming\Contracts\ConnectionContract;
use NatsStreaming\Exceptions\ConnectException;
use NatsStreaming\Exceptions\DisconnectException;
use NatsStreaming\Exceptions\PublishException;
use NatsStreaming\Exceptions\SubscribeException;
use NatsStreaming\Exceptions\TimeoutException;
use NatsStreamingProtos\Ack;
use NatsStreamingProtos\CloseRequest;
use NatsStreamingProtos\CloseResponse;
use NatsStreamingProtos\ConnectRequest;
use NatsStreamingProtos\ConnectResponse;
use NatsStreamingProtos\PubMsg;
use NatsStreamingProtos\StartPosition;
use NatsStreamingProtos\SubscriptionRequest;
use NatsStreamingProtos\SubscriptionResponse;
use RandomLib\Factory;

class Connection
{


    const UID_LENGTH = 16;
    const DEFAULT_ACK_PREFIX = '_STAN.acks';
    const DEFAULT_DISCOVER_PREFIX = '_STAN.discover';
    /**
     * @var ConnectionOptions
     */
    public $options;

    /**
     * @var \RandomLib\Generator
     */
    private $randomGenerator;

    private $pubPrefix;


    private $subRequests;

    private $unsubRequests;

    private $closeRequests;

    private $subCbMap = [];

    private $connected;

    private $reconnects = 0;
    private $pubs = 0;


    /**
     * @var \Nats\Connection
     */
    public $natsCon;

    private $subCloseRequests;

    private $timeout;

    /**
     * Connection constructor.
     * @param ConnectionOptions|null $options
     */
    public function __construct(ConnectionOptions $options = null)
    {

        if ($options === null) {
            $options = new ConnectionOptions();
        }

        $this->options  = $options;

        if (version_compare(phpversion(), '7.0', '>') === true) {
            $this->randomGenerator = new Php71RandomGenerator();
        } else {
            $randomFactory         = new Factory();
            $this->randomGenerator = $randomFactory->getLowStrengthGenerator();
        }

        $this->witness = new MessageCounter();
        $this->natsCon = new \Nats\Connection($this->options->getNatsOptions());
    }

    private function msgIsEmpty($data)
    {
        return $data === "\r\n";
    }

    /**
     * Connect
     *
     * @param null $timeout
     * @throws ConnectException
     * @throws TimeoutException
     */
    public function connect($timeout = null)
    {

        $this->natsCon->connect($timeout);

        $this->timeout      = $timeout;
        $hbInbox = uniqid('_INBOX.');

        //$this->natsCon->subscribe($hbInbox, function($message) { $this->processHeartbeat($message);});

        $discoverPrefix = $this->options->getDiscoverPrefix() ? $this->options->getDiscoverPrefix() : self::DEFAULT_DISCOVER_PREFIX;

        $discoverSubject = $discoverPrefix . '.' . $this->options->getClusterID();

        $req = new ConnectRequest();
        $req->setClientID($this->options->getClientID());
        $req->setHeartbeatInbox($hbInbox);

        $data = $req->toStream()->getContents();
        /**
         * @var $resp ConnectResponse
         */
        $resp = null;

        $this->doTrackedRequest($discoverSubject, $data, function ($message) use (&$resp) {
            $resp = ConnectResponse::fromStream($message->getBody());
        });


        if (!$resp) {
            throw new TimeoutException();
        }


        if ($resp->getError()) {
            $this->close();
            throw new ConnectException($resp->getError());
        }


        $this->pubPrefix = $resp->getPubPrefix();
        $this->subRequests = $resp->getSubRequests();
        $this->unsubRequests = $resp->getUnsubRequests();
        $this->subCloseRequests = $resp->getSubCloseRequests();
        $this->closeRequests = $resp->getCloseRequests();


        $this->connected = true;
    }


    /**
     * Publish message
     *
     * @param $subject
     * @param $data
     * @return TrackedNatsSub
     */
    public function publish($subject, $data)
    {

        $subj = $this->pubPrefix . '.' . $subject;
        $peGUID = $this->randomGenerator->generateString(self::UID_LENGTH);

        $req = new PubMsg();
        $req->setClientID($this->options->getClientID());
        $req->setGuid($peGUID);
        $req->setSubject($subject);
        $req->setData($data);

        $bytes = $req->toStream()->getContents();

        $ackSubject = uniqid(self::DEFAULT_ACK_PREFIX . '.');

        $sid = $this->natsCon->subscribe(
            $ackSubject,
            function ($message) use (&$acked) {
                /**
                 * @var $message Message
                 */
                $this->witness->incSubMsgsReceived($message->getSid());
                Ack::fromStream($message->getBody());
            }
        );
        $this->natsCon->unsubscribe($sid, 1);
        $this->natsCon->publish($subj, $bytes, $ackSubject);

        $this->pubs += 1;

        return new TrackedNatsSub($sid, $this);

    }

    /**
     * Subscribe
     *
     * @param $subjects
     * @param callable $cb
     * @param SubscriptionOptions $subscriptionOptions
     * @return Subscription
     */
    public function subscribe($subjects, callable $cb, $subscriptionOptions)
    {
        return $this->_subscribe($subjects, '', $cb, $subscriptionOptions);
    }

    /**
     * Subscribe to queue group
     * @param $subjects
     * @param $qGroup
     * @param callable $cb
     * @param SubscriptionOptions $subscriptionOptions
     * @return Subscription
     */
    public function queueSubscribe($subjects, $qGroup, callable $cb, $subscriptionOptions)
    {
        return $this->_subscribe($subjects, $qGroup, $cb, $subscriptionOptions);
    }

    /**
     * Returns unix time in nanoseconds
     * could use `date +%S` instead, need to benchmark
     * @return int
     */
    public static function unixTimeNanos(){
        list ($micro, $secs) = explode(" ", microtime());
        $nanosOffset = $micro * 1000000000;
        $totalNanos = $secs * 1000000000 + $nanosOffset;
        return (int) $totalNanos;
    }


    /**
     *
     * @param $subject
     * @param $qGroup
     * @param callable $cb
     * @param SubscriptionOptions $subscriptionOptions
     * @return Subscription
     * @throws SubscribeException
     * @throws TimeoutException
     * @throws \Exception
     */
    private function _subscribe($subject, $qGroup, callable $cb, $subscriptionOptions)
    {
        $inbox = uniqid('_INBOX.');
        $sub = new Subscription(
            $subject,
            $qGroup,
            $inbox,
            $subscriptionOptions,
            $cb,
            $this
        );
        $this->subCbMap[$sub->getInbox()] = $sub;
        $sid = $this->natsCon->subscribe($sub->getInbox(), function ($message) {
            $this->processMsg($message);
        });

        $sub->setSid($sid);
        $this->subCbMap[$sub->getInbox()] = $sub;


        $req = new SubscriptionRequest();
        $req->setSubject($sub->getSubject());
        $req->setClientID($this->options->getClientID());
        $req->setAckWaitInSecs($subscriptionOptions->getAckWaitSecs());
        $req->setMaxInFlight($subscriptionOptions->getMaxInFlight());
        $req->setDurableName($subscriptionOptions->getDurableName());
        $req->setInbox($sub->getInbox());
        $req->setStartPosition($subscriptionOptions->getStartAt());

        switch ($req->getStartPosition()->value()) {
            case StartPosition::SequenceStart_VALUE:
                $req->setStartSequence($subscriptionOptions->getStartSequence());
                break;
            case StartPosition::TimeDeltaStart_VALUE:

                $nowNano = self::unixTimeNanos();
                $req->setStartTimeDelta($nowNano - $subscriptionOptions->getStartMicroTime() * 1000);
                break;
        }

        $data = $req->toStream()->getContents();
        /**
         * @var $resp SubscriptionResponse
         */
        $resp = null;
        try {
            $this->doTrackedRequest($this->subRequests, $data, function ($message) use (&$resp) {
                $resp = SubscriptionResponse::fromStream($message->getBody());
            });

        } catch (\Exception $e) {
            $this->natsCon->unsubscribe($sid);
            throw $e;
        }

        if (!$resp) {
            $this->natsCon->unsubscribe($sid);
            throw new TimeoutException('no response for subscribe request');
        }

        if ($resp->getError()) {
            $this->natsCon->unsubscribe($sid);
            throw new SubscribeException($resp->getError());
        }


        $sub->setAckInbox($resp->getAckInbox());
        return $sub;
    }

    /**
     * Callback which handles every MsgProto and finds the related callback for that message
     *
     * @param $rawMessage Message
     */
    private function processMsg($rawMessage)
    {

        $message = Msg::fromStream($rawMessage->getBody());

        /**
         * @var $sub Subscription
         */
        $sub = @$this->subCbMap[$rawMessage->getSubject()];

        if ($sub == null) {
            return;
        }

        // smuggle ack subject
        $message->setSub($sub);

        $isManualAck = $sub->getOpts()->isManualAck();
        $cb = $sub->getCb();
        // TODO - should we ack if no cb?
        if ($cb != null) {
            $cb($message);
        }

        if (!$isManualAck) {
            $message->ack();
        }
    }

    /**
     * Number of reconnects
     * @return int
     */
    public function reconnectsCount()
    {
        return $this->reconnects;
    }

    /**
     * Number of publish requests
     * @return int
     */
    public function pubsCount()
    {
        return $this->pubs;
    }

    /**
     * @return mixed
     */
    public function getUnsubRequests()
    {
        return $this->unsubRequests;
    }

    /**
     * @return mixed
     */
    public function getSubCloseRequests()
    {
        return $this->subCloseRequests;
    }


    /**
     * @param $message Message
     */
    private function processHeartbeat($message)
    {
        $message->reply(null);
    }



    /**
     * Reconnects to the server.
     *
     * @return void
     */
    public function reconnect()
    {
        $this->reconnects += 1;
        $this->close();
        $this->connect($this->timeout);
    }

    /**
     * Is client connected
     *
     * @return bool
     */
    public function isConnected()
    {

        if (!$this->natsCon->isConnected()) {
            $this->connected = false;
        }

        return $this->connected;
    }

    /**
     * Close connection
     *
     * @throws Exception
     */
    public function close()
    {

        if (!$this->closeRequests || !$this->options->getClientID() || !$this->connected) {
            $this->connected = false;
            return;
        }
        $this->connected = false;

        $req = new CloseRequest();
        $req->setClientID($this->options->getClientID());
        /**
         * @var $resp CloseResponse
         */
        $resp = null;
        $reqBody  = $req->toStream()->getContents();
        $this->doTrackedRequest($this->closeRequests, $reqBody, function ($message) use (&$resp) {
            /**
             * @var $message Message
             */
            if (!$this->msgIsEmpty($message->getBody())) {
                $resp = CloseResponse::fromStream($message->getBody());
            }
        });


        if ($resp && $resp->getError()) {
            throw new DisconnectException($resp->getError());
        }

        $this->natsCon->close();
    }

    /**
     * Underlying nats connection
     *
     * @return \Nats\Connection
     */
    public function natsConn()
    {
        return $this->natsCon;
    }

    /**
     *
     * Disconnect if we haven't
     */
    public function __destruct()
    {
        if ($this->isConnected()) {
            $this->close();
        }
    }

    /**
     *
     * Do a plain nats request and wait for it's response in particular, not just any 1 message
     *
     * @param $subject
     * @param $data
     * @param callable $cb
     */
    public function doTrackedRequest($subject, $data, callable $cb){
        $replyInbox = uniqid('_INBOX');
        $sid = $this->natsCon->subscribe($replyInbox , function ($message) use (&$resp, &$cb) {
            /**
             * @var $message Message
             */
            $this->witness->incSubMsgsReceived($message->getSid());
            $cb($message);
        });
        $this->natsCon->unsubscribe($sid,1);
        $this->natsCon->publish($subject, $data, $replyInbox);
        $sub = new TrackedNatsSub($sid, $this);
        $sub->wait();
    }

    /**
     * @return bool
     */
    public function socketInGoodHealth(){

        $streamSocket = $this->natsCon->getStreamSocket();
        if (!$streamSocket) {
            return false;
        }
        $info = stream_get_meta_data($streamSocket);
        $ok = is_resource($streamSocket) === true && feof($streamSocket) === false && empty($info['timed_out']) === true;
        return $ok;

    }

}
