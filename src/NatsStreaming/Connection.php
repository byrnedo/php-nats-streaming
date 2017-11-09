<?php


namespace NatsStreaming;

use Nats\Message;
use Nats\Php71RandomGenerator;
use NatsStreaming\Contracts\ConnectionContract;
use pb\ConnectRequest;
use pb\ConnectResponse;
use pb\PubAck;
use RandomLib\Factory;

class Connection implements ConnectionContract {


    const INBOX_LENGTH = 16;
    const DEFAULT_ACK_PREFIX = '_STAN.acks';
    const DEFAULT_DISCOVER_PREFIX = '_STAN.discover';
    /**
     * @var ConnectionOptions
     */
    public $options;

    private $stanClusterID;

    private $clientID;

    /**
     * @var \RandomLib\Generator
     */
    private $randomGenerator;

    private $pubPrefix;

    private $subPrefix;

    private $subRequests;

    private $unsubRequests;

    private $closeRequests;

    private $ackSubject;


//    private $ackSubscription;
//    private $hbSubscription;

    //private $subscriptions = [];

    //private $pubAckMap = [];


    /**
     * @var \Nats\Connection
     */
    public $natsCon;
    private $subCloseRequests;

    public function publish($subject, $data)
    {
        // TODO: Implement publish() method.
    }

//    public function publishAsync($subject, $data, callable $ackHander)
//    {
//        // TODO: Implement publishAsync() method.
//    }

    public function subscribe($subjects, callable $cb, $subscriptionOptions)
    {
        // TODO: Implement subscribe() method.
    }

    public function queueSubscribe($subjects, $qGroup, callable $cb, $subscriptionOptions)
    {
        // TODO: Implement queueSubscribe() method.
    }

    public function disconnect()
    {
        // TODO: Implement disconnect() method.
    }

    public function natsConn()
    {
        return $this->natsCon;
    }

    public function __construct(ConnectionOptions $options = null)
    {

        if ($options === null) {
            $this->options = new ConnectionOptions();
        }

        if (version_compare(phpversion(), '7.0', '>') === true) {
            $this->randomGenerator = new Php71RandomGenerator();
        } else {
            $randomFactory         = new Factory();
            $this->randomGenerator = $randomFactory->getLowStrengthGenerator();
        }

        $this->natsCon = new \Nats\Connection($this->options);
    }


    /**
     * @param $message Message
     */
    private function processHeartbeat($message){
        $message->reply(null);
    }


    /**
     * @param $message Message
     */
    private function processAck($message){

        $req = PubAck::fromStream($message->getBody());
        //TODO - what?

    }

    public function connect($timeout = null){

        $this->natsCon->connect($timeout);


        $hbInbox = $this->randomGenerator->generateString(self::INBOX_LENGTH);

        $this->natsCon->subscribe($hbInbox, function($message) { $this->processHeartbeat($message);});

        $discoverSubject = $this->options->discoverPrefix . '.' . $this->stanClusterID;

        $req = new ConnectRequest();
        $req->setClientID($this->clientID);
        $req->setHeartbeatInbox($hbInbox);


        $data = $req->toStream()->getContents();
        /**
         * @var $resp ConnectResponse
         */
        $resp = null;
        $this->natsCon->request($discoverSubject, $data, $timeout, function($message) use (&$resp) {
            $resp = ConnectResponse::fromStream($message->getBody());
        });

        if ($resp->getError()) {
            // TODO throw execption
            $this->disconnect();
        }


        $this->pubPrefix = $resp->getPubPrefix();
        $this->subRequests = $resp->getSubRequests();
        $this->unsubRequests = $resp->getUnsubRequests();
        $this->subCloseRequests = $resp->getSubCloseRequests();
        $this->closeRequests = $resp->getCloseRequests();

        $this->ackSubject = self::DEFAULT_ACK_PREFIX . '.' . $this->randomGenerator->generateString(16);

        // what to do about this
        $this->natsCon->subscribe($this->ackSubject, function($message) { $this->processAck($message);});

    }
}
