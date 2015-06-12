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
                    '3351', 
                    'vtsutsman', 
                    'DevHack32', 
                    'chrome_expense'
                )
            );
        $this -> dest = new MySQLAdapter();
            $this -> dest -> connect(
                new DatabaseConnectionData(
                    'localhost', 
                    '3306', 
                    'root', 
                    'developer', 
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
    public function getTableDescribe( $tableName );
    public function getStoredProcedures();
    public function getStoredProcedureSQL( $procedureName );
    public function getCountRecords($tableName);
    public function getRecords($tableName, $offset, $limit);

    public function createTable( $tableName, $sql );
    public function createStoredProcedure( $sql );
    public function createRecords($tableName, $table, $records);

    public function setConnection( $connection );
    public function setLink( $link );
}

class MySQLScheme implements DatabaseScheme {

    private $connection;
    private $databaseName;
    private $condition;
    private $link;

    /**
     * MySQLScheme constructor.
     * @param $connection
     */
    public function __construct($connection, $databaseName, $condition)
    {
        $this -> connection = $connection;
        $this -> databaseName = $databaseName;
        $this -> condition = $condition;
        
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

    public function getTableDescribe( $tableName ) {
        $response = mysql_query(
            "DESCRIBE {$this->databaseName}.$tableName", 
            $this->connection
        ) or die('ERR::GET_TABLE_SQL' . mysql_error($this->connection));

        $result = [];
        while ($table = mysql_fetch_object($response) ) {
            array_push($result, $table);
        }

        return $result;
    }

    public function getStoredProcedures()
    {
        $result = array();
        $response = mysql_query(
            "SHOW PROCEDURE STATUS WHERE Db = 'chrome_expense'",
            $this -> connection
        ) or die('ERR::GET_PROCEDURES');

        while( $procedure = mysql_fetch_row($response) ) {
            array_push( $result, $procedure[ 1 ] );
        }

        return $result;
    }

    public function getStoredProcedureSQL($procedureName)
    {
        $response = mysql_query(
            "SHOW CREATE PROCEDURE {$this->databaseName}.$procedureName",
            $this->connection
        ) or die('ERR::GET_PROCEDURE_SQL ' . mysql_error($this->connection));

        $procedure = mysql_fetch_row($response);

        return $procedure[ 2 ];
    }

    public function getCountRecords($tableName) {
        $response = mysql_query(
            "SELECT COUNT(*) AS count FROM $tableName WHERE {$this -> condition}",
            $this->connection
        );
        if (!$response)
            $response = mysql_query(
                "SELECT COUNT(*) AS count FROM $tableName",
                $this->connection
            ) or die('ERR::GET_COUNT');

        $count = mysql_fetch_object($response);

        return $count -> count;
    }

    public function getRecords($tableName, $offset, $limit) {
        $response = mysql_query(
            "SELECT * FROM $tableName WHERE {$this -> condition} LIMIT $offset, $limit",
            $this->connection
        );
        if (!$response)
            $response = mysql_query(
                "SELECT COUNT(*) FROM $tableName LIMIT $offset, $limit",
                $this->connection
            );

        $result = [];
        while ($row = mysql_fetch_row($response) ) {
            array_push($result, $row);
        }

        return $result;
    }

    public function createTable($tableName, $sql)
    {
        $result = mysql_query($sql, $this -> connection);// or die('ERR::CREATEA_TABLE' . mysql_error($this->connection));
        if (!$result)
            throw new Exception('ERR::CREATE_TABLE ' . mysql_error($this->connection));
    }

    public function createStoredProcedure($sql)
    {
        $result = mysql_query($sql, $this -> connection);// or die('ERR::CREATEA_TABLE' . mysql_error($this->connection));
        if (!$result)
            throw new Exception('ERR::CREATE_PROCEDURE ' . mysql_error($this->connection));
    }

    public function createRecords($tableName, $table, $records) {
        $sql = "INSERT INTO $tableName VALUES ";
        foreach ($records as $record) {
            $sql .= '(';
                $i = 0;
                foreach ($record as $val) {
                    if (strstr($table[$i] -> Type, 'char')
                        || strstr($table[$i] -> Type, 'time')
                        || strstr($table[$i] -> Type, 'text')
                    )
                        $sql .= "'" . addslashes($val) . "',";
                    elseif (is_null($val))
                        $sql .= 'NULL,';
                    else
                        $sql .= $val . ',';
                    $i++;
                }
            $sql = substr($sql, 0, strlen($sql) - 1);
            $sql .= "),\n";
        }
        $sql = substr($sql, 0, strlen($sql) - 1);
        $sql .= ';';
        
        fwrite($this->link, $sql);
        fwrite($this->link, "\n\n");

//        $response = mysql_query($sql, $this->connection);
//        if (!$response)
//            throw new Exception('ERR::INSERT ' . mysql_error($this->connection));
    }

    /**
     * @param mixed $connection
     */
    public function setConnection($connection)
    {
        $this->connection = $connection;
    }

    public function setLink($link) {
        $this -> link = $link;
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
        return new MySQLScheme(
            $this->connection,
            $this -> databaseName,
            ' CustomerID = 361 '
        );
    }

    public function setScheme( DatabaseScheme $scheme )
    {
        // Copy Tables
        $newScheme = new MySQLScheme($this->connection, $this -> databaseName, ' CustomerID = 361 ');
//            $createdTables = $newScheme->getTables();

        $tables = $scheme -> getTables();
//
//        $tables = array_diff($tables, $createdTables);
//        foreach ($tables as $tableName) {
//            try {
//                $newScheme -> createTable(
//                    $tableName, 
//                    $scheme -> getCreateTableSQL($tableName)
//                );
//            } catch (Exception $e) {
//                echo $tableName, ' was not created.', $e->getMessage(), "\n";
//                continue;
//            }
//        }

        // Copy Procedures/function
//        $createdProcedures = $newScheme -> getStoredProcedures();
//
//        $procedures = $scheme -> getStoredProcedures();
//        $procedures = array_diff($procedures, $createdProcedures);
//        foreach ($procedures as $procedureName) {
//            try {
//                $newScheme -> createStoredProcedure(
//                    $scheme -> getStoredProcedureSQL( $procedureName )
//                );
//            } catch( Exception $e ) {
//                echo $procedureName, ' was not created. ', $e -> getMessage(), "\n";
//                continue;
//            }
//        }

        //FIXME: move to own class
        foreach ($tables as $tableName) {
            try {
                $f = fopen('/media/vtsutsman/1df3508c-c324-40b6-84d4-f503ea709241/cr/cr_data-' . $tableName . '.sql', 'a+');
                $newScheme->setLink($f);
                $count = $scheme -> getCountRecords($tableName);
                for ($i = 0; $i <= $count; $i += 100) {
                    $newScheme -> createRecords(
                        $tableName, 
                        $scheme -> getTableDescribe($tableName), 
                        $scheme -> getRecords($tableName, $i, 100)
                    );
                    echo 'Inserted ', $tableName, ' ', $i, "/$count\n";
                }
                fclose($f);
            } catch (Exception $e) {
                echo 'Skipped: ', $tableName, ' ', $e -> getMessage(), "\n";
                continue;
            }
        }
    }
}


// Start app
$app = new Application();
    $app -> start();
