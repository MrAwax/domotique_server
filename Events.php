<?php

require_once('Config.php');

use PDO;

class Events
{

	public $offset;
	public $db;
	public $config;
	
	function __construct ()
    {
		$paris_time = new DateTimeZone('Europe/Paris');
		$utc_time = new DateTimeZone('UTC');
		$this->offset = $paris_time->getOffset(new DateTime());
				
		$this->config = new JConfig();		
		
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
            throw new RestException(500, 'MySQL: ' . $e->getMessage());
        }
	}
	
	function get($field, $begin = 0, $end = 0)
    {
		$this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);	
			
		$paris_time = new DateTimeZone('Europe/Paris');
		$utc_time = new DateTimeZone('UTC');
		$offset = $paris_time->getOffset(new DateTime());		
		
		$req ="";
		
		try {						
			if (is_numeric($begin)) {
				$begin = intval($begin);
			} else { 
				$begin = strtotime($begin);
			}
			
			if (is_numeric($end)) {
				$end = intval($end);
			} else { 
				$end = strtotime($end);
			}
					
			if ($begin <= 0) {
				$begin = (time () - 24*60*60);
			}
			if ($end <= 0) {
				$end = time ();
			}
			
			$range = $end - $begin;
			$table_suffix = '';
			if ($range > 60 * 60 * 24 * 30)
			{
				$table_suffix = '_day';
			} elseif ($range >  60 * 60 * 24 * 2 )
			{
				$table_suffix = '_hour';
			} elseif ($range > 60 * 60 * 2)
			{
				$table_suffix = '_five';
			}
			
			$startTime = gmstrftime('%Y-%m-%d %H:%M:%S', floor(($begin - 0.1 * $range )));
			$endTime = gmstrftime('%Y-%m-%d %H:%M:%S', floor(($end + 0.1 * $range )));

			$today = round(time()/(24*60*60))*24*60*60;		
			
			$this->db->beginTransaction();
						
			$output = $this->getWhere($field, "event between '$startTime' and '$endTime'");
			
			$dateRange["from"] = gmstrftime('%Y-%m-%dT%H:%M:%S',floor($begin));
			$dateRange["to"] = gmstrftime('%Y-%m-%dT%H:%M:%S',floor($end));	
			$output["dateRange"] = $dateRange;
			
			$this->db->rollback();
			
			return $output;
		} catch (RestException $e) {
			throw $e;
		} catch (Exception $e) {
			throw new RestException(500, 'MySQL: ' . $e->getMessage() );
		}
    }
	
	private function getWhere($field, $clause)
	{
		$paris_time = new DateTimeZone('Europe/Paris');
		$utc_time = new DateTimeZone('UTC');
		$offset = $paris_time->getOffset(new DateTime());		
		
		try {
			$req = "select ( round(unix_timestamp(w.event)/30)*30 + $offset )  as event, event_id, text, author from field_event as w where field = '$field' and $clause order by event asc";
			$stmt = $this->db->query($req);
			$ret = $stmt->fetchAll();
			
			$result = array();
			foreach($ret as $row) {
				extract($row);
				$ret[] = "{x: $event, field: '$field', text: '$text', author: '$author', title: $event_id}";
				$result[] = array("id" => $event_id, "event" => $event, "text" => $text, "author" => $author);
			}
			
			$output["field"] = $field;			
			$output["events"] = $result;
			$output["count"] = count($result);
			
			return $output;
		} catch (RestException $e) {
			$this->db->rollback();
			throw $e;
		} catch (Exception $e) {
			$this->db->rollback();
			throw new RestException(500, 'MySQL: ' . $e->getMessage() . "\n" . debug_backtrace()  );
		}		
	}
	
	/**
	 * @param string $field		field corresponding to this event
     * @param string $text		{@from body} event text
	 * @param string $author	{@from body} event author
	 *
	 * @return mixed
	 */
	protected function post($field, $text, $author = null)
    {
		$paris_time = new DateTimeZone('Europe/Paris');
		$utc_time = new DateTimeZone('UTC');
		$offset = $paris_time->getOffset(new DateTime());

		$this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);	
		try {	
			$this->db->beginTransaction();
			
			$stmt = $this->db->prepare("insert into field_event (event, field, text, author) values (NOW(), :field, :text, :author)");
			$stmt->bindParam(":field", $field);
			$stmt->bindParam(":text", $text);
			$stmt->bindParam(":author", $author);
			$stmt->execute();			
			$id =  $this->db->lastInsertId();
			
			$output = $this->getWhere($field, "event_id = $id");
			$output["event_id"] = $id;
			
			$this->db->commit();
			
			return $output;
		} catch (RestException $e) {
			$this->db->rollback();
			throw $e;
		} catch (Exception $e) {
			$this->db->rollback();
			throw new RestException(500, 'MySQL: ' . $e->getMessage() . "\n" . debug_backtrace() );
		}
    }
    
    /**
     * @param int $id event id
     *
     */
    protected function delete($id) 
    {
    	try {	
			$this->db->beginTransaction();
			$this->db->prepare("DELETE from field_event where event_id = ?")->execute(array(intval($id)));
			$this->db->commit();
			return TRUE;
		} catch (RestException $e) {
			$this->db->rollback();
			throw $e;
		} catch (Exception $e) {
			$this->db->rollback();
			throw new RestException(500, 'MySQL: ' . $e->getMessage() . "\n" . debug_backtrace() );
		}
    }
}