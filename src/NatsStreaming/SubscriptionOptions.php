<?php

namespace NatsStreaming;

use DateTime;
use NatsStreamingProtos\StartPosition;

class SubscriptionOptions
{
    use Fillable;

    /**
     * @var string
     */
    private $durableName = '';

    /**
     * @var int
     */
    private $maxInFlight = 1024;

    /**
    * 30seconds
     * @var int
     */
    private $ackWaitSecs = 30;

    /**
     * @var StartPosition
     */
    private $startAt = null;

    /**
     * @var int
     */
    private $startSequence = 0;

    /**
     * @var int
     */
    private $startMicroTime = 0;

    /**
     * @var bool
     */
    private $manualAck = false;

    private $fillable = [
       'durableName',
       'maxInFlight',
       'ackWaitSecs',
       'startAt',
       'startSequence',
       'startMicroTime',
       'manualAck',
    ];

    public function __construct($options = null)
    {
        if (empty($options) === false) {
            $this->initialize($options);
        }

        if ($this->getStartAt() == null) {
            $this->setStartAt(StartPosition::NewOnly());
        }
    }

    /**
     * @param bool $manualAck
     * @return SubscriptionOptions
     */
    public function setManualAck($manualAck)
    {
        $this->manualAck = $manualAck;
        return $this;
    }

    /**
     * @return bool
     */
    public function isManualAck()
    {
        return $this->manualAck;
    }

    /**
     * @return string
     */
    public function getDurableName()
    {
        return $this->durableName;
    }

    /**
     * @param string $durableName
     * @return $this
     */
    public function setDurableName($durableName)
    {
        $this->durableName = $durableName;
        return $this;
    }

    /**
     * @return int
     */
    public function getMaxInFlight()
    {
        return $this->maxInFlight;
    }

    /**
     * @param int $maxInFlight
     * @return $this
     */
    public function setMaxInFlight($maxInFlight)
    {
        $this->maxInFlight = $maxInFlight;
        return $this;
    }

    /**
     * @return int
     */
    public function getAckWaitSecs()
    {
        return $this->ackWaitSecs;
    }

    /**
     * @param int $ackWaitSecs
     * @return $this
     */
    public function setAckWaitSecs($ackWaitSecs)
    {
        $this->ackWaitSecs = $ackWaitSecs;

        return $this;
    }

    /**
     * @return StartPosition
     */
    public function getStartAt()
    {
        return $this->startAt;
    }

    /**
     * @param StartPosition $startAt
     * @return $this
     */
    public function setStartAt($startAt)
    {
        $this->startAt = $startAt;
        return $this;
    }

    /**
     * @return int
     */
    public function getStartSequence()
    {
        return $this->startSequence;
    }

    /**
     * @param int $startSequence
     * @return $this
     */
    public function setStartSequence($startSequence)
    {
        $this->startSequence = $startSequence;
        return $this;
    }

    /**
     * @return int
     */
    public function getStartMicroTime()
    {
        return $this->startMicroTime;
    }

    /**
     * @param DateTime $startMicroTime
     * @return $this
     */
    public function setStartMicroTime($startMicroTime)
    {
        $this->startMicroTime = $startMicroTime;
        return $this;
    }
}
