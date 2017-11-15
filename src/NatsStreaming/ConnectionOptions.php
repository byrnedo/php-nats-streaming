<?php

namespace NatsStreaming;

use Traversable;

class ConnectionOptions
{

    use Fillable;
    //public $ackTimeout;

    /**
     * @var string
     */
    private $discoverPrefix = '';

    //public $maxPubAcksInflight;

    /**
     * @var \Nats\ConnectionOptions
     */
    private $natsOptions = null;


    /**
     * @var string
     */
    private $clusterID = "test-cluster";

    /**
     * @var string
     */
    private $clientID = '';

    /**
     * Allows to define parameters which can be set by passing them to the class constructor.
     *
     * @var array
     */
    private $fillable = [
        //'ackTimeout',
        'discoverPrefix',
        //'maxPubAcksInflight',
        'natsOptions',
        'clientID',
        'clusterID',
    ];



    /**
     * ConnectionOptions.php constructor.
     *
     * <code>
     * use NatsStreamingServer\ConnectionOptions.php;
     *
     * $options = new ConnectionOptions.php([
     *     'discoverPrefix' => 'myprefix.subj',
     *      // ...
     * ]);
     * </code>
     *
     * @param Traversable|array $options The connection options.
     */
    public function __construct($options = null)
    {
        if (empty($options) === false) {
            $this->initialize($options);
        }
    }


    /**
     * @param $discoverPrefix
     * @return $this
     */
    public function setDiscoverPrefix($discoverPrefix)
    {
        $this->discoverPrefix = $discoverPrefix;
        return $this;
    }

    public function getDiscoverPrefix()
    {
        return $this->discoverPrefix;
    }

    /**
     * @param \Nats\ConnectionOptions $natsOptions
     * @return ConnectionOptions
     */
    public function setNatsOptions($natsOptions)
    {
        $this->natsOptions = $natsOptions;
        return $this;
    }

    /**
     * @return \Nats\ConnectionOptions
     */
    public function getNatsOptions()
    {
        return $this->natsOptions;
    }

    /**
     * @param string $clusterID
     * @return ConnectionOptions
     */
    public function setClusterID($clusterID)
    {
        $this->clusterID = $clusterID;
        return $this;
    }

    /**
     * @return string
     */
    public function getClusterID()
    {
        return $this->clusterID;
    }

    /**
     * @param string $clientID
     * @return ConnectionOptions
     */
    public function setClientID($clientID)
    {
        $this->clientID = $clientID;
        return $this;
    }

    /**
     * @return string
     */
    public function getClientID()
    {
        return $this->clientID;
    }
}
