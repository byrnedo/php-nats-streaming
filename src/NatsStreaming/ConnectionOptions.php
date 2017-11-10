<?php

namespace NatsStreaming;

use Traversable;

class ConnectionOptions {

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
    private $configurable = [
        //'ackTimeout',
        'discoverPrefix',
        //'maxPubAcksInflight',
        'natsOptions',
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
     * Initialize the parameters.
     *
     * @param Traversable|array $options The connection options.
     *
     * @throws Exception When $options are an invalid type.
     *
     * @return void
     */
    protected function initialize($options)
    {
        if (is_array($options) === false && ($options instanceof Traversable) === false) {
            throw new Exception('The $options argument must be either an array or Traversable');
        }

        foreach ($options as $key => $value) {
            if (in_array($key, $this->configurable, true) === false) {
                continue;
            }

            $method = 'set'.ucfirst($key);

            if (method_exists($this, $method) === true) {
                $this->$method($value);
            }
        }
    }

    /**
     * @param $discoverPrefix
     * @return $this
     */
    public function setDiscoverPrefix($discoverPrefix){
        $this->discoverPrefix = $discoverPrefix;
        return $this;
    }

    public function getDiscoverPrefix(){
        return $this->discoverPrefix;
    }

    /**
     * @param \Nats\ConnectionOptions $natsOptions
     * @return ConnectionOptions
     */
    public function setNatsOptions($natsOptions)
    {
        $this->natsOptions = new \Nats\ConnectionOptions($natsOptions);
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
