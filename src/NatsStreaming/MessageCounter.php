<?php

namespace NatsStreaming;


class MessageCounter
{

    private $subMessagesReceivedMap = [];
    private $subMessagesWitnessedMap = [];

    public function incSubMsgsReceived($sid){
        $count = @$this->subMessagesReceivedMap[$sid];
        if(!$count) {
            $count = 0;
        }

        $count ++;
        $this->subMessagesReceivedMap[$sid] = $count;
    }

    public function getSubMsgsReceived($sid) {
        $count = @$this->subMessagesReceivedMap[$sid];
        if(!$count) {
            $count = 0;
        }

        return $count;
    }

    public function incSubMsgsWitnessed($sid, $messages = 1) {

        $count = @$this->subMessagesWitnessedMap[$sid];
        if(!$count) {
            $count = 0;
        }

        $count += $messages;
        $this->subMessagesWitnessedMap[$sid] = $count;
    }

    public function getSubMsgsWitnessed($sid) {
        $count = @$this->subMessagesWitnessedMap[$sid];
        if(!$count) {
            $count = 0;
        }
        return $count;
    }

}