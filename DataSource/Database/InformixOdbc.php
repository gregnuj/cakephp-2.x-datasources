<?php

App::uses('OdbcSource', 'Model/Datasource');

class InformixOdbc extends OdbcSource {

	/**
	 * Table/column starting quote
	 *
	 * @var string
	 */
	public $startQuote = "";

	/**
	 * Table/column end quote
	 *
	 * @var string
	 */
	public $endQuote = "";

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
		'informixdir' => '/opt/IBM/informix',
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

	public $columnType = array(
		0 => 'string',    //CHAR
		1 => 'integer',   //SMALINT
		2 => 'integer',   //INTEGER
		3 => 'float',     //FLOAT
		4 => 'float',     //SMALLFLOAT
		5 => 'float',     //DECIMAL
		6 => 'integer',   //SERIAL
		7 => 'date',      //DATE
		8 => 'float',    //MONEY
		9 => 'string',    //NULL
		10 => 'datetime', //DATETIME
		11 => 'binary',   //BYTE
		12 => 'text',     //TEXT
		13 => 'string',   //VARCHAR
		14 => 'timestamp',//INTERVAL
		15 => 'string',   //CHAR
		16 => 'string',   //NVARCHAR
		17 => 'integer',  //INT8
		18 => 'integer',  //SERIAL8
		19 => 'string',   //SET
		20 => 'string',   //MULITSET
		21 => 'string',   //LIST
		22 => 'string',   //UnamedROW
		40 => 'string',   //Variable-length opaque type,
		4118 => 'string', //NamedROW
	);

	public function connect() {
		putenv("INFORMIXDIR={$this->config['informixdir']}");
		return parent::connect();
	}

	/**
	 * Returns an array of the fields in given table name.
	 *
	 * @param Model $model Model object to describe
	 * @return array Fields in table. Keys are name and type
	 */
	public function describe($model) {
		$cache = DboSource::describe($model);
		if ($cache != null) {
			return $cache;
		}
		$fields = array();

		$sql = implode(" ", array(
			"SELECT c.colname, c.coltype, c.collength",
			"FROM informix.systables  AS t JOIN informix.syscolumns AS c ON t.tabid = c.tabid",
			"WHERE t.tabtype = 'T'",
			"AND t.tabname = '" . $this->fullTableName($model) . "'",
			"ORDER BY t.tabname, c.colno",
		));
		$results = $this->_execute($sql);
		while ($result = $results->fetch(PDO::FETCH_OBJ)) {
			$name = $result->colname;
			$coltype = $result->coltype;
			$type = $this->columnType[$coltype % 256];
			$length = $result->collength;
			$null = $coltype >= 256;
			$fields[$name] = compact('type', 'length', 'null', 'default');
		}
		$this->_cacheDescription($model->tablePrefix . $model->table, $fields);
		return $fields;
	}


	/**
	 * Returns an array of sources (tables) in the database.
	 *
	 * @return array Array of tablenames in the database
	 */
	public function listSources() {
		$cache = DboSource::listSources();
		if ($cache != null) {
			return $cache;
		}
		$sql = 'SELECT Systable.tabname FROM informix.systables as Systable WHERE Systable.tabid >= 100';
		$results = $this->_execute($sql);
		$tables = array();
		while ($result = $results->fetch(PDO::FETCH_OBJ)) {
			array_push($tables, $result->tabname);
		}
		DboSource::listSources($tables);
		return $tables;
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
			if ($count == -1){
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
	 * Returns a limit statement in the correct format for the particular database.
	 *
	 * Informix has not a limit statement, so limits here have been simulated setting flags as needed
	 * to fetch only selected rows.
	 *
	 * @param integer $limit Limit of results returned
	 * @param integer $offset Offset from which to start results
	 * @return string SQL limit/offset statement
	 *
	 */

	public function limit($limit, $offset = null){
		if ($limit){
			if ($offset == null){
				$offset = 0;
			}
			$this->offset = $offset;
			$this->limit = $limit;
			$this->num_record = $offset + 1;
			return "SKIP {$offset} FIRST {$limit}";
		}
		return null;
	}

	/**
	 * Renders a final SQL statement by putting together the component parts in the correct order
	 *
	 * @param string $type type of query being run. e.g select, create, update, delete, schema, alter.
	 * @param array $data Array of data to insert into the query.
	 * @return string Rendered SQL expression to be run.
	 */
	public function renderStatement($type, $data) {
		if (strtolower($type) == 'select'){
			extract($data);
			return trim("SELECT {$limit} {$fields} FROM {$table} {$alias} {$joins} {$conditions} {$group} {$order}" );
		}
		return parent::renderStatement($type, $data);
	}
}