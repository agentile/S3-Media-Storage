<?php
/**
 * @package S3 Media Storage
 */
/*
Plugin Name: S3 Media Storage
Description: Store media library contents onto S3 directly without the need for temporarily storing files on the filesystem/cron jobs. This is more ideal for multiple web server environemnts.
Version: 1.0.1
Author: Anthony Gentile
Author URI: http://agentile.com
*/

/*  Copyright 2013  Anthony Gentile  (email : asgentile@gmail.com)

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License, version 2, as 
    published by the Free Software Foundation.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

// Don't expose any info if called directly
if ( !function_exists( 'add_action' ) ) {
	echo 'S3 Media Storage WordPress Plugin';
	exit;
}

define('S3MS_PLUGIN_VERSION', '0.9');
define('S3MS_PLUGIN_URL', plugin_dir_url( __FILE__ ));

function s3ms_install() {

}

register_activation_hook(__FILE__, 's3ms_install');

function s3ms_uninstall() {
    
}

register_uninstall_hook(__FILE__, 's3ms_uninstall');

if ( is_admin() ) {
	require_once dirname( __FILE__ ) . DIRECTORY_SEPARATOR . 'admin.php';
}

function s3_attachment_url($url, $post_id) {
    $custom_fields = get_post_custom($post_id);
    
    $bucket = isset($custom_fields['S3MS_bucket']) ? $custom_fields['S3MS_bucket'][0] : null;
    $bucket_path = isset($custom_fields['S3MS_bucket_path']) ? $custom_fields['S3MS_bucket_path'][0] : null;
    
    // Was this a file we even uploaded to S3? If not bail.
    if (!$bucket || trim($bucket) == '') {
        return $url;
    }
    
    $upload_dir = wp_upload_dir();

    $file = str_replace($upload_dir['baseurl'], '', $url);
    if (substr($file, 0, 1) == DIRECTORY_SEPARATOR) {
        $file = substr($file, 1);
    }
    
    // $file = isset($custom_fields['S3MS_file']) ? $custom_fields['S3MS_file'][0] : null;
    $cloudfront = isset($custom_fields['S3MS_cloudfront']) ? $custom_fields['S3MS_cloudfront'][0] : null;
    $settings = json_decode(get_option('S3MS_settings'), true);
    
    // Determine protocol to serve from
    if ($settings['s3_protocol'] == 'http') {
        $protocol = 'http://';
    } elseif ($settings['s3_protocol'] == 'https') {
        $protocol = 'https://';
    } elseif ($settings['s3_protocol'] == 'relative') {
        $protocol = 'http://';
        if (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] === '443') {
            $protocol = 'https://';
        }
    } else {
        $protocol = 'https://';
    }
    
    // Should serve with respective protocol
    if ($cloudfront && trim($cloudfront) != '') {
        if ($bucket_path) {
            $url = $protocol . $cloudfront . '/' . $bucket_path . '/' . $file;
        } else {
            $url = $protocol . $cloudfront . '/' . $file;
        }
    } else {
        if ($bucket_path) {
            $url = $protocol . $bucket . '.s3.amazonaws.com/' . $bucket_path . '/' . $file;
        } else {
            $url = $protocol . $bucket . '.s3.amazonaws.com/' . $file;
        }
    }

    return $url;
}


function s3_delete_attachment($url) { 
    $settings = json_decode(get_option('S3MS_settings'), true);

    // Check our settings to see if we even want to delete from S3.
    if (!isset($settings['s3_delete']) || (int) $settings['s3_delete'] == 0) {
        return true;
    }
    
    $upload_dir = wp_upload_dir();
    $file = str_replace($upload_dir['basedir'], '', $url);
    if (substr($file, 0, 1) == DIRECTORY_SEPARATOR) {
        $file = substr($file, 1);
    }
    
    if (isset($settings['s3_bucket_path']) && $settings['s3_bucket_path']) {
        $file = $settings['s3_bucket_path'] . '/' . $file;
    }
    
    if (isset($settings['valid']) && (int) $settings['valid']) {
        if (!class_exists('S3')) {
            require_once dirname( __FILE__ ) . DIRECTORY_SEPARATOR . 'S3.php';
        }
        
        $s3 = new S3($settings['s3_access_key'], $settings['s3_secret_key']);
        $ssl = (int) $settings['s3_ssl'];
        $s3->setSSL((bool) $ssl);
        $s3->setExceptions(true);

        try {
            $s3->deleteObject($settings['s3_bucket'], $file);
            return true;
        } catch (Exception $e) {
            //echo $e->getMessage();
            //die();
        }
    }
}

function s3_image_make_intermediate_size($attachment_path) {
    $upload_dir = wp_upload_dir();
    $s3_path = str_replace($upload_dir['basedir'], '', $attachment_path);
    if (substr($s3_path, 0, 1) == DIRECTORY_SEPARATOR) {
        $s3_path = substr($s3_path, 1);
    }
    $settings = json_decode(get_option('S3MS_settings'), true);
    
    if (isset($settings['s3_bucket_path']) && $settings['s3_bucket_path']) {
        $s3_path = $settings['s3_bucket_path'] . '/' . $s3_path;
    }
    
    if (isset($settings['valid']) && (int) $settings['valid']) {
        if (!class_exists('S3')) {
            require_once dirname( __FILE__ ) . DIRECTORY_SEPARATOR . 'S3.php';
        }
        
        $s3 = new S3($settings['s3_access_key'], $settings['s3_secret_key']);
        $ssl = (int) $settings['s3_ssl'];
        $s3->setSSL((bool) $ssl);
        $s3->setExceptions(true);
        
        $meta_headers = array();
        // Allow for far reaching expires
        $request_headers = array();
        if (trim($settings['s3_expires']) != '') {
            $request_headers = array(
                "Cache-Control" => "max-age=315360000",
                "Expires" => gmdate("D, d M Y H:i:s T", strtotime(trim($settings['s3_expires'])))
            );
        }

        try {
            $s3->putObjectFile($attachment_path, $settings['s3_bucket'], $s3_path, S3::ACL_PUBLIC_READ, $meta_headers, $request_headers);
            if (isset($settings['s3_delete_local']) && $settings['s3_delete_local']) {
                @unlink($attachment_path);
            } 
        } catch (Exception $e) {
            //echo $e->getMessage();
            //die();
        }
    }
    return $s3_path;
}

function s3_update_attachment_metadata($data, $attachment_id) {
    $attachment_path = get_attached_file($attachment_id); // Gets path to attachment
    $upload_dir = wp_upload_dir();
    $s3_path = str_replace($upload_dir['basedir'], '', $attachment_path);
    if (substr($s3_path, 0, 1) == DIRECTORY_SEPARATOR) {
        $s3_path = substr($s3_path, 1);
    }
    $settings = json_decode(get_option('S3MS_settings'), true);
    
    if (isset($settings['s3_bucket_path']) && $settings['s3_bucket_path']) {
        $s3_path = $settings['s3_bucket_path'] . '/' . $s3_path;
    }
    
    if (isset($settings['valid']) && (int) $settings['valid']) {
        if (!class_exists('S3')) {
            require_once dirname( __FILE__ ) . DIRECTORY_SEPARATOR . 'S3.php';
        }
        
        $s3 = new S3($settings['s3_access_key'], $settings['s3_secret_key']);
        $ssl = (int) $settings['s3_ssl'];
        $s3->setSSL((bool) $ssl);
        $s3->setExceptions(true);
        
        $meta_headers = array();
        // Allow for far reaching expires
        $request_headers = array();
        if (trim($settings['s3_expires']) != '') {
            $request_headers = array(
                "Cache-Control" => "max-age=315360000",
                "Expires" => gmdate("D, d M Y H:i:s T", strtotime(trim($settings['s3_expires'])))
            );
        }

        try {
            $s3->putObjectFile($attachment_path, $settings['s3_bucket'], $s3_path, S3::ACL_PUBLIC_READ, $meta_headers, $request_headers);
            // We store per file instead of always just referencing the settings, as if settings change we don't want to break previously
            // uploaded files that refer to different buckets/cloudfront/etc.
            update_post_meta($attachment_id, "S3MS_bucket", $settings['s3_bucket']);
            update_post_meta($attachment_id, "S3MS_bucket_path", $settings['s3_bucket_path']);
            update_post_meta($attachment_id, "S3MS_file", $s3_path);
            update_post_meta($attachment_id, "S3MS_cloudfront", $settings['s3_cloudfront']);
            if ((isset($data['S3MS_move']) && $data['S3MS_move']) || (isset($settings['s3_delete_local']) && $settings['s3_delete_local'])) {
                @unlink($attachment_path);
            }
            
            // If we are copy or moving we need to grab any thumbnails as well.
            if (isset($data['S3MS_move'])) {
                $c = wp_get_attachment_metadata($attachment_id);
                if (isset($c['sizes']) && is_array($c['sizes'])) {
                    foreach ($c['sizes'] as $size) {
                        // Do a cheap check for - and x to know that we are talking about a resized image
                        // e.g. Photo0537.jpg turns into Photo0537-150x150.jpg
                        if (isset($size['file']) && strpos($size['file'], '-') && strpos($size['file'], 'x')) {
                            $parts = pathinfo($attachment_path);
                            $new_attachment_path = $parts['dirname'] . DIRECTORY_SEPARATOR . $size['file'];
                            s3_image_make_intermediate_size($new_attachment_path);
                        }
                    }
                }
            }
        } catch (Exception $e) {
            //echo $e->getMessage();
            //die();
            return $data;
        }
    }
    return $data;
}

/**
 * Register hooks/filters
 */
// Handle original image uploads and edits for that image
add_filter('wp_update_attachment_metadata', 's3_update_attachment_metadata', 9, 2);
// Handle thumbs that are created for that image
add_filter('image_make_intermediate_size', 's3_image_make_intermediate_size');
// Handle when image urls are requested.
add_action("wp_get_attachment_url", 's3_attachment_url', 9, 2);
add_action("wp_get_attachment_thumb_url", 's3_attachment_url', 9, 2);
// Handle when images are deleted.
add_action("wp_delete_file", 's3_delete_attachment');
// We can't hook into add_attachment/edit_attachment actions as these occur too early in the chain as at that point in time, 
// metadata for the attachment has not been associated. So we want to wait, so we can handle both and then delete the local uploaded file.
// add_action("add_attachment", 's3_update_attachment');
// add_action("edit_attachment", 's3_update_attachment');

