<?php

namespace PostsCollections;

abstract class Facade {
    /**
     * Instance class
     */
    private static $instance;

    public static function init($className) {
        self::$instance = new $className;
    }

    /**
     * Forward statically called method to instantinated object
     */
    public static function __callStatic($method_name, $arguments) {
        return call_user_func_array([self::$instance, $method_name], $arguments);
    }
}