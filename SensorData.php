<?php

require_once('../../../webprivate/home/homeconfiguration.php');

use PDO;

class SensorData
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
     * @param string $field field
     * @param string $begin begin date and time
     * @param string $end end date and time
     *
     * @return object
     *
     */
	function get($id, $field = "", $begin = 0, $end = 0)
    {
		$this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);	
	
		$splitid = strpos($id, '-');
		$id1 = substr($id, 0, $splitid);
		$id2 = substr($id, $splitid+1);		
		
		$paris_time = new DateTimeZone('Europe/Paris');
		$utc_time = new DateTimeZone('UTC');
		$offset = $paris_time->getOffset(new DateTime());		
		
		$req ="";
		
		try {			
			$stmt = $this->db->query("select s.type, t.table_name, t.default_field from sondes as s, sensor_type as t where $id1 = s.id1 and $id2 = s.id2 and s.type = t.name");		
			$ret = $stmt->fetchAll();
			if  (count($ret) != 1) {
				throw new RestException(500, "Sensor $id not found");
			}
			extract($ret[0]);
			if ($field == "") {
				$field = $default_field;
			}
			
			if (is_numeric($begin)) {
				$begin = intval($begin);
			} else { 
				$begin = strtotime($begin)*1000;
			}
			
			if (is_numeric($end)) {
				$end = intval($end);
			} else { 
				$end = strtotime($end)*1000;
			}
					
			if ($begin <= 0) {
				$begin = (time () - 24*60*60)*1000;
			}
			if ($end <= 0) {
				$end = time () * 1000;
			}
			
			$range = $end - $begin;
			$table_suffix = '';
			if ($range > 1000 * 60 * 60 * 24 * 30)
			{
				$table_suffix = '_day';
			} elseif ($range > 1000 * 60 * 60 * 24 * 2 )
			{
				$table_suffix = '_hour';
			} elseif ($range > 1000 * 60 * 60 * 2)
			{
				$table_suffix = '_five';
			}
			
			$startTime = gmstrftime('%Y-%m-%d %H:%M:%S', floor(($begin - 0.1 * $range ) / 1000));
			$endTime = gmstrftime('%Y-%m-%d %H:%M:%S', floor(($end + 0.1 * $range ) / 1000));

			$today = round(time()/(24*60*60))*24*60*60*1000;
			
			$req = "select ( round(unix_timestamp(w.event)/30)*30 + $offset ) * 1000 as event, round(w.$field, 2) as field_value from $table_name$table_suffix as w where w.id1 = $id1 and w.id2 = $id2 and event between '$startTime' and '$endTime'";
			$stmt = $this->db->query($req);
			$ret = $stmt->fetchAll();
			
			foreach($ret as $row) {
				extract($row);
				$result[] = array($event, $field_value);
			}
			
			$output["sensor"] = $id;
			$output["field"] = $field;
			$output["tableName"] = $table_name . $table_suffix;
			$output["begin"] = $begin;
			$output["end"] = $end;
			$output["beginDate"] = gmstrftime('%Y-%m-%dT%H:%M:%S',floor($begin/1000));
			$output["endDate"] = gmstrftime('%Y-%m-%dT%H:%M:%S',floor($end/1000));	
			$output["data"] = $result;
			
			return $output;			
		} catch (RestException $e) {
			throw $e;
		} catch (Exception $e) {
			throw new RestException(500, 'MySQL: ' . $e->getMessage() . "\n" . $req );
		}
    }
}