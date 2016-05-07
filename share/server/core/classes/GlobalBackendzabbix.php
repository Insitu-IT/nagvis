<?php

/*
 * Backend for get data from Zabbix API
 */

class GlobalBackendzabbix implements GlobalBackendInterface {

	private $backendId;
	private $cfgUrl;
	private $cfgLogin;
	private $cfgPass;
	
	private static $validConfig = Array(
	'url' => Array('must' => 1,
			'editable' => 1,
			'default' => 'http://localhost/zabbix/api_jsonrpc.php',
			'match' => MATCH_STRING_URL),
		'login' => Array('must' => 1,
			'editable' => 1,
			'default' => 'Admin',
			'match' => MATCH_STRING),
		'pass' => Array('must' => 1,
			'editable' => 1,
			'default' => 'zabbix',
			'match' => MATCH_STRING),
	);

	public function __construct($backendId) {
		$this->backendId = $backendId;
		$this->cfgUrl = cfg('backend_'.$backendId, 'url');
		$this->cfgLogin = cfg('backend_'.$backendId, 'login');
		$this->cfgPass = cfg('backend_'.$backendId, 'pass');
	$this->id = 0;

		return true;
	}
	
	/**
	 *  Send data to Zabbix API
	 * @param array $data
	 * @return array
	 */
	private function sendRequest($data) {

		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $this->cfgUrl);
		curl_setopt($ch, CURLOPT_POST, 1);
		curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 2);
		curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json-rpc', 'Content-Length: '.strlen(json_encode($data))]);
	$result = curl_exec($ch);

#	error_log('Data: '.var_export($data, TRUE)."\n\n", 3, '/tmp/php.log');
#	error_log('Request:'.json_encode($data)."\n\n", 3, '/tmp/php.log');
#	error_log('Response:'.$result."\n\n\n\n", 3, '/tmp/php.log');

		$result = json_decode($result, TRUE);
		curl_close($ch);
		$this->id = $this->id + 1;

		return $result;
	}

	private function arrayCheck($objects) {
		if(is_array($objects)) {
			$result = "";
			foreach ($objects as $key => $value) {
				$result[$key] = $value;
			}
			return $result;
		} else {
			return $objects;
		}
	}
	
	/**
	 * Get auth key for login/password
	 * @return string
	 */
	private function auth() {
		$response = $this->sendRequest([
			'jsonrpc' => '2.0',
			'method' => 'user.login',
			'params' => [
				'user' => $this->cfgLogin,
				'password' => $this->cfgPass
			],
			'id' => $this->id,
			'auth' => null
		]);

		return $response['result'];
	}
	
	/**
	 * Checking on error
	 * @param array $response
	 * @return array
	 */
	private function checkOnError($response) {
		if(isset($response['result'])) {
			return $response['result'];
		} elseif(isset ($response['error'])) {
			return $response['error'];
		}
	}

		/**
	 * Get all hosts
	 * @return array
	 */
	public function getHostsList($objects, $options, $filters) {
		$objects = $this->arrayCheck($objects);
		$response = $this->sendRequest([
			'jsonrpc' => '2.0',
			'method' => 'host.get',
			'params' => [
				'output' => 'extend'
			],
			'id' => $this->id,
			'auth' => $this->auth()
		]);

		return $this->checkOnError($response);
	}
	
	/**
	 * Get groups for single host
	 * @param integer/array $hostid
	 * @return array
	 */
	public function getHostGroups($objects, $options, $filters) {
		$objects = $this->arrayCheck($objects);
		
		$response = $this->sendRequest([
			'jsonrpc' => '2.0',
			'method' => 'host.get',
			'params' => [
				'output' => [
					$objects
				],
				'selectGroups' => 'extend'
			],
			'id' => $this->id,
			'auth' => $this->auth()
		]);
		
		return $this->checkOnError($response);
		
	}
	
	/**
	 * Get all host groups in zabbix server
	 * @return array
	 */
	public function getHostgroup($objects, $options, $filters) {
		$objects = $this->arrayCheck($objects);
		
		$response = $this->sendRequest([
			'jsonrpc' => '2.0',
			'method' => 'hostgroup.get',
			'params' => [
				'output' => 'extend'
			],
			'id' => $this->id,
			'auth' => $this->auth()
		]);
		
		return $this->checkOnError($response);
		
	}
	
	/**
	 * Get application by host id
	 * @param integer/array $hostid
	 * @return array
	 */
	public function getServiceGroups($objects, $options, $filters) {
		$objects = $this->arrayCheck($objects);
		
		$response = $this->sendRequest([
			'jsonrpc' => '2.0',
			'method' => 'application.get',
			'params' => [
				'output' => 'extend',
				'hostids' => array_keys($objects)
			],
			'id' => $this->id,
			'auth' => $this->auth()
		]);
		
		return $this->checkOnError($response);
		
	}
	
	/**
	 * Get all triggers with priority sorting
	 * @return array
	 */
	private function getServices($name1Pattern = '') {
		$response = $this->sendRequest([
			'jsonrpc' => '2.0',
			'method' => 'trigger.get',
			'params' => [
				'output' => [
					'triggerid',
					'description',
					'priority',
					'lastchange'
				],
				'filter' => [
					'host' => $name1Pattern
				],
				'sortfield' => 'priority',
				'sortorder' => 'DESC',
				'monitored' => '1', 
				'expandDescription' => '1'
			],
			'id' => $this->id,
			'auth' => $this->auth()
		]);

		return $this->checkOnError($response);
		
	}
	
	/**
	 * Get triggers state for host by host id
	 * @param string/array $objects
	 * @return array
	 */
	public function getServiceState($objects, $options, $filters) {
		$objects = $this->arrayCheck($objects);

		$ret = [];

		foreach (array_keys($objects) as $key) {
			if (preg_match('/~~\d+: /', $key)) {
				$response = $this->sendRequest([
					'jsonrpc' => '2.0',
					'method' => 'trigger.get',
					'params' => [
						'output' => 'extend',
						'triggerids' => preg_replace('/(.+)~~(\d+): .+/', '$2', $key),
						'filter' => [
							'host' => preg_replace('/(.+)~~(\d+): .+/', '$1', $key)
						],
						'selectFunctions' => 'extend',
						'sortfield' => 'priority',
						'sortorder' => 'DESC',
						'monitored' => '1',
						'expandDescription' => '1',
					],
					'id' => $this->id,
					'auth' => $this->auth()
				]);
			} else {
				$response = $this->sendRequest([
					'jsonrpc' => '2.0',
					'method' => 'trigger.get',
					'params' => [
						'output' => 'extend',
						'filter' => [
							'host' => $key
						],
						'selectFunctions' => 'extend',
						'sortfield' => 'priority',
						'sortorder' => 'DESC',
						'monitored' => '1',
						'expandDescription' => '1',
						'min_severity' => '3',
						'only_true' => '1'
					],
					'id' => $this->id,
					'auth' => $this->auth()
				]);
			}
			$resp[$key] = $response['result'];
		}

		foreach (array_keys($resp) as $id) {
			if (preg_match('/~~/', $id)) {
				// Single service of the host
				$temp_status = [];$state_descr = "";
				$temp_status[$resp[$id][0]['state']][$resp[$id][0]['value']] = 1;
	
				if (isset($temp_status[0][0])) { $state = OK;		$state_descr = "Service is OK";			}
				if (isset($temp_status[0][1])) { $state = CRITICAL;	$state_descr = "Service is in Problem state";	}
				if (isset($temp_status[1][0])) { $state = UNKNOWN;	$state_descr = "Service state unknown";		}
				if (isset($temp_status[1][1])) { $state = UNKNOWN;	$state_descr = "Service state unknown";		}

				$ret[$id] = [$state,$state_descr,'ack','downtime',0,1,1,1,null,null,$resp[$id][0]['lastchange'],$resp[$id][0]['lastchange'],$resp[$id][0]['error'],$resp[$id][0]['description'],$resp[$id][0]['description'],null,$resp[$id][0]['comments'],$resp[$id][0]['expression'],null,null,null,null,null, preg_replace('/[^0-9a-z\-: \/_#\.]/iu', '', preg_replace('/%/', 'prc', $resp[$id][0]['triggerid'].': '.$resp[$id][0]['description']))];
			} else {
				// All services of the host
				foreach (array_keys($resp[$id]) as $trig) {
					$temp_status = [];$state_descr = "";
					$temp_status[$resp[$id][$trig]['state']][$resp[$id][$trig]['value']] = 1;

					if (isset($temp_status[0][0])) { $state = OK;           $state_descr = "Service is OK";                 }
					if (isset($temp_status[0][1])) { $state = CRITICAL;         $state_descr = "Service is in Problem state";   }
					if (isset($temp_status[1][0])) { $state = UNKNOWN;      $state_descr = "Service state unknown";         }
					if (isset($temp_status[1][1])) { $state = UNKNOWN;      $state_descr = "Service state unknown";         }

					$ret[$id][] = [$state,$state_descr,0,0,0,1,1,1,null,null,$resp[$id][$trig]['lastchange'],$resp[$id][$trig]['lastchange'],$resp[$id][$trig]['error'],$resp[$id][$trig]['description'],$resp[$id][$trig]['description'],'localhost',$resp[$id][$trig]['comments'],$resp[$id][$trig]['expression'],null,null,null,null,null, preg_replace('/[^0-9a-z\-: \/_#\.]/iu', '', preg_replace('/%/', 'prc', $resp[$id][$trig]['triggerid'].': '.$resp[$id][$trig]['description']))];
				}
			}
		}

		return $ret;
	}
	
	/**
	 * Static function which returns the backend specific configuration options
	 * and defines the default values for the options
	 */
	public static function getValidConfig() {
		return self::$validConfig;
	}

	/**
	 * Used in WUI forms to populate the object lists when adding or modifying
	 * objects in WUI.
	 */
	public function getObjects($type, $name1Pattern = '', $name2Pattern = '') {

		$result[] = "";

		if($type == 'host'):
			foreach ($this->getHostsList("", "", "") as $key => $value) {
				$result[$key] = [
			'name1' => $value['name'],
					'name2' => null
				];
			}
		elseif($type == 'hostgroup'):
			foreach ($this->getHostGroup("", "", "") as $key => $value) {
				$result[] = [
//                    'name1' => $value['groupid'],
			'name1' => $value['name'],
					'name2' => $value['name']
				];
			}
		elseif($type == 'servicegroup'):
			foreach ($this->ServiceGroups("", "", "") as $key => $value) {
				$result[] = [
//                    'name1' => $value['applicationid'],
			'name1' => $value['name'],
					'name2' => $value['name']
				];
			}
		elseif($type == 'service'):
			foreach ($this->getServices($name1Pattern) as $key => $value) {
				$result[$key] = [
					'name1' => preg_replace('/[^0-9a-z\-: \/_#\.]/iu', '', preg_replace('/%/', 'prc', $value['triggerid'].': '.$value['description'])),
					'name2' => preg_replace('/[^0-9a-z\-: \/_#\.]/iu', '', preg_replace('/%/', 'prc', $value['triggerid'].': '.$value['description']))
				];
			}
		endif;

		return $result;
	}

	/**
	 * Returns the state with detailed information of a list of hosts. Using the
	 * given objects and filters.
	 */
	public function getHostState($objects, $options, $filters) {
	$state = "";
	$state_descr = "";
	$ret = [];
		$response = $this->sendRequest([
			'jsonrpc' => '2.0',
			'method' => 'host.get',
			'params' => [
				'output' => ['hostid','host','status','snmp_available','available'],
		'filter' => [
			'host' => array_keys($objects)
		]
			],
			'id' => $this->id,
			'auth' => $this->auth()
		]);

	$response = $this->checkOnError($response);
	/* From Zabbix API: 
		status - check whether host is monitored (0 yes, 1 no)
		snmp_available - check SNMP availability (0 unknown, 1 ok, 2 problem)
		available - check Zabbix agent availability (0 unknown, 1 ok, 2 problem)
	   So we need to have some status translation matrix here
	*/

	foreach (array_keys($response) as $hostid) {
		$temp_status = [];$state_descr = "";

		$temp_status[$response[$hostid]['status']][$response[$hostid]['snmp_available']][$response[$hostid]['available']] = 1;

		if (isset($temp_status[0][0][0])) { $state = UNKNOWN; 	$state_descr = "Not monitored by SNMP or Agent"; 	}
		if (isset($temp_status[0][0][1])) { $state = OK; 	$state_descr = "Agent is OK";				}
		if (isset($temp_status[0][0][2])) { $state = DOWN; 	$state_descr = "Agent is DOWN";				}
		if (isset($temp_status[0][1][0])) { $state = OK; 	$state_descr = "SNMP is OK";				}
		if (isset($temp_status[0][1][1])) { $state = OK; 	$state_descr = "SNMP is OK, Agent is OK";		}
		if (isset($temp_status[0][1][2])) { $state = OK; 	$state_descr = "SNMP is OK, Agent is DOWN";		}
		if (isset($temp_status[0][2][0])) { $state = DOWN; 	$state_descr = "SNMP is DOWN";				}
		if (isset($temp_status[0][2][1])) { $state = OK; 	$state_descr = "SNMP is DOWN, Agent is OK";		}
		if (isset($temp_status[0][2][2])) { $state = DOWN; 	$state_descr = "SNMP is DOWN, Agent is DOWN";		}
		if ($response[$hostid]['status'] == 1) { $state = UNKNOWN; $state_descr = "Host not monitored";	} # Host not monitored

		$ret[$response[$hostid]['host']] = [$state,$state_descr,0,0,0,null,null,null,null,null,null,null,null,$response[$hostid]['host'],$response[$hostid]['host'],null,null,null,null,null,null,null,null];
	}

		return $ret;
	}

	/**
	 * Returns the service state counts for a list of hosts. Using
	 * the given objects and filters.
	 */
	public function getHostMemberCounts($objects, $options, $filters) {
		return [];
	}

	/**
	 * Returns the host and service state counts for a list of hostgroups. Using
	 * the given objects and filters.
	 */
	public function getHostgroupStateCounts($objects, $options, $filters) {
		return [];
	}

	/**
	 * Returns the service state counts for a list of servicegroups. Using
	 * the given objects and filters.
	 */
	public function getServicegroupStateCounts($objects, $options, $filters) {
		return [];
	}

	/**
	 * Returns a list of host names which have no parent defined.
	 */
	public function getHostNamesWithNoParent() {
		return [];
	}

	/**
	 * Returns a list of host names which are direct childs of the given host
	 */
	public function getDirectChildNamesByHostName($hostName) {
		return [];
	}

	/**
	 * Returns a list of host names which are direct parents of the given host
	 */
	public function getDirectParentNamesByHostName($hostName) {
		return [];
	}

}

