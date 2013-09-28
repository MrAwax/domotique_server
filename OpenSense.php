<?php

require_once('Config.php');

use PDO;

class OpenSense
{

	public $offset;
	public $db;
	public $config;
	public $opensense;
	
	function __construct ()
    {
		$paris_time = new DateTimeZone('Europe/Paris');
		$utc_time = new DateTimeZone('UTC');
		$this->offset = $paris_time->getOffset(new DateTime());
				
		$this->config = new JConfig();
		$this->opensense = new OpenSenseConfig();
				
		try {
			$this->db = new PDO(
					'mysql:host=' . $this->config->host . ';dbname=' . $this->config->db,
					$this->config->user,
					$this->config->password,
					array(PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES 'utf8'")
				);
			$this->db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE,
				PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
file_put_contents('apidev.log', $e->getMessage());
            throw new RestException(500, 'MySQL: ' . $e->getMessage());
        }
	}
	
	protected function post($device_id = 0, $feed_id = 0, $timetag = null, $value = null)
    {
		file_put_contents('/tmp/apidev.log', print_r($_POST, true));
		
		if (!isset($this->opensense->feeds[$feed_id]))  {
			throw new RestException(400, 'Unknown feed id ' . $feed_id);
		}

		$dateTime = mktime();
		if (isset($timetag)) {
			$dateTime = strtotime($timetag);
		}		
		
		$feed = (object)$this->opensense->feeds[$feed_id];
		
		if ($feed->type == "field_event") {
			$this->postFieldEvent($feed->field, $value, 'Open.Sen.se', $dateTime);
		}
	}
		
	private function postFieldEvent($field, $text, $author, $dateTime) {
		$paris_time = new DateTimeZone('Europe/Paris');
		$utc_time = new DateTimeZone('UTC');
		$offset = $paris_time->getOffset(new DateTime());
		
		$this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);	
		try {	
			$this->db->beginTransaction();
			
			$stmt = $this->db->prepare("insert into field_event (event, field, text, author) values (FROM_UNIXTIME(:event), :field, :text, :author)");
			$stmt->bindParam(":event", $dateTime);
			$stmt->bindParam(":field", $field);
			$stmt->bindParam(":text", $text);
			$stmt->bindParam(":author", $author);
			$stmt->execute();			
			$id =  $this->db->lastInsertId();
			
			$this->db->commit();
			
			return true;
		} catch (RestException $e) {
			$this->db->rollback();
			throw $e;
		} catch (Exception $e) {
			error_log($e->getMessage() . "\n" . debug_backtrace());
			$this->db->rollback();
			throw new RestException(500, 'MySQL: ' . $e->getMessage() . "\n" . debug_backtrace() );
		}
    }
    
}