<?php


use Daalvand\Safar724AutoTrack\TicketChecker;
use Daalvand\Safar724AutoTrack\ValueObjects\TicketCheckerValueObject;

require __DIR__ . '/vendor/autoload.php';


$from        = '1402-06-31';
$to          = '1402-07-05';
$source      = 'khorramabad';
$destination = 'tehran';
$chatId      = 683434092;

$valueObject = new TicketCheckerValueObject($from, $to, $source, $destination, $chatId);
$valueObject->setCheckDuration(60);

$checker     = new TicketChecker();
$checker->track($valueObject);