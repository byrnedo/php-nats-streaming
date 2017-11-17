<?php

namespace NatsStreaming;

use Nats\Message;

class TrackedNatsSub {
    private $sid;
    private $stanCon;

    /**
     * TrackedNatsSub constructor.
     * @param $sid
     * @param $stanCon Connection
     */
    public function __construct($sid, $stanCon)
    {
        $this->sid = $sid;
        $this->stanCon = $stanCon;
    }

    public function wait(){
        $messages = 1;
        $initialWitnessed = $this->stanCon->witness->getSubMsgsWitnessed($this->sid);
        while(true) {
            $countPreRead = $this->stanCon->witness->getSubMsgsReceived($this->sid);
            if (($countPreRead - $initialWitnessed) >= $messages) {
                return;
            }

            if ($this->stanCon->witness->getSubMsgsWitnessed($this->sid) < $countPreRead) {
                $this->stanCon->witness->incSubMsgsWitnessed($this->sid);
                continue;
            }

            if (!$this->stanCon->socketInGoodHealth()) {
                return;
            }
            $this->stanCon->natsConn()->wait(1);

            $countPostRead = $this->stanCon->witness->getSubMsgsReceived($this->sid);
            if ($countPostRead > $countPreRead) {
                // we got one
                $this->stanCon->witness->incSubMsgsWitnessed($this->sid);
            }
        }
    }

    /**
     * @return mixed
     */
    public function getSid()
    {
        return $this->sid;
    }
}

