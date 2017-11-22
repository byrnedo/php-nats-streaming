<?php


namespace NatsStreaming;

use NatsStreamingProtos\Ack;
use NatsStreamingProtos\MsgProto;
use Protobuf\Configuration;

class Msg extends MsgProto
{

    /**
     * @var Subscription
     */
    private $sub = null;

    /**
     * {@inheritdoc}
     */
    public static function fromStream($stream, Configuration $configuration = null)
    {
        return new self($stream, $configuration);
    }

    /**
     * @return null
     */
    public function getSub()
    {
        return $this->sub;
    }

    /**
     * @param null $sub
     * @return $this
     */
    public function setSub($sub)
    {
        $this->sub = $sub;
        return $this;
    }

    /**
     * Send an Acknowledgement for a received message
     *
     * Must use if isManualAck
     * @return void
     */
    public function ack()
    {
        $req = new Ack();
        $req->setSubject($this->getSubject());
        $req->setSequence($this->getSequence());
        $data = $req->toStream()->getContents();
        $stanCon = $this->sub->getStanCon();
        $stanCon->natsCon()->publish($this->sub->getAckInbox(), $data);
    }
}
