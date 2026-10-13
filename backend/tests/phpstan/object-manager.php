<?php

require dirname(__DIR__, 2).'/vendor/autoload.php';

$kernel = new App\Kernel('dev', true);
$kernel->boot();

return $kernel->getContainer()->get('doctrine')->getManager();
