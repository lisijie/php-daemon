#!/bin/env php
<?php
require dirname(__FILE__).'/phpdaemon.php';

function handler($pno) {
	for (;;) {
		echo "this is #{$pno}\n";
		sleep(3);
	}
}

$obj = new PHPDaemon();
$obj->setProcessNum(10);
$obj->setHandler("handler");
$obj->run();

