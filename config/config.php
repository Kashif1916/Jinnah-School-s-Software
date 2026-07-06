<?php
/**
 * General Configuration
 * School Finance Management System
 */

define('BASE_URL', 'http://localhost/Finance_System/');
define('SITE_NAME', 'School Finance Management System');
define('SYSTEM_NAME', 'SFMS');

// Current Academic Year
define('CURRENT_YEAR', date('Y'));

// Month Names
$MONTHS = [
    'Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun',
    'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'
];

// Date & Time Format
define('DATE_FORMAT', 'd-m-Y');
define('TIME_FORMAT', 'H:i:s');
define('DATETIME_FORMAT', 'd-m-Y H:i:s');

// Classes available in school
$CLASSES = ['P.G','Nursury', 'Prep' , '1', '2', '3', '4', '5', '6', '7', '8', 'Pre-9', '9', '10', 'Passed-10', '11', '12'];

// Sections available
$SECTIONS = ['B', 'G'];

// PDF Settings
define('PDF_FONT_SIZE_LARGE', 14);
define('PDF_FONT_SIZE_NORMAL', 11);
define('PDF_FONT_SIZE_SMALL', 9);

?>
