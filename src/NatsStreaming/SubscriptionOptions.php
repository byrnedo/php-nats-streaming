<?php
/**
 * Created by IntelliJ IDEA.
 * User: donal
 * Date: 2017-11-10
 * Time: 15:00
 */

namespace NatsStreaming;


use DateTime;
use pb\StartPosition;

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
    private $ackWaitMs = 30000;

    /**
     * @var StartPosition
     */
    private $startAt = null;

    /**
     * @var int
     */
    private $startSequence = 0;

    /**
     * @var DateTime
     */
    private $startTime = null;

    /**
     * @var bool
     */
    private $manualAck = false;

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
    public function getAckWaitMs()
    {
        return $this->ackWaitMs;
    }

    /**
     * @param int $ackWaitMs
     * @return $this
     */
    public function setAckWaitMs($ackWaitMs)
    {
        $this->ackWaitMs = $ackWaitMs;

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
     * @return DateTime
     */
    public function getStartTime()
    {
        return $this->startTime;
    }

    /**
     * @param DateTime $startTime
     * @return $this
     */
    public function setStartTime($startTime)
    {
        $this->startTime = $startTime;
        return $this;
    }

}