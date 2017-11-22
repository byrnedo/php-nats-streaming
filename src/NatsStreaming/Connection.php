<?php


namespace NatsStreaming;

use Exception;
use Nats\Message;
use Nats\Php71RandomGenerator;
use NatsStreaming\Exceptions\ConnectException;
use NatsStreaming\Exceptions\DisconnectException;
use NatsStreaming\Exceptions\SubscribeException;
use NatsStreaming\Exceptions\TimeoutException;
use NatsStreaming\Helpers\NatsHelper;
use NatsStreamingProtos\Ack;
use NatsStreamingProtos\CloseRequest;
use NatsStreamingProtos\CloseResponse;
use NatsStreamingProtos\ConnectRequest;
use NatsStreamingProtos\ConnectResponse;
use NatsStreamingProtos\PubMsg;
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

    /**
     * @var string
     */
    private $pubPrefix;

    /**
     * @var string
     */
    private $subRequests;

    /**
     * @var string
     */
    private $subCloseRequests;

    /**
     * @var string
     */
    private $unsubRequests;

    /**
     * @var string
     */
    private $closeRequests;

    /**
     * @var bool
     */
    private $connected = false;

    /**
     * @var int
     */
    private $reconnects = 0;

    /**
     * @var int
     */
    private $pubs = 0;


    /**
     * @var \Nats\Connection
     */
    public $natsCon;


    /**
     * @var int
     */
    private $timeout;


    /**
     * @var bool
     */
    private $waiting;


    /**
     * @var array
     */
    private $subs = [];

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

        $this->natsCon = new \Nats\Connection($this->options->getNatsOptions());
    }

    /**
     * Helper to check if message payload is empty
     * @param $data
     * @return bool
     */
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
        $hbInbox = NatsHelper::newInboxSubject();

        $this->natsCon->subscribe($hbInbox, function ($message) {
            $this->processHeartbeat($message);
        });

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

        $req = new TrackedNatsRequest($this, $discoverSubject, $data, function ($message) use (&$resp) {
            $resp = ConnectResponse::fromStream($message->getBody());
        });

        $req->wait();


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
     * @return TrackedNatsRequest
     */
    public function publish($subject, $data)
    {

        $natsSubject = $this->pubPrefix . '.' . $subject;
        $peGUID = $this->randomGenerator->generateString(self::UID_LENGTH);

        $req = new PubMsg();
        $req->setClientID($this->options->getClientID());
        $req->setGuid($peGUID);
        $req->setSubject($subject);
        $req->setData($data);

        $bytes = $req->toStream()->getContents();

        $ackSubject = NatsHelper::newInboxSubject(self::DEFAULT_ACK_PREFIX . '.');


        $natsReq = new TrackedNatsRequest($this, $natsSubject, $bytes, function ($message) {
            /**
             * @var $message Message
             */
            Ack::fromStream($message->getBody());
        }, $ackSubject);


        $this->pubs ++;

        return $natsReq;
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
        $inbox = NatsHelper::newInboxSubject();
        $sub = new Subscription(
            $subject,
            $qGroup,
            $inbox,
            $subscriptionOptions,
            $cb,
            $this
        );

        $this->subs[] = $sub;

        return $sub;
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
        $req = new TrackedNatsRequest($this, $this->closeRequests, $reqBody, function ($message) use (&$resp) {
            /**
             * @var $message Message
             */
            if (!$this->msgIsEmpty($message->getBody())) {
                $resp = CloseResponse::fromStream($message->getBody());
            }
        });
        $req->wait();

        if ($resp && $resp->getError()) {
            throw new DisconnectException($resp->getError());
        }

        $this->natsCon->close();
    }


    /**
     * Wait until timeout. Dispatches any pending messages first.
     */
    public function wait()
    {
        $this->waiting = true;
        foreach ($this->subs as $sub) {
            /**
             * @var $sub Subscription
             */
            $sub->dispatchCachedMessages();
        }
        $this->natsCon->wait();
        $this->waiting = false;
    }


    /**
     * Underlying nats connection
     *
     * @return \Nats\Connection
     */
    public function natsCon()
    {
        return $this->natsCon;
    }



    /**
     * @return string
     */
    public function getSubRequests()
    {
        return $this->subRequests;
    }

    /**
     * @return bool
     */
    public function isWaiting()
    {
        return $this->waiting;
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
}
