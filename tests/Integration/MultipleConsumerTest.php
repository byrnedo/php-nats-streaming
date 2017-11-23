<?php



class MultipleConsumerTest extends PHPUnit_Framework_TestCase
{

    private $pids = [];

    private $phpCmd = '';
    private $logPrefix;

    public function setUp()
    {
        parent::setUp();
        $this->phpCmd = str_replace("\n", '', `which php`);
        $this->logPrefix = $this->getName();
    }


    private function runScript($script, $args)
    {


        $suffix = basename($script) . uniqid('',true).'.log';

        $logfile = '/tmp/'.$this->logPrefix . $args[0] . $suffix;
        $command =  $this->phpCmd . ' ' .  $script . ' ' . implode(' ', $args) . ' > ' . $logfile . ' 2>&1 & echo $! ';
        $this->pids[] = exec($command, $output);
        return $logfile;
    }

    private function runConsumer($clientId, $subject, $expectMessages, $durableName)
    {
        $scriptDir = realpath(dirname(__FILE__));
        return $this->runScript($scriptDir . '/Commands/Consumer', [$clientId, $subject, $expectMessages, $durableName]);
    }

    private function runProducer($clientId, $subject, $pubMessages)
    {
        $scriptDir = realpath(dirname(__FILE__));
        return $this->runScript($scriptDir . '/Commands/Producer', [$clientId, $subject, $pubMessages]);
    }

    public function testMultipleConsumers()
    {

        $subject = uniqid('integration.multiconsumer.');
        $numMessages = 10;

        // run consumer 1
        $logCon1 = $this->runConsumer(uniqid(), $subject, $numMessages, '');

        // run consumer 2
        $logCon2 = $this->runConsumer(uniqid(), $subject, $numMessages, '');

        sleep(1);

        // run producer
        $logProd = $this->runProducer(uniqid(), $subject, $numMessages);

        // wait for processes to finish
        sleep(1);

        $this->checkConsumer($logCon1, $numMessages);
        $this->checkConsumer($logCon2, $numMessages);
        $this->checkProducer($logProd, $numMessages);
    }


    public function testDurableConsumer()
    {

        $subject = uniqid('integration.multiconsumer.');
        $numMessages = 10;

        // run consumer 1

        $logCon1 = $this->runConsumer('client1', $subject, $numMessages, 'test');


        sleep(1);

        // run producer
        $logProd1 = $this->runProducer(uniqid(), $subject, $numMessages);


        // wait for processes to finish
        sleep(1);

        $this->checkConsumer($logCon1, $numMessages);
        $this->checkProducer($logProd1, $numMessages);

        $logProd2 = $this->runProducer(uniqid(), $subject, 1);

        sleep(1);

        $logCon2 = $this->runConsumer('client1', $subject, 1, 'test');

        // wait for processes to finish
        sleep(1);

        $this->checkConsumer($logCon2, $numMessages +1);
        $this->checkProducer($logProd2, 1);
    }

    private function checkProducer($log, $messages)
    {
        return $this->checkConsumer($log, $messages);
    }

    private function checkConsumer($log, $maxSeq)
    {
        $data=file($log);
        $lastLine=@$data[count($data)-1];
        $count = trim($lastLine);
        $this->assertEquals($maxSeq, $count, "$maxSeq did not equal expected $count. Log: " . implode("", $data));
    }

    protected function tearDown()
    {
        parent::tearDown();

        foreach ($this->pids as $pid) {
            posix_kill($pid, 9);
        }
    }
}
