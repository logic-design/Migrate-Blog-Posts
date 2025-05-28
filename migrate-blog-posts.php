<?php
/*
Plugin Name: Migrate Blog Posts
Description: Migrate selected posts from a remote WordPress site using REST API, with image support and duplicate detection.
Version: 0.1.2
Author: Logic Design & Consultancy Ltd
*/

if (!defined('ABSPATH')) exit;

require_once plugin_dir_path(__FILE__) . 'inc/content-cleanup.php';
require_once plugin_dir_path(__FILE__) . 'inc/functions.php';

require_once plugin_dir_path(__FILE__) . 'inc/admin-menus.php';
require_once plugin_dir_path(__FILE__) . 'inc/admin-settings.php';
require_once plugin_dir_path(__FILE__) . 'inc/admin-migrator.php';
