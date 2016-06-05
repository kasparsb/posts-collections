<?php
/*
Plugin Name: Posts collections
Plugin URI: http://webit.lv/posts-collections
Description: Add ability to create collections of posts
Version: 1.0
Author: Kaspars Bulins
Author URI: http://webit.lv
License: Commercial
*/

include_once(plugin_dir_path(__FILE__).'base.php');
include_once(plugin_dir_path(__FILE__).'facade.php');
include_once(plugin_dir_path(__FILE__).'plugin.php');


// Define static facade to theme object
class PostsCollections extends PostsCollections\Facade {}

// Init facade and create plugin object
PostsCollections::init('PostsCollections\Plugin');