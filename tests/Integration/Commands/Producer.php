<?php

$scriptDir = realpath(dirname(__FILE__));
require_once($scriptDir . '/../../../vendor/autoload.php');

$clientId = $argv[1];
$subject = $argv[2];
$numMessages = $argv[3];


$opts = new \NatsStreaming\ConnectionOptions();
$opts->setClientID($clientId);

$con = new \NatsStreaming\Connection($opts);
$con->connect();


$subOpts = new \NatsStreaming\SubscriptionOptions();
$count = 0;
for(; $count < $numMessages;  $count++) {
    $ack = $con->publish($subject, 'foobar '.$count);
    $ack->wait();
}


$con->close();

echo "${count}\n";
