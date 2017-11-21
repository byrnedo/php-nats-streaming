<?php


$scriptDir = realpath(dirname(__FILE__));
require_once($scriptDir . '/../../../vendor/autoload.php');

$clientId = $argv[1];
$subject = $argv[2];
$numMessages = $argv[3];
$durableName = @$argv[4];


$opts = new \NatsStreaming\ConnectionOptions();
$opts->setClientID($clientId);

$con = new \NatsStreaming\Connection($opts);
$con->connect();


$subOpts = new \NatsStreaming\SubscriptionOptions();
if ($durableName) {
    $subOpts->setDurableName($durableName);
}
$count = 0;
$sub = $con->subscribe($subject, function($message)use(&$count){
    $count ++;
}, $subOpts);

$sub->wait($numMessages);

if ($count != $numMessages) {
    error_log('ERROR: expected ' . $numMessages . ', got ' . $count);
    die(2);
}

$con->close();

echo "${count}\n";
