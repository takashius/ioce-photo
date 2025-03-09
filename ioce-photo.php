<?php
/*
Plugin Name: Ioce Photo
Description: Plugin para vender fotos con WooCommerce.
Version: 2.4
Author: Erick Hernandez
*/

// Definir constantes del plugin
define('IOCE_PHOTO_PLUGIN_PATH', plugin_dir_path(__FILE__));

// Incluir archivos necesarios
require_once IOCE_PHOTO_PLUGIN_PATH . 'includes/product-type.php';
require_once IOCE_PHOTO_PLUGIN_PATH . 'includes/product-folder.php';
require_once IOCE_PHOTO_PLUGIN_PATH . 'includes/functions.php';
