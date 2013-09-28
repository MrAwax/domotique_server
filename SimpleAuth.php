<?php

require_once('Config.php');

use Luracast\Restler\iAuthenticate;

class SimpleAuth implements iAuthenticate
{
    public $config;

	function __construct() {
		$this->config = new JConfig();	
	}


    function __isAllowed()
    {
	    	if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
	    		return $_SERVER['HTTP_AUTHORIZATION'] == $this->config->SECRET_API_KEY ? TRUE : FALSE;
	    	}
    
        if (isset($_GET['api_key'])) {
        	return $_GET['api_key'] == $this->config->SECRET_API_KEY ? TRUE : FALSE;
        }
    }
    
}
?>