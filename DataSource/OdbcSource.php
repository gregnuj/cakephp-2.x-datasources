<?php
/**
 * ODBC for DBO
 *
 * PHP versions 4 and 5
 *
 * CakePHP(tm) : Rapid Development Framework (http://cakephp.org)
 * Copyright 2005-2009, Cake Software Foundation, Inc. (http://cakefoundation.org)
 *
 * Licensed under The MIT License
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright     Copyright 2005-2009, Cake Software Foundation, Inc. (http://cakefoundation.org)
 * @link          http://cakephp.org CakePHP(tm) Project
 * @since         CakePHP Datasources v 0.1
 * @license       MIT License (http://www.opensource.org/licenses/mit-license.php)
 */
App::uses('DboSource', 'Model/Datasource');


class OdbcSource extends DboSource {

	/**
	 * Driver base configuration
	 *
	 * @var array
	 */
	protected $_baseConfig = array(
		'login' => null,
		'password' => null,
		'database' => 'cake',
		'odbcini' => '/etc/odbc.ini',
		'persistent' => true,
	);

	/**
	 * Columns
	 *
	 * @var array
	*/
	public $columns = array(
		'primary_key' => array('name' => 'int(11) DEFAULT NULL auto_increment'),
		'string' => array('type' => 'varchar', 'limit' => '255'),
		'char' => array('type' => 'char', 'limit' => '255'),
		'varchar' => array('type' => 'varchar', 'limit' => '255'),
		'text' => array('type' => 'text'),
		'integer' => array('type' => 'int', 'limit' => '11'),
		'smallint' => array('type' => 'int', 'limit' => '6'),
		'float' => array('type' => 'float'),
		'numeric' => array('type' => 'numeric'),
		'decimal' => array('type' => 'numeric'),
		'datetime' => array('type' => 'datetime', 'format' => 'Y-m-d h:i:s', 'formatter' => 'date'),
		'timestamp' => array('type' => 'datetime', 'format' => 'Y-m-d h:i:s', 'formatter' => 'date'),
		'time' => array('type' => 'time', 'format' => 'h:i:s', 'formatter' => 'date'),
		'date' => array('type' => 'date', 'format' => 'd/m/Y', 'formatter' => 'date'),
		'binary' => array('type' => 'blob'),
		'boolean' => array('type' => 'tinyint', 'limit' => '1')
	);

	/**
	 * Connects to the database using options in the given configuration array.
	 *
	 * @return boolean True if the database could be connected, else false
	*/
	public function connect() {
		putenv("ODBCINI={$this->config['odbcini']}");
		$this->connected = false;

		try {
			$flags = array(
				PDO::ATTR_PERSISTENT => $this->config['persistent'],
				PDO::ATTR_EMULATE_PREPARES => true,
			);
			$this->_connection = new PDO(
				"odbc:{$this->config['database']}",
				$this->config['login'],
				$this->config['password'],
				$flags
			);
			$this->_connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
			$this->connected = true;
		} catch (PDOException $e) {
			throw new MissingConnectionException(array(
				'class' => get_class($this),
				'message' => $e->getMessage()
			));
		}
		return $this->connected;
	}

	/**
	 * Check if the ODBC extension is installed/loaded
	 *
	 * @return boolean
	 */
	public function enabled() {
		return in_array('odbc', PDO::getAvailableDrivers());
	}

	/**
	 * Enter description here...
	 *
	 * @param unknown_type $results
	 */
	public function resultSet($results) {
		$this->map = array();
		$sql = $results->queryString;
		$select = substr($sql, 0, strpos($sql, ' FROM') - strlen($sql));
		$fields = explode(", ", $select);
		$fields[0] = array_pop(explode(' ', $fields[0]));
		foreach ($fields as $key => $value) {
			list($table, $name) = pluginSplit($value, false, 0);
			if (!$table && strpos($name, $this->virtualFieldSeparator) !== false) {
				$name = substr(strrchr($name, " "), 1);

			}
		  	$this->map[$key] = array($table, $name, null);
		}
	}

	/**
 	 * Fetches the next row from the current result set
 	 *
 	 * @return mixed array with results fetched and mapped to column names or false if there is no results left to fetch
 	 */
	public function fetchResult() {
		if ($row = $this->_result->fetch(PDO::FETCH_NUM)) {
			$resultRow = array();
			foreach ($this->map as $col => $meta) {
				list($table, $column, $type) = $meta;
				$resultRow[$table][$column] = $row[$col];
				if ($type === 'boolean' && $row[$col] !== null) {
					$resultRow[$table][$column] = $this->boolean($resultRow[$table][$column]);
				}
			}
			return $resultRow;
		}
		$this->_result->closeCursor();
		return false;
	}
}