<?php

// Example file for apc.class.php

// Turn errors/notices on to get pretty error messages from the class
ini_set("display_errors", "on");
error_reporting(E_ALL);

// Require the class (obviously)
require_once('apc.class.php');

// Instantiate the class
$pdu = new apcPduConnect();

// Set the IP
if(!$pdu->setIP('192.168.1.1')) {
    exit();
}

// Set the username
$pdu->setUsername('apc');

// Set the password
$pdu->setPassword('apc');

// Open connection
$pdu->connect();

// Download config to the local machine
$pdu->getConfig();

// Parse the config into an array
$pduConfig = $pdu->parseConfig();

// Now the fun part, change values.  Refer to an existing config.ini
// for exactly what to change.  A few basics are here for reference, but
// the syntax is $config[$directive][$item] = $value, where $directive is
// something like [NetworkTCP/IP] that's in a regular config.ini, $item
// is what goes on the left side of the config line, $value is the right side.
//
// Setting something here will overwrite an existing value, be careful!
$pduConfig['SystemID']['Contact'] = 'Testing';

// Write the config back to the system, exit if it doesn't work.  If notices
// are turned on, you'll get a message.
if(!$pdu->writeConfig($pduConfig)) {
    exit();
}

// Close the connection
$pdu->close();

?>
