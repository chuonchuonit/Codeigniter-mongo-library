<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

/**
 * CodeIgniter MongoDB Active Record Library
 *
 * A library to interface with the NoSQL database MongoDB. For more information see http://www.mongodb.org
 *
 * @package CodeIgniter
 * @author gyuha
 * @license http://www.opensource.org/licenses/mit-license.php
 * @link https://github.com/gyuha/Codeigniter-mongo-library
 * @version Version 0.1
 * forked from intekhabrizvi/Codeigniter-mongo-library
 */

Class Mongo_db{

	private $CI;
	private $manager;
	private $collection;
	private $write_concerns;
	private $read_preference;
	private $config = array();
	private $param = array();
	private $activate;

	// config
	private $hostname;
	private $port;
	private $database;
	private $username;
	private $password;
	private $debug;

	private $selects = array();
	private $updates = array();
	private $wheres	= array();
	private $limit	= null;
	private $offset	= 0;
	private $sorts	= array();
	private $return_as = 'array';
	public $benchmark = array();

	/**
	 * --------------------------------------------------------------------------------
	 * Class Constructor
	 * --------------------------------------------------------------------------------
	 *
	 * Automatically check if the Mongo PECL extension has been installed/enabled.
	 * Get Access to all CodeIgniter available resources.
	 * Load mongodb config file from application/config folder.
	 * Prepare the connection variables and establish a connection to the MongoDB.
	 * Try to connect on MongoDB server.
	 */

	function __construct($param)
	{
		if ( ! class_exists('MongoDB\Driver\Manager') && ! class_exists('MongoDB\Collection') ) {
			show_error("The MongoDB PECL extension has not been installed or enabled", 500);
		}
		$this->CI =& get_instance();
		$this->CI->load->config('mongo_db');
		$this->config = $this->CI->config->item('mongo_db');
		$this->param = $param;
		$this->connect();
	}

	/**
	 * --------------------------------------------------------------------------------
	 * Prepare configuration for mongoDB connection
	 * --------------------------------------------------------------------------------
	 *
	 * Validate group name or autoload default group name from config file.
	 * Validate all the properties present in config file of the group.
	 */
	private function prepare()
	{
		if(is_array($this->param) && count($this->param) > 0 && isset($this->param['activate']) == TRUE) {
			$this->activate = $this->param['activate'];
		} else if(isset($this->config['active']) && !empty($this->config['active'])) {
			$this->activate = $this->config['active'];
		}else {
			show_error("MongoDB configuration is missing.", 500);
		}

		if(isset($this->config[$this->activate]) == TRUE) {
			if(empty($this->config[$this->activate]['hostname'])) {
				show_error("Hostname missing from mongodb config group : {$this->activate}", 500);
			}
			else {
				$this->hostname = trim($this->config[$this->activate]['hostname']);
			}

			if(empty($this->config[$this->activate]['port'])) {
				show_error("Port number missing from mongodb config group : {$this->activate}", 500);
			} else {
				$this->port = trim($this->config[$this->activate]['port']);
			}

			if(empty($this->config[$this->activate]['username'])) {
				show_error("Username missing from mongodb config group : {$this->activate}", 500);
			} else {
				$this->username = trim($this->config[$this->activate]['username']);
			}

			if(empty($this->config[$this->activate]['password'])) {
				show_error("Password missing from mongodb config group : {$this->activate}", 500);
			} else {
				$this->password = trim($this->config[$this->activate]['password']);
			}

			if(empty($this->config[$this->activate]['database'])) {
				show_error("Database name missing from mongodb config group : {$this->activate}", 500);
			} else {
				$this->database = trim($this->config[$this->activate]['database']);
			}

			if(empty($this->config[$this->activate]['db_debug'])) {
				$this->debug = FALSE;
			} else {
				$this->debug = $this->config[$this->activate]['db_debug'];
			}

			if(empty($this->config[$this->activate]['return_as'])) {
				$this->return_as = 'array';
			} else {
				$this->return_as = $this->config[$this->activate]['return_as'];
			}

			if(empty($this->config[$this->activate]['write_concern'])) {
				$this->write_concerns = new MongoDB\Driver\WriteConcern(
					MongoDB\Driver\WriteConcern::MAJORITY,
					1000
				);
			}else{
				$this->write_concerns = new MongoDB\Driver\WriteConcern(
					$this->config[$this->activate]['write_concern'],
					$this->config[$this->activate]['write_timeout']
				);
			}

			if(empty($this->config[$this->activate]['read_preference'])) {
				$this->read_preference = new MongoDB\Driver\ReadPreference(
					MongoDB\Driver\ReadPreference::RP_PRIMARY
				);
			}else{
				$this->read_preference = new MongoDB\Driver\ReadPreference(
					$this->config[$this->activate]['read_preference'],
					$this->config[$this->activate]['read_preference_tags']
				);
			}
		}else{
			show_error("mongodb config group :  <strong>{$this->activate}</strong> does not exist.", 500);
		}
	}

	/**
	 * --------------------------------------------------------------------------------
	 * Connect to MongoDB Database
	 * --------------------------------------------------------------------------------
	 *
	 * Connect to mongoDB database or throw exception with the error message.
	 */
	private function connect()
	{
		$this->prepare();
		try {
			$dns = "mongodb://{$this->username}:{$this->password}@{$this->hostname}:{$this->port}";
			$this->manager = new MongoDB\Driver\Manager($dns);
		} catch (MongoDB\Driver\Exception\ConnectionException $e) {
			if(isset($this->debug) == TRUE && $this->debug == TRUE)
			{
				show_error("Unable to connect to MongoDB: {$e->getMessage()}", 500);
			}
			else
			{
				show_error("Unable to connect to MongoDB", 500);
			}
		}
	}

	/**
	 * --------------------------------------------------------------------------------
	 * //! Insert
	 * --------------------------------------------------------------------------------
	 *
	 * Insert a new document into the passed collection
	 *
	 * @usage : $this->mongo_db->insert('foo', $data = array());
	 */
	public function insert($collection = "", $insert = array())
	{
		if (empty($collection)) {
			show_error("No Mongo collection selected to insert into", 500);
		}

		if (!is_array($insert) || count($insert) == 0) {
			show_error("Nothing to insert into Mongo collection or insert is not an array", 500);
		}

		$bulk = new MongoDB\Driver\BulkWrite;
		$bulk->insert($insert);
		try {
			$result = $this->manager->executeBulkWrite($this->activate.".".$collection,
				$bulk);
			// TODO : 현재 write_concerns를 넣으면 오류가 발생 함. 패치 후 적용 검토
			// $result = $this->manager->executeBulkWrite($this->activate.".".$collection,
			// 									$bulk,
			// 									$this->write_concerns);
		} catch (MongoDB\Driver\Exception\Exception $e) {
			if(isset($this->debug) == TRUE && $this->debug == TRUE)
			{
				show_error("Insert of data into MongoDB failed: {$e->getMessage()}");
			}else{
				show_error("Insert of data into MongoDB failed", 500);
			}
		}
		return $result->getInsertedCount();
	}

	/**
	 * --------------------------------------------------------------------------------
	 * Batch Insert
	 * --------------------------------------------------------------------------------
	 *
	 * Insert a multiple document into the collection
	 *
	 * @usage : $this->mongo_db->batch_insert('foo', $data = array());
	 */
	public function batch_insert($collection = "", $insert = array())
	{
		if (empty($collection))
		{
			show_error("No Mongo collection selected to insert into", 500);
		}
		if (count($insert) == 0 || !is_array($insert))
		{
			show_error("Nothing to insert into Mongo collection or insert is not an array", 500);
		}

		$bulk = new MongoDB\Driver\BulkWrite;
		foreach ($insert as $i) {
			$bulk->insert($i);
		}

		try
		{
			$result = $this->manager->executeBulkWrite($this->activate.".".$collection,
				$bulk);
			// TODO : 현재 write_concerns를 넣으면 오류가 발생 함. 패치 후 적용 검토
			// $result = $this->manager->executeBulkWrite($this->activate.".".$collection,
			// 									$bulk,
			// 									$this->write_concerns);
		} catch (MongoDB\Driver\Exception\Exception $e) {
			if(isset($this->debug) == TRUE && $this->debug == TRUE)
			{
				show_error("Insert of data into MongoDB failed: {$e->getMessage()}");
			}else{
				show_error("Insert of data into MongoDB failed", 500);
			}
		}
		return $result->getInsertedCount();
	}

	/**
	 * --------------------------------------------------------------------------------
	 * //! Select
	 * --------------------------------------------------------------------------------
	 *
	 * Determine which fields to include OR which to exclude during the query process.
	 * If you want to only choose fields to exclude, leave $includes an empty array().
	 *
	 * @usage:
	 * $this->mongo_db->select(array('foo', 'bar'))->get('foobar');
	 *    OR
	 * $this->mongo_db->select('foo, bar'))->get('foobar');
	 */
	public function select($includes = array())
	{
		if ( ! is_array($includes))
		{
			$incs = explode(",","$includes");
			foreach($incs as $i) {
				$this->selects[trim($i)] = 1;
			}
			return ($this);
		}

		if ( ! empty($includes))
		{
			foreach ($includes as $col)
			{
				$this->selects[$col] = 1;
			}
		}

		return ($this);
	}

	/**
	 * --------------------------------------------------------------------------------
	 * //! Where
	 * --------------------------------------------------------------------------------
	 *
	 * Get the documents based on these search parameters. The $wheres array should
	 * be an associative array with the field as the key and the value as the search
	 * criteria.
	 *
	 * @usage : $this->mongo_db->where(array('foo' => 'bar'))->get('foobar');
	 */
	public function where($wheres, $value = null)
	{
		if (is_array($wheres))
		{
			foreach ($wheres as $wh => $val)
			{
				$this->wheres[$wh] = $val;
			}
		}
		else
		{
			$this->wheres[$wheres] = $value;
		}
		return $this;
	}

	/**
	 * --------------------------------------------------------------------------------
	 * or where
	 * --------------------------------------------------------------------------------
	 *
	 * Get the documents where the value of a $field may be something else
	 *
	 * @usage : $this->mongo_db->or_where(array('foo'=>'bar', 'bar'=>'foo'))->get('foobar');
	 */
	public function or_where($wheres = array())
	{
		if (is_array($wheres) && count($wheres) > 0) {
			if ( ! isset($this->wheres['$or']) || ! is_array($this->wheres['$or'])) {
				$this->wheres['$or'] = array();
			}

			foreach ($this->wheres as $k => $v) {
				if($k[0] != '$') {
					$this->wheres['$or'][] = array($k=>$v);
					unset($this->wheres[$k]);
				}
			}

			foreach ($wheres as $k => $v)
			{
				$this->wheres['$or'][] = array($k=>$v);
			}
			return ($this);
		}
		else
		{
			show_error("Where value should be an array.", 500);
		}
	}

	/**
	 * --------------------------------------------------------------------------------
	 * Where in
	 * --------------------------------------------------------------------------------
	 *
	 * Get the documents where the value of a $field is in a given $in array().
	 *
	 * @usage : $this->mongo_db->where_in('foo', array('bar', 'zoo', 'blah'))->get('foobar');
	 */
	public function where_in($field = "", $in = array())
	{
		if (empty($field))
		{
			show_error("Mongo field is require to perform where in query.", 500);
		}

		if (is_array($in) && count($in) > 0)
		{
			$this->_w($field);
			$this->wheres[$field]['$in'] = $in;
			return ($this);
		}
		else
		{
			show_error("in value should be an array.", 500);
		}
	}

	/**
	 * --------------------------------------------------------------------------------
	 * Where in all
	 * --------------------------------------------------------------------------------
	 *
	 * Get the documents where the value of a $field is in all of a given $in array().
	 *
	 * @usage : $this->mongo_db->where_in_all('foo', array('bar', 'zoo', 'blah'))->get('foobar');
	 */
	public function where_in_all($field = "", $in = array())
	{
		if (empty($field))
		{
			show_error("Mongo field is require to perform where all in query.", 500);
		}

		if (is_array($in) && count($in) > 0)
		{
			$this->_w($field);
			$this->wheres[$field]['$all'] = $in;
			return ($this);
		}
		else
		{
			show_error("in value should be an array.", 500);
		}
	}

	/**
	 * --------------------------------------------------------------------------------
	 * Where not in
	 * --------------------------------------------------------------------------------
	 *
	 * Get the documents where the value of a $field is not in a given $in array().
	 *
	 * @usage : $this->mongo_db->where_not_in('foo', array('bar', 'zoo', 'blah'))->get('foobar');
	 */
	public function where_not_in($field = "", $in = array())
	{
		if (empty($field))
		{
			show_error("Mongo field is require to perform where not in query.", 500);
		}

		if (is_array($in) && count($in) > 0)
		{
			$this->_w($field);
			$this->wheres[$field]['$nin'] = $in;
			return ($this);
		}
		else
		{
			show_error("in value should be an array.", 500);
		}
	}

	/**
	 * --------------------------------------------------------------------------------
	 * Where greater than
	 * --------------------------------------------------------------------------------
	 *
	 * Get the documents where the value of a $field is greater than $x
	 *
	 * @usage : $this->mongo_db->where_gt('foo', 20);
	 */
	public function where_gt($field = "", $x)
	{
		if (!isset($field))
		{
			show_error("Mongo field is require to perform greater then query.", 500);
		}

		if (!isset($x))
		{
			show_error("Mongo field's value is require to perform greater then query.", 500);
		}

		$this->_w($field);
		$this->wheres[$field]['$gt'] = $x;
		return ($this);
	}

	/**
	 * --------------------------------------------------------------------------------
	 * Where greater than or equal to
	 * --------------------------------------------------------------------------------
	 *
	 * Get the documents where the value of a $field is greater than or equal to $x
	 *
	 * @usage : $this->mongo_db->where_gte('foo', 20);
	 */
	public function where_gte($field = "", $x)
	{
		if (!isset($field))
		{
			show_error("Mongo field is require to perform greater then or equal query.", 500);
		}

		if (!isset($x))
		{
			show_error("Mongo field's value is require to perform greater then or equal query.", 500);
		}

		$this->_w($field);
		$this->wheres[$field]['$gte'] = $x;
		return($this);
	}

	/**
	 * --------------------------------------------------------------------------------
	 * Where less than
	 * --------------------------------------------------------------------------------
	 *
	 * Get the documents where the value of a $field is less than $x
	 *
	 * @usage : $this->mongo_db->where_lt('foo', 20);
	 */
	public function where_lt($field = "", $x)
	{
		if (!isset($field))
		{
			show_error("Mongo field is require to perform less then query.", 500);
		}

		if (!isset($x))
		{
			show_error("Mongo field's value is require to perform less then query.", 500);
		}

		$this->_w($field);
		$this->wheres[$field] = '$lt:'.$x;
		return($this);
	}

	/**
	 * --------------------------------------------------------------------------------
	 * Where less than or equal to
	 * --------------------------------------------------------------------------------
	 *
	 * Get the documents where the value of a $field is less than or equal to $x
	 *
	 * @usage : $this->mongo_db->where_lte('foo', 20);
	 */
	public function where_lte($field = "", $x)
	{
		if (!isset($field))
		{
			show_error("Mongo field is require to perform less then or equal to query.", 500);
		}

		if (!isset($x))
		{
			show_error("Mongo field's value is require to perform less then or equal to query.", 500);
		}

		$this->_w($field);
		$this->wheres[$field]['$lte'] = $x;
		return ($this);
	}

	/**
	 * --------------------------------------------------------------------------------
	 * Where between
	 * --------------------------------------------------------------------------------
	 *
	 * Get the documents where the value of a $field is between $x and $y
	 *
	 * @usage : $this->mongo_db->where_between('foo', 20, 30);
	 */
	public function where_between($field = "", $x, $y)
	{
		if (!isset($field))
		{
			show_error("Mongo field is require to perform greater then or equal to query.", 500);
		}

		if (!isset($x))
		{
			show_error("Mongo field's start value is require to perform greater then or equal to query.", 500);
		}

		if (!isset($y))
		{
			show_error("Mongo field's end value is require to perform greater then or equal to query.", 500);
		}

		$this->_w($field);
		$this->wheres[$field]['$gte'] = $x;
		$this->wheres[$field]['$lte'] = $y;
		return ($this);
	}

	/**
	 * --------------------------------------------------------------------------------
	 * Where between and but not equal to
	 * --------------------------------------------------------------------------------
	 *
	 * Get the documents where the value of a $field is between but not equal to $x and $y
	 *
	 * @usage : $this->mongo_db->where_between_ne('foo', 20, 30);
	 */
	public function where_between_ne($field = "", $x, $y)
	{
		if (!isset($field))
		{
			show_error("Mongo field is require to perform between and but not equal to query.", 500);
		}

		if (!isset($x))
		{
			show_error("Mongo field's start value is require to perform between and but not equal to query.", 500);
		}

		if (!isset($y))
		{
			show_error("Mongo field's end value is require to perform between and but not equal to query.", 500);
		}

		$this->_w($field);
		$this->wheres[$field]['$gt'] = $x;
		$this->wheres[$field]['$lt'] = $y;
		return ($this);
	}

	/**
	 * --------------------------------------------------------------------------------
	 * Where not equal
	 * --------------------------------------------------------------------------------
	 *
	 * Get the documents where the value of a $field is not equal to $x
	 *
	 * @usage : $this->mongo_db->where_ne('foo', 1)->get('foobar');
	 */
	public function where_ne($field = '', $x)
	{
		if (!isset($field))
		{
			show_error("Mongo field is require to perform Where not equal to query.", 500);
		}

		if (!isset($x))
		{
			show_error("Mongo field's value is require to perform Where not equal to query.", 500);
		}

		$this->_w($field);
		$this->wheres[$field]['$ne'] = $x;
		return ($this);
	}


	/**
	 * --------------------------------------------------------------------------------
	 * Like
	 * --------------------------------------------------------------------------------
	 *
	 * Get the documents where the (string) value of a $field is like a value. The defaults
	 * allow for a case-insensitive search.
	 *
	 * @param $flags
	 * Allows for the typical regular expression flags:
	 * i = Case insensitivity to match upper and lower cases.
	 * m = For patterns that include anchors (i.e. ^ for the start, $ for the end), match at the beginning or end of each line for strings with multiline values.
	 * x = can contain comments
	 * s = Allows the dot character (i.e. .) to match all characters including newline characters.
	 *
	 *
	 * @usage : $this->mongo_db->like('foo', 'bar', 'si');
	 * @see https://docs.mongodb.org/v3.2/reference/operator/query/regex/
	 * @TODO : 앞뒤 포함한 쿼리에 문제가 많음. 개선 필요..
	 */
	public function like($field = "", $value = "", $options = "i")
	{
		if (empty($field))
		{
			show_error("Mongo field is require to perform like query.", 500);
		}

		if (empty($value))
		{
			show_error("Mongo field's value is require to like query.", 500);
		}

		$field = (string) trim($field);
		$this->_w($field);
		$value = (string) trim($value);
		$value = quotemeta($value);
		$regex = "$value";
		$this->wheres[$field]['$regex'] = $regex;
		$this->wheres[$field]['$options'] = $options;
		return ($this);
	}

	/**
	 * --------------------------------------------------------------------------------
	 * // Get
	 * --------------------------------------------------------------------------------
	 *
	 * Get the documents based upon the passed parameters
	 *
	 * @usage : $this->mongo_db->get('foo');
	 */
	public function get($collection = "")
	{
		if (empty($collection))
		{
			show_error("In order to retrieve documents from MongoDB, a collection name must be passed", 500);
		}



		$query = new MongoDB\Driver\Query($this->_filter(), $this->_option());

		try {
			$cursor = $this->manager->executeQuery($this->activate.".".$collection,
				$query,
				$this->read_preference);
		} catch (MongoDB\Driver\Exception\Exception $e) {
			echo $e->getMessage(), "\n";
		}

		return $cursor->toArray();
	}

	/**
	 * --------------------------------------------------------------------------------
	 * // Get where
	 * --------------------------------------------------------------------------------
	 *
	 * Get the documents based upon the passed parameters
	 *
	 * @usage : $this->mongo_db->get_where('foo', array('bar' => 'something'));
	 */
	public function get_where($collection = "", $where = array())
	{
		if (is_array($where) && count($where) > 0)
		{
			return $this->where($where)
				->get($collection);
		}
		else
		{
			show_error("Nothing passed to perform search or value is empty.", 500);
		}
	}


	/**
	 * --------------------------------------------------------------------------------
	 * Count
	 * --------------------------------------------------------------------------------
	 *
	 * Count the documents based upon the passed parameters
	 *
	 * @usage : $this->mongo_db->count('foo');
	 */
	public function count($collection = "")
	{
		if (empty($collection))
		{
			show_error("In order to retrieve a count of documents from MongoDB, a collection name must be passed", 500);
		}
		$cmd['count'] = $collection;
		if( isset($this->limit)) $cmd['limit'] = $this->limit;
		if( isset($this->offset)) $cmd['offset'] = $this->offset;
		if( isset($this->wheres)) $cmd['query'] = $this->wheres;

		$command = new MongoDB\Driver\Command($cmd);

		try {
			$cursor = $this->manager->executeCommand($this->database, $command);
		} catch (MongoDB\Driver\Exception\Exception $e) {
			echo $e->getMessage(), "\n";
			return false;
		}
		return  $cursor->toArray()[0]->n;
	}

	/**
	 * --------------------------------------------------------------------------------
	 * Set
	 * --------------------------------------------------------------------------------
	 *
	 * Sets a field to a value
	 *
	 * @usage: $this->mongo_db->where(array('blog_id'=>123))->set('posted', 1)->update('blog_posts');
	 * @usage: $this->mongo_db->where(array('blog_id'=>123))->set(array('posted' => 1, 'time' => time()))->update('blog_posts');
	 */
	public function set($fields, $value = NULL)
	{
		$this->_u('$set');
		if (is_string($fields))
		{
			$this->updates['$set'][$fields] = $value;
		}
		elseif (is_array($fields))
		{
			foreach ($fields as $field => $value)
			{
				$this->updates['$set'][$field] = $value;
			}
		}
		return $this;
	}

	/**
	 * --------------------------------------------------------------------------------
	 * Unset
	 * --------------------------------------------------------------------------------
	 *
	 * Unsets a field (or fields)
	 *
	 * @usage: $this->mongo_db->where(array('blog_id'=>123))->unset('posted')->update('blog_posts');
	 * @usage: $this->mongo_db->where(array('blog_id'=>123))->set(array('posted','time'))->update('blog_posts');
	 */
	public function unset_field($fields)
	{
		$this->_u('$unset');
		if (is_string($fields))
		{
			$this->updates['$unset'][$fields] = 1;
		}
		elseif (is_array($fields))
		{
			foreach ($fields as $field)
			{
				$this->updates['$unset'][$field] = 1;
			}
		}
		return $this;
	}

	/**
	 * --------------------------------------------------------------------------------
	 * Add to set
	 * --------------------------------------------------------------------------------
	 *
	 * Adds value to the array only if its not in the array already
	 *
	 * @usage: $this->mongo_db->where(array('blog_id'=>123))->addtoset('tags', 'php')->update('blog_posts');
	 * @usage: $this->mongo_db->where(array('blog_id'=>123))->addtoset('tags', array('php', 'codeigniter', 'mongodb'))->update('blog_posts');
	 */
	public function addtoset($field, $values)
	{
		$this->_u('$addToSet');
		if (is_string($values))
		{
			$this->updates['$addToSet'][$field] = $values;
		}
		elseif (is_array($values))
		{
			$this->updates['$addToSet'][$field] = array('$each' => $values);
		}
		return $this;
	}

	/**
	 * --------------------------------------------------------------------------------
	 * Push
	 * --------------------------------------------------------------------------------
	 *
	 * Pushes values into a field (field must be an array)
	 *
	 * @usage: $this->mongo_db->where(array('blog_id'=>123))->push('comments', array('text'=>'Hello world'))->update('blog_posts');
	 * @usage: $this->mongo_db->where(array('blog_id'=>123))->push(array('comments' => array('text'=>'Hello world')), 'viewed_by' => array('Alex')->update('blog_posts');
	 */
	public function push($fields, $value = array())
	{
		$this->_u('$push');
		if (is_string($fields))
		{
			$this->updates['$push'][$fields] = $value;
		}
		elseif (is_array($fields))
		{
			foreach ($fields as $field => $value)
			{
				$this->updates['$push'][$field] = $value;
			}
		}
		return $this;
	}

	/**
	 * --------------------------------------------------------------------------------
	 * Pop
	 * --------------------------------------------------------------------------------
	 *
	 * Pops the last value from a field (field must be an array)
	 *
	 * @usage: $this->mongo_db->where(array('blog_id'=>123))->pop('comments')->update('blog_posts');
	 * @usage: $this->mongo_db->where(array('blog_id'=>123))->pop(array('comments', 'viewed_by'))->update('blog_posts');
	 */
	public function pop($field)
	{
		$this->_u('$pop');
		if (is_string($field))
		{
			$this->updates['$pop'][$field] = -1;
		}
		elseif (is_array($field))
		{
			foreach ($field as $pop_field)
			{
				$this->updates['$pop'][$pop_field] = -1;
			}
		}
		return $this;
	}

	/**
	 * --------------------------------------------------------------------------------
	 * Pull
	 * --------------------------------------------------------------------------------
	 *
	 * Removes by an array by the value of a field
	 *
	 * @usage: $this->mongo_db->pull('comments', array('comment_id'=>123))->update('blog_posts');
	 */
	public function pull($field = "", $value = array())
	{
		$this->_u('$pull');
		$this->updates['$pull'] = array($field => $value);
		return $this;
	}

	/**
	 * --------------------------------------------------------------------------------
	 * Rename field
	 * --------------------------------------------------------------------------------
	 *
	 * Renames a field
	 *
	 * @usage: $this->mongo_db->where(array('blog_id'=>123))->rename_field('posted_by', 'author')->update('blog_posts');
	 */
	public function rename_field($old, $new)
	{
		$this->_u('$rename');
		$this->updates['$rename'] = array($old => $new);
		return $this;
	}

	/**
	 * --------------------------------------------------------------------------------
	 * Inc
	 * --------------------------------------------------------------------------------
	 *
	 * Increments the value of a field
	 *
	 * @usage: $this->mongo_db->where(array('blog_id'=>123))->inc(array('num_comments' => 1))->update('blog_posts');
	 */
	public function inc($fields = array(), $value = 0)
	{
		$this->_u('$inc');
		if (is_string($fields))
		{
			$this->updates['$inc'][$fields] = $value;
		}
		elseif (is_array($fields))
		{
			foreach ($fields as $field => $value)
			{
				$this->updates['$inc'][$field] = $value;
			}
		}
		return $this;
	}

	/**
	 * --------------------------------------------------------------------------------
	 * Multiple
	 * --------------------------------------------------------------------------------
	 *
	 * Multiple the value of a field
	 *
	 * @usage: $this->mongo_db->where(array('blog_id'=>123))->mul(array('num_comments' => 3))->update('blog_posts');
	 */
	public function mul($fields = array(), $value = 0)
	{
		$this->_u('$mul');
		if (is_string($fields))
		{
			$this->updates['$mul'][$fields] = $value;
		}
		elseif (is_array($fields))
		{
			foreach ($fields as $field => $value)
			{
				$this->updates['$mul'][$field] = $value;
			}
		}
		return $this;
	}

	/**
	 * --------------------------------------------------------------------------------
	 * Maximum
	 * --------------------------------------------------------------------------------
	 *
	 * The $max operator updates the value of the field to a specified value if the specified value is greater than the current value of the field.
	 *
	 * @usage: $this->mongo_db->where(array('blog_id'=>123))->max(array('num_comments' => 3))->update('blog_posts');
	 */
	public function max($fields = array(), $value = 0)
	{
		$this->_u('$max');
		if (is_string($fields))
		{
			$this->updates['$max'][$fields] = $value;
		}
		elseif (is_array($fields))
		{
			foreach ($fields as $field => $value)
			{
				$this->updates['$max'][$field] = $value;
			}
		}
		return $this;
	}

	/**
	 * --------------------------------------------------------------------------------
	 * Minimum
	 * --------------------------------------------------------------------------------
	 *
	 * The $min updates the value of the field to a specified value if the specified value is less than the current value of the field.
	 *
	 * @usage: $this->mongo_db->where(array('blog_id'=>123))->min(array('num_comments' => 3))->update('blog_posts');
	 */
	public function min($fields = array(), $value = 0)
	{
		$this->_u('$min');
		if (is_string($fields))
		{
			$this->updates['$min'][$fields] = $value;
		}
		elseif (is_array($fields))
		{
			foreach ($fields as $field => $value)
			{
				$this->updates['$min'][$field] = $value;
			}
		}
		return $this;
	}

	/**
	 * --------------------------------------------------------------------------------
	 * //! distinct
	 * --------------------------------------------------------------------------------
	 *
	 * Finds the distinct values for a specified field across a single collection
	 *
	 * @usage: $this->mongo_db->distinct('collection', 'field');
	 */
	public function distinct($collection = "", $field="")
	{
		if (empty($collection))
		{
			show_error("No Mongo collection selected for update", 500);
		}

		if (empty($field))
		{
			show_error("Need Collection field information for performing distinct query", 500);
		}

		try
		{
			$documents = $this->db->{$collection}->distinct($field, $this->wheres);
			$this->_clear();
			if ($this->return_as == 'object')
			{
				return (object)$documents;
			}
			else
			{
				return $documents;
			}
		}
		catch (MongoCursorException $e)
		{
			if(isset($this->debug) == TRUE && $this->debug == TRUE)
			{
				show_error("MongoDB Distinct Query Failed: {$e->getMessage()}", 500);
			}
			else
			{
				show_error("MongoDB failed", 500);
			}
		}
	}

	/**
	 * --------------------------------------------------------------------------------
	 * //! Update
	 * --------------------------------------------------------------------------------
	 *
	 * Updates a single document in Mongo
	 *
	 * @usage: $this->mongo_db->update('foo', $data = array());
	 */
	public function update($collection = "", $options = array())
	{
		if (empty($collection))
		{
			show_error("No Mongo collection selected for update", 500);
		}

		if(empty($options)) {
			$options = ["multi" => true];
		}
		$bulk = new MongoDB\Driver\BulkWrite;
		$bulk->update($this->wheres, $this->updates, $options);

		try {
				$result = $this->manager->executeBulkWrite($this->activate.".".$collection,
									$bulk);
		} catch (MongoDB\Driver\Exception\Exception $e) {
			if(isset($this->debug) == TRUE && $this->debug == TRUE)
			{
				show_error("Update of data into MongoDB failed: {$e->getMessage()}", 500);
			}
			else
			{
				show_error("Update of data into MongoDB failed", 500);
			}
		}
		return $result->getMatchedCount();
	}


	/**
	 * --------------------------------------------------------------------------------
	 * //! Delete
	 * --------------------------------------------------------------------------------
	 *
	 * delete document from the passed collection based upon certain criteria
	 *
	 * @usage : $this->mongo_db->delete('foo');
	 */
	public function delete($collection = "")
	{
		if (empty($collection))
		{
			show_error("No Mongo collection selected to delete from", 500);
		}

		$bulk = new MongoDB\Driver\BulkWrite;
		$bulk->delete($this->wheres);
		try {
				$result = $this->manager->executeBulkWrite($this->activate.".".$collection,
									$bulk);
		} catch (MongoDB\Driver\Exception\Exception $e) {
			if(isset($this->debug) == TRUE && $this->debug == TRUE)
			{
				show_error("Delete of data into MongoDB failed: {$e->getMessage()}", 500);
			}
			else
			{
				show_error("Delete of data into MongoDB failed", 500);
			}
		}
		return $result->getDeletedCount();
	}

	/**
	 * --------------------------------------------------------------------------------
	 * Aggregation Operation
	 * --------------------------------------------------------------------------------
	 *
	 * Perform aggregation on mongodb collection
	 *
	 * @usage $this->mongo_db->aggregate('collection', ['_id'=> '$auth_code', 'count' => ['$sum' => '1']]))
	 */
	public function aggregate($collection, $operation)
	{
		if (empty($collection))
		{
			show_error("In order to retreive documents from MongoDB, a collection name must be passed", 500);
		}

		if (empty($operation) && !is_array($operation))
		{
			show_error("Operation must be an array to perform aggregate.", 500);
		}

		$command = new MongoDB\Driver\Command([
			'aggregate' => $collection,
			'pipeline' => [['$group' => $operation]]
		]);
		try {
			$cursor = $this->manager->executeCommand($this->database, $command);
		} catch (MongoDB\Driver\Exception\Exception $e) {
			echo $e->getMessage(), "\n";
			return false;
		}
		$result = $cursor->toArray();
		return $result[0]->result;
	}

	/**
	 * --------------------------------------------------------------------------------
	 * // Order by
	 * --------------------------------------------------------------------------------
	 *
	 * Sort the documents based on the parameters passed. To set values to descending order,
	 * you must pass values of either -1, FALSE, 'desc', or 'DESC', else they will be
	 * set to 1 (ASC).
	 *
	 * @usage : $this->mongo_db->order_by(array('foo' => 'ASC'))->get('foobar');
	 */
	public function order_by($fields = array())
	{
		foreach ($fields as $col => $val)
		{
			if ($val == -1 || $val === FALSE || strtolower($val) == 'desc')
			{
				$this->sorts[$col] = -1;
			}
			else
			{
				$this->sorts[$col] = 1;
			}
		}
		return ($this);
	}

	/**
	 * --------------------------------------------------------------------------------
	 * Mongo Benchmark
	 * --------------------------------------------------------------------------------
	 *
	 * Output all benchmark data for all performed queries.
	 *
	 * @usage : $this->mongo_db->output_benchmark();
	 */
	public function output_benchmark()
	{
		return $this->benchmark;
	}
	/**
	 * --------------------------------------------------------------------------------
	 * // Limit results
	 * --------------------------------------------------------------------------------
	 *
	 * Limit the result set to $x number of documents
	 *
	 * @usage : $this->mongo_db->limit($x);
	 */
	public function limit($x = null)
	{
		if ($x !== NULL && is_numeric($x) && $x >= 1)
		{
			$this->limit = (int) $x;
		}
		return ($this);
	}

	/**
	 * --------------------------------------------------------------------------------
	 * // Offset
	 * --------------------------------------------------------------------------------
	 *
	 * Offset the result set to skip $x number of documents
	 *
	 * @usage : $this->mongo_db->offset($x);
	 */
	public function offset($x = 0)
	{
		if ($x !== NULL && is_numeric($x) && $x >= 1)
		{
			$this->offset = (int) $x;
		}
		return ($this);
	}

	/**
	 * --------------------------------------------------------------------------------
	 * //! Add indexes
	 * --------------------------------------------------------------------------------
	 *
	 * Ensure an index of the keys in a collection with optional parameters. To set values to descending order,
	 * you must pass values of either -1, FALSE, 'desc', or 'DESC', else they will be
	 * set to 1 (ASC).
	 *
	 * @usage : $this->mongo_db->add_index($collection, array('first_name' => 'ASC', 'last_name' => -1), array('unique' => TRUE));
	 */
	public function add_index($collection = "", $keys = array(), $options = array())
	{
		if (empty($collection))
		{
			show_error("No Mongo collection specified to add index to", 500);
		}

		if (empty($keys) || ! is_array($keys))
		{
			show_error("Index could not be created to MongoDB Collection because no keys were specified", 500);
		}

		foreach ($keys as $col => $val)
		{
			if($val == -1 || $val === FALSE || strtolower($val) == 'desc')
			{
				$keys[$col] = -1;
			}
			else
			{
				$keys[$col] = 1;
			}
		}
		try{
			$this->db->{$collection}->createIndex($keys, $options);
			$this->_clear();
			return ($this);
		}
		catch (MongoCursorException $e)
		{
			if(isset($this->debug) == TRUE && $this->debug == TRUE)
			{
				show_error("Creating Index failed : {$e->getMessage()}", 500);
			}
			else
			{
				show_error("Creating Index failed.", 500);
			}
		}
	}

	/**
	 * --------------------------------------------------------------------------------
	 * Remove index
	 * --------------------------------------------------------------------------------
	 *
	 * Remove an index of the keys in a collection. To set values to descending order,
	 * you must pass values of either -1, FALSE, 'desc', or 'DESC', else they will be
	 * set to 1 (ASC).
	 *
	 * @usage : $this->mongo_db->remove_index($collection, array('first_name' => 'ASC', 'last_name' => -1));
	 */
	public function remove_index($collection = "", $keys = array())
	{
		if (empty($collection))
		{
			show_error("No Mongo collection specified to remove index from", 500);
		}

		if (empty($keys) || ! is_array($keys))
		{
			show_error("Index could not be removed from MongoDB Collection because no keys were specified", 500);
		}

		try
		{
			$this->db->{$collection}->deleteIndex($keys, $options);
			$this->_clear();
			return ($this);
		}
		catch (MongoCursorException $e)
		{
			if(isset($this->debug) == TRUE && $this->debug == TRUE)
			{
				show_error("Creating Index failed : {$e->getMessage()}", 500);
			}
			else
			{
				show_error("Creating Index failed.", 500);
			}
		}
	}

	/**
	 * --------------------------------------------------------------------------------
	 * List indexes
	 * --------------------------------------------------------------------------------
	 *
	 * Lists all indexes in a collection.
	 *
	 * @usage : $this->mongo_db->list_indexes($collection);
	 */
	public function list_indexes($collection = "")
	{
		if (empty($collection))
		{
			show_error("No Mongo collection specified to remove all indexes from", 500);
		}
		return ($this->db->{$collection}->getIndexInfo());
	}


	/**
	 * --------------------------------------------------------------------------------
	 * //! Drop collection
	 * --------------------------------------------------------------------------------
	 *
	 * Drop a Mongo collection
	 * @usage: $this->mongo_db->drop_collection('bar');
	 */
	public function drop_collection($col = '')
	{
		if (empty($col))
		{
			show_error('Failed to drop MongoDB collection because collection name is empty', 500);
		}

		$cmd['drop'] = $col;
		$command = new MongoDB\Driver\Command($cmd);

		try {
			$cursor = $this->manager->executeCommand($this->database, $command);
		} catch (MongoDB\Driver\Exception\Exception $e) {
			echo $e->getMessage(), "\n";
			return false;
		}
		$result = $cursor->toArray();
		if(!is_array($result)) {
			return false;
		}
		return $result[0]->ok;
	}

	/**
	 * --------------------------------------------------------------------------------
	 * _clear
	 * --------------------------------------------------------------------------------
	 *
	 * Resets the class variables to default settings
	 */
	private function _clear()
	{
		$this->selects	= array();
		$this->updates	= array();
		$this->wheres	= array();
		$this->limit	= null;
		$this->offset	= 0;
		$this->sorts	= array();
	}

	/**
	 * --------------------------------------------------------------------------------
	 * Where initializer
	 * --------------------------------------------------------------------------------
	 *
	 * Prepares parameters for insertion in $wheres array().
	 */
	private function _w($param)
	{
		if ( ! isset($this->wheres[$param]))
		{
			$this->wheres[ $param ] = array();
		}
	}


	/**
	 * --------------------------------------------------------------------------------
	 * Update initializer
	 * --------------------------------------------------------------------------------
	 *
	 * Prepares parameters for insertion in $updates array().
	 */
	private function _u($method)
	{
		if ( ! isset($this->updates[$method]))
		{
			$this->updates[ $method ] = array();
		}
	}


	private function _filter()
	{
		$filter = array();

		if (isset($this->wheres)) {
			$filter = $this->wheres;
		}
		return $filter;
	}

	private function _option()
	{
		$option = array();
		if (isset($this->selects)) {
			$option["projection"] = $this->selects;
		}

		if (isset($this->sorts)) {
			$option["sort"] = $this->sorts;
		}

		if (isset($this->limit)) {
			$option["limit"] = $this->limit;
		}

		if (isset($this->offset)) {
			$option["skip"] = $this->offset;
		}

		return $option;
	}

}
