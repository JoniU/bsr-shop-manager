<?php
// Ensuring ABSPATH for security
if (!defined('ABSPATH')) {
    exit();
}

//ajax-handlers.php
include_once 'get-products-for-editor.php';
include_once 'update-products-from-editor.php';
include_once 'helpers.php';
include_once 'profit-calculations.php';
include_once 'get-products-sales-data.php';
