<?php

namespace PostsCollections;

abstract class Base {
    
    /**
     * package.json file data. If this file exists
     */
    private $package = [];

    /**
     * Plugin dir path
     */
    protected $path = '';

    public function __construct() {
        // Plugin dir path
        $this->path = trailingslashit(dirname(__FILE__));

        // Load package.json of exists
        $this->load_package_json_if_exists();
    }

    /**
     * If we can find package.json, then load it
     */
    private function load_package_json_if_exists() {
        $f = $this->path.'package.json';

        if (file_exists($f)) {
            $this->package = json_decode(file_get_contents($f), true);
        }
    }

    /**
     * Get value from package.json
     */
    protected function package($field_name) {
        return array_key_exists($field_name, $this->package) ? $this->package[$field_name] : '';
    }

    /**
     * Get package version
     */
    public function ver() {
        return $this->package('version');
    }

    /**
     * Čekojam saglabājamā post post_type
     * Post type tiek čekots pret $this->allowed_post_types
     * Post type tiek ņemts no $_POST
     */
    public function is_allowed_post_type() {
        // Ja nav definēts allowed_post_types, tad return true
        if (!property_exists($this, 'allowed_post_types')) {
            return true;
        }

        if (isset($_POST['post_type']) && $pt = $_POST['post_type']) {
            return in_array($pt, $this->allowed_post_types);
        }

        return false;
    }

    public function nonce_field_metabox($name) {
        wp_nonce_field($name.'_metabox', $name.'_metabox_nonce');
    }

    public function verify_nonce_metbox($name) {
        return wp_verify_nonce(filter_input(INPUT_POST, $name.'_metabox_nonce' ), $name.'_metabox' );
    }
}