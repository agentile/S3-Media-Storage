<?php 
/**
 * S3MS 
 * 
 * @category S3MS
 * @package S3 Media Storage
 * 
 * @author Anthony Gentile <asgentile@gmail.com>
 */
header('Content-type: application/json');

// Default return values.
$ret = array(
    'error' => null,
    'message' => null,
    'success' => false,
    'data' => array()
);

if (!isset($_POST['action']) || !isset($_POST['passphrase'])) {
    $ret['error'] = 'Invalid Request';
    echo json_encode($ret);
    die();
}

//for multi-blog only
//$blog_id = 1;

// Specify host or domain (needed for wp-includes/ms-settings.php:100)
$_SERVER['HTTP_HOST'] = isset($_POST['http_host']) ? $_POST['http_host'] : 'localhost';

// Location of wp-load.php so we have access to database and $wpdb object
$wp_load_loc = dirname(dirname(dirname(dirname(__FILE__)))) . DIRECTORY_SEPARATOR . "wp-load.php";
require_once($wp_load_loc);
// Load s3-media-storage.php to access our classes.
$plugin_path = dirname(__FILE__) . DIRECTORY_SEPARATOR . "s3-media-storage.php";
require_once($plugin_path);

//for multi-blog only
//switch_to_blog($blog_id);

// Ensure passphrase to continue;
$settings = json_decode(get_option('S3MS_settings'), true);
if ($settings['s3_background_passphrase'] !== $_POST['passphrase']) {
    $ret['error'] = 'Invalid Passphrase';
    echo json_encode($ret);
    die();
}

switch ($_POST['action']) {
    case 'delete':
        // Using S3 Class as proof of concept here
        $s3 = new S3MS_Transfer_Adapter_S3Class($settings);
        $s3->delete($_POST['file']);
        $ret['success'] = true;
    break;
    case 'upload':
        // Using S3 Class as proof of concept here
        $s3 = new S3MS_Transfer_Adapter_S3Class($settings);
        $s3->upload($_POST['attachment_path'], $_POST['s3_path'], $_POST['attachment_id'], $_POST['data']);
        $ret['success'] = true;
    break;
}
$ret['success'] = true;
$ret['data'] = $settings;
echo json_encode($ret);

