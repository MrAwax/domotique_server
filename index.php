<?php
require_once '../../../webprivate/restler3/vendor/restler.php';
//require_once 'Session.php';
use Luracast\Restler\Restler;


$r = new Restler();
$r->addAPIClass('Sensors');
$r->addAPIClass('SensorData');
$r->addAPIClass('Events');
$r->addAPIClass('Resources');
$r->addAuthenticationClass('SimpleAuth');
$r->handle();
?>