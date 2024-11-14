<?php

require __DIR__ .'/../vendor/autoload.php';

use Khalilleo\VCardParser\VCardWrapper;

$vcfFileExample1 = 'Example-01.vcf';
$vcfFileExample2 = 'Example-02.vcf';
$vcfFileExample3 = 'Example-03.vcf';

$vCardWrapper = new VCardWrapper($vcfFileExample1);

// var_export($vCardWrapper->asArray());

echo $vCardWrapper->asJson();
