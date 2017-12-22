<?php
require "../vendor/autoload.php";

use ldbglobe\geoip\geoip;

geoip::SetStoragePath('./storage');

$geoip = new geoip( isset($_GET['ip']) && !empty($_GET['ip']) ? $_GET['ip'] : null );
$location = $geoip->resolve_location();

header('content-type:application/json');
echo json_encode($location);