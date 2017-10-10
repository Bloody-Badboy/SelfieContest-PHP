<?php

class DB_Connect
{
    private $conn;

    public function connect()
    {
        require_once dirname(__DIR__) . "/config/DB_Config.php";
        $this->conn = new mysqli(DB_HOST, DB_USER, DB_PASSWORD, DB_NAME);

        return $this->conn;
    }
}