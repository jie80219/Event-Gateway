<?php
require_once __DIR__ . '/../vendor/autoload.php';

use PhpAmqpLib\Connection\AMQPStreamConnection;
use SDPMlab\AnserGateway\ServiceDiscovery\LoadBalance\EntropyScoring;

$mqQueue = 'recalc_weight';
$conn = new AMQPStreamConnection('10.1.1.94', 5672, 'guest', 'guest');
$ch = $conn->channel();
$ch->queue_declare($mqQueue, false, true, false, false);

$ch->basic_consume($mqQueue, '', false, true, false, false, function () {
    $scorer = new EntropyScoring();
    $scorer->recalculateScores();
});

while ($ch->is_consuming()) {
    $ch->wait();
}
