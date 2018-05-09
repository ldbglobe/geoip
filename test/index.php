<?php
require "../vendor/autoload.php";

use ldbglobe\geoip\geoip;

geoip::SetStoragePath('./storage');

// to use custom just call the AddSource method with a source instance
// ex :
// geoip::AddSource(new \ldbglobe\geoip\source\ipstack('YOUR_PERSONNAL_AUTH_KEY_XXXXXXXXXXXXXXXXXXXXXXXXXXXXXXX'));
// geoip::AddSource(new \ldbglobe\geoip\source\ipinfodb('YOUR_PERSONNAL_AUTH_KEY_XXXXXXXXXXXXXXXXXXXXXXXXXXXXXXX'));

// default sources will be automaticaly added if no custom source is set
// so this line is optionnal
geoip::AddDefaultSources();

$geoip = new geoip( isset($_GET['ip']) && !empty($_GET['ip']) ? $_GET['ip'] : null );
$location = $geoip->resolve_location();

header('content-type:application/json');
echo json_encode($location);