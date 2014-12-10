<?php
/**
 * PDO_OCI for DBO
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

class OraclePdoOci extends DboSource {


	/**
	 * Database keyword used to assign aliases to identifiers.
	 *
	 * @var string
	 */
	public $alias = '';


	/**
	 * Query limit
	 *
	 * @var int
	 * @access protected
	 */
	protected $_limit = -1;

	/**
	 * Query offset
	 *
	 * @var int
	 * @access protected
	 */
	protected $_offset = 0;

	/**
	 * Base configuration settings for MySQL driver
	 *
	 * @var array
	 */
	protected $_baseConfig = array(
		'login' => 'system',
		'password' => '',
		'host' => 'localhost',
		'database' => 'cake',
		'nls_sort' => null,
		'nls_comp' => null,
		'persistent' => true,
	);

	/**
	 * Column definitions
	 *
	 * @var array
	 * @access public
	*/
	public $columns = array(
		'primary_key' => array('name' => ''),
		'string' => array('name' => 'varchar2', 'limit' => '255'),
		'text' => array('name' => 'varchar2'),
		'integer' => array('name' => 'number'),
		'float' => array('name' => 'float'),
		'datetime' => array('name' => 'date', 'format' => 'Y-m-d H:i:s'),
		'timestamp' => array('name' => 'date', 'format' => 'Y-m-d H:i:s'),
		'time' => array('name' => 'date', 'format' => 'Y-m-d H:i:s'),
		'date' => array('name' => 'date', 'format' => 'Y-m-d H:i:s'),
		'binary' => array('name' => 'bytea'),
		'boolean' => array('name' => 'boolean'),
		'number' => array('name' => 'number'),
		'inet' => array('name' => 'inet')
	);

	/**
	 * Connects to the database using options in the given configuration array.
	 *
	 * @return boolean True if the database could be connected, else false
	*/
	public function connect() {
		$this->connected = false;

		try {
			$flags = array(
				PDO::ATTR_PERSISTENT => $this->config['persistent'],
				PDO::ATTR_EMULATE_PREPARES => true,
			);
			$this->_connection = new PDO(
				"oci:dbname=//{$this->config['database']}",
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
	 * Returns an array of the fields in given table name.
	 *
	 * @param Model $model Model object to describe
	 * @return array Fields in table. Keys are name and type
	 */
	public function describe($model) {

		$table = $this->fullTableName($model);
		if (!empty($model->sequence)) {
			$this->_sequenceMap[$table] = $model->sequence;
		} elseif (!empty($model->table)) {
			$this->_sequenceMap[$table] = $model->table . '_seq';
		}


		$cache = parent::describe($model);
		if ($cache != null) {
			return $cache;
		}
		$fields = array();

		$sql = implode(" ", array(
			"SELECT COLUMN_NAME, DATA_TYPE, DATA_LENGTH, NULLABLE, DATA_DEFAULT",
			"FROM ALL_TAB_COLUMNS",
			"WHERE table_name = '" . strtoupper($this->fullTableName($model)) . "'",
		));
		$results = $this->_execute($sql);
		while ($result = $results->fetch(PDO::FETCH_ASSOC)) {
			$name = $result['COLUMN_NAME'];
			$type = $result['DATA_TYPE'];
			$length = $result['DATA_LENGTH'];
			$null = $result['NULLABLE'];
			$default = $result['DATA_DEFAULT'];
			$fields[$name] = compact('type', 'length', 'null', 'default');
		}
		$this->_cacheDescription($model->tablePrefix . $model->table, $fields);
		return $fields;
	}

	/**
	 * Queries the database with given SQL statement, and obtains some metadata about the result
	 * (rows affected, timing, any errors, number of rows in resultset). The query is also logged.
	 * If Configure::read('debug') is set, the log is shown all the time, else it is only shown on errors.
	 *
	 * ### Options
	 *
	 * - log - Whether or not the query should be logged to the memory log.
	 *
	 * @param string $sql SQL statement
	 * @param array $options
	 * @param array $params values to be bound to the query
	 * @return mixed Resource or object representing the result set, or false on failure
	 */
	public function execute($sql, $options = array(), $params = array()) {
		$this->_cursorPosition = 0;
		return parent::execute($sql, $options, $params);
	}

	/**
	 * Fetches the next row from the current result set
	 *
	 * @return mixed array with results fetched and mapped to column names or false if there is no results left to fetch
	 */
	public function fetchResult() {
		if ($this->_offset > 0){
			while ($this->_cursorPosition < $this->_offset){
				$this->_result->fetch(PDO::FETCH_NUM);
				$this->_cursorPosition++;
			}
		}
		if ($this->_limit > 0 && $this->_cursorPosition >= $this->_offset + $this->_limit){
			$this->_result->closeCursor();
			return false;
		}

		if ($row = $this->_result->fetch(PDO::FETCH_NUM)) {
			$this->_cursorPosition++;
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

	/**
	 * Returns number of affected rows in previous database operation. If no previous operation exists,
	 * this returns false.
	 *
	 * @param mixed $source
	 * @return integer Number of affected rows
	 */
	public function lastAffected($source = null) {
		if ($this->hasResult()) {
			$count = $this->_result->rowCount();
			if ($count == 0){
				$query = $this->_result->queryString;
				$parts = preg_split('/(SELECT | SKIP \d+ | FIRST \d+ | FROM | WHERE | ORDER | GROUP )/i', $query, null, PREG_SPLIT_NO_EMPTY | PREG_SPLIT_DELIM_CAPTURE);
				$parts = array_map('trim', $parts);
				foreach($parts as $i => $part){
					if (strtoupper(trim($part)) == 'FROM') $parts[$i - 1] = ' COUNT(*) ';
					if (strtoupper(trim($part)) == 'WHERE') $query = implode(' ', array_slice($parts, 0, $i + 2));
				}
				$count = $this->_execute($query)->fetch(PDO::FETCH_NUM);
				$count = array_shift($count);
			}
			return $count;
		}
		return 0;
	}

	/**
	 * Returns an array of sources (tables) in the database.
	 *
	 * @return array Array of tablenames in the database
	 */
	public function listSources() {
		$cache = parent::listSources();
		if ($cache != null) {
			return $cache;
		}
		$sql = 'SELECT view_name AS name FROM all_views UNION SELECT table_name AS name FROM all_tables';
		$results = $this->_execute($sql);
		$tables = array();
		while ($result = $results->fetch(PDO::FETCH_ASSOC)) {
			array_push($tables, $result['NAME']);
		}
		parent::listSources($tables);
		return $tables;
	}

	/**
	 * Modify a SQL query to limit (and offset) the result set
	 *
	 * @param integer $limit Maximum number of rows to return
	 * @param integer $offset Row to begin returning
	 * @return modified SQL Query
	 * @access public
	 */
	public function limit($limit = -1, $offset = 0) {
		$this->_limit = (int) $limit;
		$this->_offset = (int) $offset;
	}

	/**
	 * Check if the ODBC extension is installed/loaded
	 *
	 * @return boolean
	 */
	public function enabled() {
		return in_array('oci', PDO::getAvailableDrivers());
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
	 * Returns a quoted and escaped string of $data for use in an SQL statement.
	 *
	 * @param string $data String to be prepared for use in an SQL statement
	 * @param string $column The column datatype into which this data will be inserted.
	 * @return string Quoted and escaped data
	 */
	public function value($data, $column = null) {
		if (is_array($data) && !empty($data)) {
			return array_map(
				array(&$this, 'value'),
				$data, array_fill(0, count($data), $column)
			);
		} elseif (is_object($data) && isset($data->type, $data->value)) {
			if ($data->type === 'identifier') {
				return $this->name($data->value);
			} elseif ($data->type === 'expression') {
				return $data->value;
			}
		} elseif (in_array($data, array('{$__cakeID__$}', '{$__cakeForeignKey__$}'), true)) {
			return $data;
		}

		if ($data === null || (is_array($data) && empty($data))) {
			return 'NULL';
		}

		if (empty($column)) {
			$column = $this->introspectType($data);
		}

		// This is the unique part
		switch ($column) {
			case 'date':
				$data = date('Y-m-d H:i:s', strtotime($data));
				$data = "TO_DATE('$data', 'YYYY-MM-DD HH24:MI:SS')";
				break;
			case 'binary':
			case 'integer' :
			case 'float' :
			case 'boolean':
			case 'string':
			case 'text':
			default:
				if ($data === '') {
					return 'NULL';

				} elseif (is_float($data)) {
					return str_replace(',', '.', strval($data));

				} elseif ((is_int($data) || $data === '0') || (
					is_numeric($data) && strpos($data, ',') === false &&
					$data[0] != '0' && strpos($data, 'e') === false)
				) {
					return $data;
				}
				$data = str_replace("'", "''", $data);
				$data = "'$data'";
				return $data;
				break;
		}
		return $data;
	}
}