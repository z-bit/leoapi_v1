<?php

class Preferences
{
    private $props = array();
    private $json = '../Config/preferences.json';
    private static $instance;

    private function __construct()
    {
        //load persistent values
        $this->props = (array) json_decode(file_get_contents(dirname(__FILE__).$this->json));
    }

    public static function getInstance()
    {
        if (empty(self::$instance))
        {
            self::$instance = new Preferences();
        }
        return self::$instance;
    }

    public function setProperty($key, $val)
    {
        //reload persistent values (in case they have been changed somewhere else)
        $this->props = (array) json_decode(file_get_contents(dirname(__FILE__).$this->json));
        //make changes
        $this->props[$key] = $val;
        //save persistent values
        file_put_contents(dirname(__FILE__).$this->json, json_encode($this->props));
    }

    public function getProperty($key)
    {
        return $this->props[$key];
    }
} 