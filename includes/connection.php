<?php
class Database
{
    public static $connection;
    public static function setUpConnection()
    {
        if (!isset(Database::$connection)) {
            // Database::$connection = new mysqli("localhost", "u155757515_test", "*w3MO$*6p&5S", "u155757515_test");
            Database::$connection = new mysqli("127.0.0.1", "root", "RanaDe@10326510", "u155757515_erp_orinsto_live");
        }
    }
    public static function iud($q)
    {
        Database::setUpConnection();
        Database::$connection->query($q);
    }
    public static function search($q)
    {
        Database::setUpConnection();
        $resultset = Database::$connection->query($q);
        return $resultset;
    }

    public static function escape($string)
    {
        Database::setUpConnection();
        return Database::$connection->real_escape_string($string);
    }
}
