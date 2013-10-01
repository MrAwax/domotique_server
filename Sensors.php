<?php

require_once('Config.php');

use PDO;
use Luracast\Restler\RestException;

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

		
		$this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);	
		try {						
			$stmt = $this->db->query("select t.name, t.fields, t.table_name from sondes as s, sensor_type as t where s.type = t.name and s.id1 = $id1 and s.id2 = $id2");
			$sensorDef = $stmt->fetchAll();
			if (count($sensorDef)==0) {
				throw new RestException(404, "Unknown sensor $id");
			}
			$sensorDef = (object)$sensorDef[0];
		
			$query = $this->buildQuery(explode(",", $sensorDef->fields), $sensorDef->table_name);
			$query .= " and c.id1 = $id1 and c.id2 = $id2";
	
		
			$stmt = $this->db->query($query);
			$ret = $stmt->fetchAll();
			if (count($ret)>0) {				
				$ret = $this->parseSensor($ret);
				return $ret[0];
			}
			throw new RestException(404, "No data for sensor $id");			
		} catch (Exception $e) {
			throw new RestException(500, 'MySQL: ' . $e->getMessage());
		}
    }
	  
	function index($sensorType = null)
    {	
		$this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);	
		try {
			$clause = '';
			if (isset($sensorType)) {
				$clause = " where name = '$sensorType'";
			}
			$stmt = $this->db->query("select name, default_field, fields, table_name from sensor_type" . $clause);
			$sensorTypes = $stmt->fetchAll();
			// var_dump($sensorTypes);
			
			$ret = array();
			$types = array();
			foreach($sensorTypes as $itType) {
				$itType = (object)$itType;
				$types[] = $itType->name;
				// var_dump($itType);
				
				$fields = explode(",", $itType->fields);
				
				$query = $this->buildQuery($fields, $itType->table_name);
				// echo $query ."\n";
				
				$stmt = $this->db->query($query);
				$ret = array_merge($ret, $stmt->fetchAll());
			}									
			$sensors = $this->parseSensor($ret);
			$output["types"] = $types;
			$output["count"] = count($sensors);
			$output["sensors"] = $sensors;
			
			return $output;
		} catch (Exception $e) {
			throw new RestException(500, 'MySQL: ' . $e->getMessage());
		}
    }

	private function buildQuery($fields, $table_name) {
		$paris_time = new DateTimeZone('Europe/Paris');
		$utc_time = new DateTimeZone('UTC');
		$offset = $paris_time->getOffset(new DateTime());
		
		$query = "select concat(s.id1, '-', s.id2) as id, s.type, t.fields, t.default_field, s.name, ";
		$query .= "(unix_timestamp(c.event) + $offset ) * 1000 as event, (unix_timestamp(c.old_event) + $offset ) * 1000 as old_event, ";
		foreach($fields as $field) {
			$query .= 'c.' . $field . ", ";
		}
		$query .= "c.battery, c.signal ";
		$query .= "from current_" . $table_name . " as c, sondes as s, sensor_type as t ";
		$query .= "where s.id1 = c.id1 and c.id2 = s.id2 and s.type = t.name";
		
		return $query;
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