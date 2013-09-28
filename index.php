<?php
require_once('Config.php');
require_once($restlerLocation . '/restler.php');

use Luracast\Restler\Restler;

$r = new Restler();
$r->addAPIClass('Sensors');
$r->addAPIClass('SensorData');
$r->addAPIClass('Events');
$r->addAPIClass('Resources');
$r->addAuthenticationClass('SimpleAuth');
$r->handle();
?>