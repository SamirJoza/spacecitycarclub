<?php

namespace App\Support\Admin\Dashboard;

defined('ABSPATH') || exit;

//Rename dashboard
require_once get_theme_file_path('app/Support/Admin/Dashboard/Rename.php');

// Sponsors widget
require_once get_theme_file_path('app/Support/Admin/Dashboard/SponsorsWidget.php');

// Members widget 
require_once get_theme_file_path('app/Support/Admin/Dashboard/MembersWidget.php');

// Cleanup Dashboard
require_once get_theme_file_path('app/Support/Admin/Dashboard/Cleanup.php'); 

