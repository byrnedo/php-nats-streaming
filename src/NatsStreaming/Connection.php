<?php


namespace NatsStreaming;

use Nats\Message;
use Nats\Php71RandomGenerator;
use NatsStreaming\Contracts\ConnectionContract;
use pb\Ack;
use pb\CloseRequest;
use pb\CloseResponse;
use pb\ConnectRequest;
use pb\ConnectResponse;
use pb\PubMsg;
use RandomLib\Factory;

class Connection implements ConnectionContract
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

    //private $subPrefix;

    private $subRequests;

    private $unsubRequests;

    private $closeRequests;

    //private $ackSubject;


//    private $ackSubscription;
//    private $hbSubscription;

    //private $subscriptions = [];

    //private $pubAckMap = [];

    private $connected;

    private $reconnects = 0;
    private $pubs = 0;


    /**
     * @var \Nats\Connection
     */
    public $natsCon;

    private $subCloseRequests;

    private $timeout;

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

    private function msgIsEmpty($data) {
        return $data === "\r\n";
    }

    /**
     * @param null $timeout
     * @throws Exception
     */
    public function connect($timeout = null)
    {

        $this->natsCon->connect($timeout);

        $this->timeout      = $timeout;
        $hbInbox = $this->randomGenerator->generateString(self::UID_LENGTH);

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

        $this->natsCon->request($discoverSubject, $data, function ($message) use (&$resp) {
            $resp = ConnectResponse::fromStream($message->getBody());
        });


        if ($resp->getError()) {
            $this->close();
            throw Exception::forFailedConnection($resp->getError());
        }


        $this->pubPrefix = $resp->getPubPrefix();
        $this->subRequests = $resp->getSubRequests();
        $this->unsubRequests = $resp->getUnsubRequests();
        $this->subCloseRequests = $resp->getSubCloseRequests();
        $this->closeRequests = $resp->getCloseRequests();


        $this->connected = true;
        // what to do about this
        //$this->natsCon->subscribe($this->ackSubject, function($message) { $this->processAck($message);});
    }


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

        $ackSubject = self::DEFAULT_ACK_PREFIX . '.' . $this->randomGenerator->generateString(self::UID_LENGTH);

        $sid   = $this->natsCon->subscribe(
            $ackSubject,
            function ($message) {
                /**
                 * @var $message Message
                 */

                //$resp = Ack::fromStream($message->getBody());
            }
        );
        $this->natsCon->unsubscribe($sid, 1);
        $this->natsCon->publish($subj, $bytes, $ackSubject);
        $this->natsCon->wait(1);
        $this->pubs += 1;
    }

//    public function publishAsync($subject, $data, callable $ackHander)
//    {
//        // TODO: Implement publishAsync() method.
//    }

//    public function subscribe($subjects, callable $cb, $subscriptionOptions)
//    {
//        // TODO: Implement subscribe() method.
//    }
//
//    public function queueSubscribe($subjects, $qGroup, callable $cb, $subscriptionOptions)
//    {
//        // TODO: Implement queueSubscribe() method.
//    }


    public function reconnectsCount()
    {
        return $this->reconnects;
    }

    public function pubsCount()
    {
        return $this->pubs;
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

    public function isConnected()
    {

        if (!$this->natsCon->isConnected()) {
            $this->connected = false;
        }

        return $this->connected;
    }

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
        $this->natsCon->request($this->closeRequests, $reqBody, function ($message) use (&$resp) {
            /**
             * @var $message Message
             */

            if (!$this->msgIsEmpty($message->getBody())) {
                $resp = CloseResponse::fromStream($message->getBody());
            }
        });

        if ($resp && $resp->getError()) {
            throw Exception::forFailedDisconnection($resp->getError());
        }

        $this->natsCon->close();
    }

    public function natsConn()
    {
        return $this->natsCon;
    }

    public function __destruct()
    {
        if ($this->isConnected()) {
            $this->close();
        }
    }
}
