<?php

/**
 * This file is part of the Firstance BPER project.
 *
 * @copyright Firstance srl.
 */

class Database
{
    public ?PDO $conn = null;

    public string $host;
    public string $db;
    public string $user;
    public string $pass;
    public string $port;

    public function __construct(string $host = '', string $db = '', string $user = '', string $pass = '', string $port = '')
    {
        if ('' == $host) {
            $this->host = ENV_DB_HOST;
        }
        if ('' == $db) {
            $this->db = ENV_DB_DATABABE;
        }
        if ('' == $port) {
            $this->port = ENV_DB_PORT;
        }
        if ('' == $user) {
            $this->user = ENV_DB_USER;
        }
        if ('' == $pass) {
            $this->pass = ENV_DB_PASSWORD;
        }
    }

    public function connect_db(): PDO
    {
        $dsn     = "pgsql:host=$this->host;port=$this->port;dbname=$this->db";
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_TIMEOUT            => 3600,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];
        try {
            $this->conn = new PDO($dsn, $this->user, $this->pass, $options);
        } catch (PDOException $e) {
            throw new PDOException($e->getMessage(), (int) $e->getCode());
        }

        if (ENVIRONMENT_NAME === 'development') {
            $this->conn->query('CREATE EXTENSION IF NOT EXISTS plpython3u CASCADE');
        }
        $this->conn->query('CREATE EXTENSION IF NOT EXISTS aws_s3     CASCADE');

        return $this->conn;
    }

    public function getConnection(): PDO
    {
        if (!isset($this->conn) || null == $this->conn) {
            $this->connect_db();
        }

        return $this->conn;
    }
}
