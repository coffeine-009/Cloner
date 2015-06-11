<?php
/**
 * @copyright 2014 (c), by Vitaliy Tsutsman
 *
 * @author Vitaliy Tsutsman <vitaliyacm@gmail.com>
 */
set_time_limit(0);
class Application {

    private $src;
    private $dest;

    public function start() {
        $this -> src = new MySQLAdapter();
            $this -> src -> connect(
                new DatabaseConnectionData(
                    '127.0.0.1', 
                    '3350', 
                    'chrome_dev', 
                    'houseP4rty', 
                    'chrome_expense'
                )
            );
        $this -> dest = new MySQLAdapter();
            $this -> dest -> connect(
                new DatabaseConnectionData(
                    'localhost', 
                    '3306', 
                    'chrome_dev', 
                    'houseP4rty', 
                    'chrome_expense'
                )
            );

        $this -> dest -> setScheme($this -> src -> getScheme());
        
        $this -> src -> close();
        $this -> dest -> close();
    }
}

/**
 * Class DatabaseConnectionData
 * Data about connection.
 *
 * @version 1.0
 */
class DatabaseConnectionData {

    private $host;
    private $port;
    private $username;
    private $password;
    private $databaseName;

    /**
     * DatabaseConnectionData constructor.
     * @param $host
     * @param $port
     * @param $username
     * @param $password
     * @param $databaseName
     */
    public function __construct(
        $host, 
        $port, 
        $username, 
        $password, 
        $databaseName
    ) {
        $this->host = $host;
        $this->port = $port;
        $this->username = $username;
        $this->password = $password;
        $this->databaseName = $databaseName;
    }

    /**
     * @return mixed
     */
    public function getHost()
    {
        return $this->host;
    }

    /**
     * @return mixed
     */
    public function getPort()
    {
        return $this->port;
    }

    /**
     * @return mixed
     */
    public function getUsername()
    {
        return $this->username;
    }

    /**
     * @return mixed
     */
    public function getPassword()
    {
        return $this->password;
    }

    /**
     * @return mixed
     */
    public function getDatabaseName()
    {
        return $this->databaseName;
    }
}

// Scheme
interface DatabaseScheme {

    public function getTables();
    public function getCreateTableSQL( $tableName );

    public function createTable( $tableName, $sql );

    public function setConnection( $connection );
}

class MySQLScheme implements DatabaseScheme {

    private $connection;
    private $databaseName;

    /**
     * MySQLScheme constructor.
     * @param $connection
     */
    public function __construct($connection, $databaseName)
    {
        $this->connection = $connection;
        $this -> databaseName = $databaseName;
    }


    public function getTables()
    {
        $result = array();
        $response = mysql_query( 
            "SELECT table_name FROM information_schema.tables WHERE table_schema = '{$this->databaseName}' AND table_type = 'BASE TABLE'  ORDER BY create_time", 
            $this -> connection 
        ) or die("ERR::GET_TABLES");

        while( $table = mysql_fetch_row($response) ) {
            array_push( $result, $table[ 0 ] );
        }

        return $result;
    }

    public function getCreateTableSQL($tableName)
    {
        $response = mysql_query("SHOW CREATE TABLE {$this->databaseName}.$tableName", $this->connection) or die('ERR::GET_TABLE_SQL' . mysql_error($this->connection));

        $table = mysql_fetch_row($response);

        return $table[ 1 ];
    }

    public function createTable($tableName, $sql)
    {
        $result = mysql_query($sql, $this -> connection);// or die('ERR::CREATEA_TABLE' . mysql_error($this->connection));
        if (!$result)
            throw new Exception('ERR::CREATEA_TABLE ' . mysql_error($this->connection));
    }

    /**
     * @param mixed $connection
     */
    public function setConnection($connection)
    {
        $this->connection = $connection;
    }
}

// Adapter
interface DatabaseAdapter {

    /**
     * Connect to database.
     *
     * @param $params DatabaseConnectionData
     */
    public function connect(DatabaseConnectionData $params);
    public function close();

    public function getScheme();
    public function setScheme( DatabaseScheme $scheme );
}

class MySQLAdapter implements DatabaseAdapter {

    private $connection;
    private $databaseName;


    /**
     * Connect to database.
     *
     * @param $params DatabaseConnectionData
     */
    public function connect(DatabaseConnectionData $params)
    {
        $this -> connection = mysql_connect(
            $params -> getHost() . ':' . $params -> getPort(), 
            $params -> getUsername(), 
            $params -> getPassword() 
        ) or die("ERR::CONNECT");
        //TODO: check connection

        mysql_select_db(
            $params -> getDatabaseName(), 
            $this -> connection
        ) or die('ERR::SELECT_DB');

        $this -> databaseName = $params -> getDatabaseName();
    }

    public function close()
    {
        mysql_close( $this -> connection );
    }

    public function getScheme()
    {
        return new MySQLScheme($this->connection, $this -> databaseName);
    }

    public function setScheme( DatabaseScheme $scheme )
    {
        $newScheme = new MySQLScheme($this->connection, $this -> databaseName);
            $createdTables = $newScheme->getTables();

        $tables = $scheme -> getTables();
//        var_dump($tables);die();
        $tables = array_diff($tables, $createdTables);
        foreach ($tables as $tableName) {
            try {
                $newScheme -> createTable(
                    $tableName, 
                    $scheme -> getCreateTableSQL($tableName)
                );
            } catch (Exception $e) {
                echo $tableName, ' was not created.', $e->getMessage(), "\n";
                continue;
            }
        }
    }
}


// Start app
$app = new Application();
    $app -> start();
