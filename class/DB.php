<?php

class DB {

    private $pdo;
    private $db_host;
    private $db_name;
    private $db_user;
    private $db_pass;

    function __construct($config){
        $this->db_host = $config['db_host'];
        $this->db_name = $config['db_name'];
        $this->db_user = $config['db_user'];
        $this->db_pass = $config['db_pass'];
    }

    public function connect() {
        try {
            $this->pdo = new PDO("mysql:host=".$this->db_host.";dbname=".$this->db_name, $this->db_user, $this->db_pass, array(PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES 'UTF8'"));
            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        }
        catch(PDOException $e){
            echo "Connection to database failed: " . $e->getMessage();
        }
    }

    public function disconnect() {
        $this->pdo = null;
    }

    public function insert($table, $data){

        $fields = array();
        $values = array();
        $duplicates = array();
        foreach( $data as $field => $value ) {
            $fields[] = " `$field` ";
            $values[] = ($value === null ? 'null' : " '". addslashes( $value ) . "' ");
            $duplicates[] = ($value === null ? " `$field` = null " : " `$field` = '". addslashes( $value ) . "' ");
        }

        $fields_str = implode( ',', $fields );
        $values_str = implode( ',', $values );

        $sql = "INSERT INTO ".$table." (".$fields_str.") VALUES (".$values_str.")";
        //$sql .= " ON DUPLICATE KEY UPDATE " . implode( ',', $duplicates );

        $query = $this->pdo->prepare($sql);
        $res = $query->execute();

        if($res){
            return $this->pdo->lastInsertId();
        }
        return $res;
    }

    public function update( $table, $data, $where ) {

        $fields = array();
        foreach( $data as $field => $value ) {
            $fields[] = ($value === null ? " $field = null " : " $field = '". addslashes( $value ) . "' ");
        }

        $fields_str = implode( ',', $fields );
        $sql = "UPDATE ".$table." SET ".$fields_str." WHERE ".$where;

        $query = $this->pdo->prepare($sql);
        $res = $query->execute();

        return $res;
    }

    public function select($sql){

        $query = $this->pdo->prepare($sql);
        $query->execute();

        $rows = array();
        while ($row = $query->fetch(PDO::FETCH_ASSOC)){
            $rows[] = $row;
        }
        return $rows;
    }

    public function selectRow($table, $where) {

        $sql = "SELECT * FROM ".$table." WHERE ".$where;

        $query = $this->pdo->prepare($sql);
        $query->execute();

        if($query->rowCount()){
            return $query->fetch(PDO::FETCH_ASSOC);
        }
        return 0;
    }
}