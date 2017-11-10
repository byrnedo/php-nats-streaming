<?php

namespace NatsStreaming;


class Subscription
{

    use Fillable;

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

    private $fillable = [
        'subject',
        'qGroup',
        'inbox',
        'ackInbox',
        'opts',
        'cb'
    ];

    public function __construct($options = null)
    {
        if (empty($options) === false) {
            $this->initialize($options);
        }
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

}