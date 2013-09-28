<?php
/*

restlerLocation should point to the 'vendor' folder of a Restler 3 installation.

secretConfigLocation should point to a file with the following code:
class JConfig {
	var $dbtype = 'mysql'; // only DB supported
	var $user = 'the db user';
	var $password = 'the db user password';
	var $db = 'the db name';
	var $host = 'the host name';
	var $hostName = 'the host name';
	var $SECRET_API_KEY = 'the secret api key to access protected methodes'; 
}
*/
$secretConfigLocation = '../../../webprivate/home/homeconfiguration.php';
$restlerLocation = '../../../webprivate/restler3/vendor';

require_once($secretConfigLocation);
?>