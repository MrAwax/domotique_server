<?php

require_once('Config.php');

use PDO;

class Sensors
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
			$this->db = new PDO('mysql:host=' . $this->config->host . ';dbname=' . $this->config->db, $this->config->user, $this->config->password);
			$this->db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE,
				PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            throw new RestException(500, 'MySQL: ' . $e->getMessage());
        }
	}
	
	/**
     * @param string $id sensor id
     *
     */
	function get($id)
    {
		$splitid = strpos($id, '-');
		$id1 = substr($id, 0, $splitid);
		$id2 = substr($id, $splitid+1);

        $paris_time = new DateTimeZone('Europe/Paris');
		$utc_time = new DateTimeZone('UTC');
		$offset = $paris_time->getOffset(new DateTime());
		
		$this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);	
		try {						
			$stmt = $this->db->query("select concat(s.id1, '-', s.id2) as id, s.type, t.fields, t.default_field, s.name, (unix_timestamp(w.event) + $offset ) * 1000 as event, (unix_timestamp(w.old_event) + $offset ) * 1000  as old_event, w.temp, w.humi, w.battery, w.signal from current_weather as w, sondes as s, sensor_type as t where s.id1 = w.id1 and w.id2 = s.id2 and s.type = t.name and s.id1 = $id1 and s.id2 = $id2");
			$ret = $stmt->fetchAll();
			if (count($ret)>0) {
				$ret = $this->parseSensor($ret);
				return $ret[0];
			}
			$stmt = $this->db->query("select concat(s.id1, '-', s.id2) as id, s.type, t.fields, t.default_field, s.name, (unix_timestamp(w.event) + $offset ) * 1000 as event, (unix_timestamp(w.old_event) + $offset ) * 1000 as old_event, w.power, w.total, w.battery, w.signal from current_power as w, sondes as s, sensor_type as t where s.id1 = w.id1 and w.id2 = s.id2 and s.type = t.name and s.id1 = $id1 and s.id2 = $id2");
			$ret = $stmt->fetchAll();
			if (count($ret)>0) {				
				$ret = $this->parseSensor($ret);
				return $ret[0];
			}						
			
		} catch (Exception $e) {
			throw new RestException(500, 'MySQL: ' . $e->getMessage());
		}
    }
	  
	function index()
    {	
		$paris_time = new DateTimeZone('Europe/Paris');
		$utc_time = new DateTimeZone('UTC');
		$offset = $paris_time->getOffset(new DateTime());
		
		$this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);	
		try {						
			$stmt = $this->db->query("select concat(s.id1, '-', s.id2) as id, s.type, t.fields, t.default_field, s.name, (unix_timestamp(w.event) + $offset ) * 1000 as event, (unix_timestamp(w.old_event) + $offset ) * 1000  as old_event, w.temp, w.humi, w.battery, w.signal from current_weather as w, sondes as s, sensor_type as t where s.id1 = w.id1 and w.id2 = s.id2 and s.type = t.name");
			$ret = $stmt->fetchAll();
			$stmt = $this->db->query("select concat(s.id1, '-', s.id2) as id, s.type, t.fields, t.default_field, s.name, (unix_timestamp(w.event) + $offset ) * 1000 as event, (unix_timestamp(w.old_event) + $offset ) * 1000 as old_event, w.power, w.total, w.battery, w.signal from current_power as w, sondes as s, sensor_type as t where s.id1 = w.id1 and w.id2 = s.id2 and s.type = t.name");
			$ret = array_merge($ret, $stmt->fetchAll());
									
			return $this->parseSensor($ret);
		} catch (Exception $e) {
			throw new RestException(500, 'MySQL: ' . $e->getMessage());
		}
    }
	
	private function parseSensor($sensors)
	{
		foreach($sensors as $sensor) {
			$fields = $sensor["fields"];
			unset($sensor["fields"]);
			$fields = explode(",", $fields);
			
			$values = array();
			foreach($fields as $field) {
				$values[$field] = floatval($sensor[$field]);
				unset($sensor[$field]);
			}
			$sensor["values"] = $values;
			
			$period = array();
			$event = floatval($sensor["old_event"]);		
			$period["from"] =  gmstrftime('%Y-%m-%dT%H:%M:%S',floor($event/1000));
			$event = floatval($sensor["event"]);
			$period["to"] =  gmstrftime('%Y-%m-%dT%H:%M:%S',floor($event/1000));
			unset($sensor["event"]);	
			unset($sensor["old_event"]);	
			$sensor["dateRange"] = $period;	
			
			$health = array();
			$health["battery"] = intval($sensor["battery"]);
			$health["signal"] = intval($sensor["signal"]);
			unset($sensor["battery"]);
			unset($sensor["signal"]);
			$sensor["health"] = $health;
			
			$ret[] = $sensor;
		}
		return $ret;
	}	
}