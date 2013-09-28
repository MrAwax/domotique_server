<?php

use Luracast\Restler\iAuthenticate;

class SimpleAuth implements iAuthenticate
{
    const KEY = 'BzpABl9lchqzgHPZOwtvQsTWAZScmRpeKJfnWEzl2fyVtFNMEC';
    function __isAllowed()
    {
    	
    	if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
    		return $_SERVER['HTTP_AUTHORIZATION'] == SimpleAuth::KEY ? TRUE : FALSE;
    	}
    
        if (isset($_GET['api_key'])) {
        	return $_GET['api_key'] == SimpleAuth::KEY ? TRUE : FALSE;
        }
    }
    private function key()
    {
        return SimpleAuth::KEY;
    }
}
?>