<?php  if ( ! defined('BASEPATH')) exit('No direct script access allowed');
/*
| -------------------------------------------------------------------------
| DATABASE CONNECTIVITY SETTINGS
| -------------------------------------------------------------------------
| This file will contain the settings needed to access your Mongo database.
|
|
| ------------------------------------------------------------------------
| EXPLANATION OF VARIABLES
| ------------------------------------------------------------------------
|
|	['hostname'] The hostname of your database server.
|	['username'] The username used to connect to the database
|	['password'] The password used to connect to the database
|	['database'] The name of the database you want to connect to
|	['db_debug'] TRUE/FALSE - Whether database errors should be displayed.
|	['write_concerns'] Default is 1: acknowledge write operations.  ref(http://php.net/manual/en/mongo.writeconcerns.php)
|	['journal'] Default is TRUE : journal flushed to disk. ref(http://php.net/manual/en/mongo.writeconcerns.php)
|	['read_preference'] Set the read preference for this connection. ref (http://php.net/manual/en/mongoclient.setreadpreference.php)
|	['read_preference_tags'] Set the read preference for this connection.  ref (http://php.net/manual/en/mongoclient.setreadpreference.php)
|
| The $config['mongo_db']['active'] variable lets you choose which connection group to
| make active.  By default there is only one group (the 'default' group).
|
*/

$config['mongo_db']['active'] = 'default';

$config['mongo_db']['talklish_log']['no_auth'] = FALSE;
$config['mongo_db']['talklish_log']['hostname'] = '192.168.100.179';
$config['mongo_db']['talklish_log']['port'] = '27017';
$config['mongo_db']['talklish_log']['username'] = 'talklish';
$config['mongo_db']['talklish_log']['password'] = 'dpsjwl';
$config['mongo_db']['talklish_log']['database'] = 'talklish_log';
$config['mongo_db']['talklish_log']['db_debug'] = TRUE;
$config['mongo_db']['talklish_log']['return_as'] = 'array';

$config['mongo_db']['talklish_log']['write_concern'] = "majority";
$config['mongo_db']['talklish_log']['write_timeout'] = 1000;
$config['mongo_db']['talklish_log']['journal'] = TRUE;

$config['mongo_db']['talklish_log']['read_preference'] = NULL;
$config['mongo_db']['talklish_log']['read_preference_tags'] = NULL;



/* End of file database.php */
/* Location: ./application/config/database.php */
