<?php


namespace NatsStreaming;

use Nats\Message;
use Nats\Php71RandomGenerator;
use NatsStreaming\Contracts\ConnectionContract;
use pb\CloseRequest;
use pb\CloseResponse;
use pb\ConnectRequest;
use pb\ConnectResponse;
use pb\PubAck;
use pb\PubMsg;
use RandomLib\Factory;

class Connection implements ConnectionContract {


    const UID_LENGTH = 16;
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

    //private $subPrefix;

    private $subRequests;

    private $unsubRequests;

    private $closeRequests;

    //private $ackSubject;


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

        $subj = $this->pubPrefix . '.' . $subject;
        $peGUID = $this->randomGenerator->generateString(self::UID_LENGTH);

        $req = new PubMsg();
        $req->setClientID($this->clientID);
        $req->setGuid($peGUID);
        $req->setSubject($subject);
        $req->setData($data);

        $bytes = $req->toStream()->getContents();

        $ackSubject = self::DEFAULT_ACK_PREFIX . '.' . $this->randomGenerator->generateString(self::UID_LENGTH);

        $sid   = $this->natsCon->subscribe(
            $ackSubject,
            function($message){
                /**
                 * @var $message Message
                 */

               $resp = PubAck::fromStream($message->getBody());

               if ($resp->getError()) {
                   throw Exception::forFailedAck($resp->getError());
               }

            }
        );
        $this->natsCon->unsubscribe($sid, 1);
        $this->natsCon->publish($subj, $bytes, $ackSubject);
        $this->natsCon->wait(1);
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

    public function disconnect()
    {

        if (!$this->closeRequests || !$this->clientID) {
            return;
        }

        $req = new CloseRequest();

        $req->setClientID($this->clientID);

        /**
         * @var $resp CloseResponse
         */
        $resp = null;

        $this->natsCon->request($this->closeRequests, $req->toStream()->getContents(), function(&$message) use (&$resp) {
            /**
             * @var $message Message
             */
            $resp = CloseResponse::fromStream($message->getBody());
        });

        if ($resp->getError()) {
            throw Exception::forFailedDisconnection($resp->getError());
        }
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


    public function connect($timeout = null){

        $this->natsCon->connect($timeout);


        $hbInbox = $this->randomGenerator->generateString(self::UID_LENGTH);

        $this->natsCon->subscribe($hbInbox, function($message) { $this->processHeartbeat($message);});

        $discoverPrefix = $this->options->discoverPrefix ? $this->options->discoverPrefix : self::DEFAULT_DISCOVER_PREFIX;

        $discoverSubject = $discoverPrefix . '.' . $this->stanClusterID;

        $req = new ConnectRequest();
        $req->setClientID($this->clientID);
        $req->setHeartbeatInbox($hbInbox);

        $data = $req->toStream()->getContents();
        /**
         * @var $resp ConnectResponse
         */
        $resp = null;
        $this->natsCon->setStreamTimeout($timeout);
        $this->natsCon->request($discoverSubject, $data, function($message) use (&$resp) {
            $resp = ConnectResponse::fromStream($message->getBody());
        });

        if ($resp->getError()) {
            $this->disconnect();
            throw Exception::forFailedConnection($resp->getError());
        }


        $this->pubPrefix = $resp->getPubPrefix();
        $this->subRequests = $resp->getSubRequests();
        $this->unsubRequests = $resp->getUnsubRequests();
        $this->subCloseRequests = $resp->getSubCloseRequests();
        $this->closeRequests = $resp->getCloseRequests();


        // what to do about this
        //$this->natsCon->subscribe($this->ackSubject, function($message) { $this->processAck($message);});

    }
}
