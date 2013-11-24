<?php
/**
 * @package S3 Media Storage
 */
/*
Plugin Name: S3 Media Storage
Description: Store media library contents onto S3 directly without the need for temporarily storing files on the filesystem/cron jobs. This is more ideal for multiple web server environemnts.
Version: 1.0.2
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

define('S3MS_PLUGIN_VERSION', '1.0.2');
define('S3MS_PLUGIN_URL', plugin_dir_url( __FILE__ ));

register_activation_hook(__FILE__, array('S3MS', 'install'));
register_uninstall_hook(__FILE__, array('S3MS', 'uninstall'));

if ( is_admin() ) {
	require_once dirname( __FILE__ ) . DIRECTORY_SEPARATOR . 'admin.php';
}

/**
 * S3MS 
 * 
 * @category S3MS
 * @package S3 Media Storage
 * 
 * @author Anthony Gentile <asgentile@gmail.com>
 */
class S3MS {
    
    /**
     * Static settings
     */
    public static $settings = null;
    
    /**
     * Hook for plugin install
     */
    public static function install() 
    {
    
    }
    
    /**
     * Hook for plugin uninstall
     */
    public static function uninstall() 
    {
    
    }
    
    public static function getSettings()
    {
        if (!self::$settings) {
            self::$settings = json_decode(get_option('S3MS_settings'), true);
        }
        return self::$settings;
    }
    
    public static function attachmentUrl($url, $post_id) 
    {
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
        $settings = self::getSettings();
        
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
    
    public static function deleteAttachment($url) 
    { 
        $settings = self::getSettings();
    
        // Check our settings to see if we even want to delete from S3.
        if (!isset($settings['s3_delete']) || (int) $settings['s3_delete'] == 0) {
            return true;
        }
        
        $file = self::getS3PathFromAttachmentPath($url, $settings);
        
        if (isset($settings['valid']) && (int) $settings['valid']) {
            $s3 = S3MS_Transfer::getTransferClass($settings);
            return $s3->delete($file);
        }
    }
    
    public static function imageMakeIntermediateSize($attachment_path) 
    {
        $settings = self::getSettings();
        $s3_path = self::getS3PathFromAttachmentPath($attachment_path, $settings);

        if (isset($settings['valid']) && (int) $settings['valid']) {
            $s3 = S3MS_Transfer::getTransferClass($settings);
            $s3->upload($attachment_path, $s3_path, false, null);
        }
        return $s3_path;
    }
    
    public static function updateAttachmentMetadata($data, $attachment_id) 
    {
        $attachment_path = get_attached_file($attachment_id); // Gets path to attachment
        $settings = self::getSettings();
        $s3_path = self::getS3PathFromAttachmentPath($attachment_path, $settings);
        
        if (isset($settings['valid']) && (int) $settings['valid']) {
            $s3 = S3MS_Transfer::getTransferClass($settings);
            $s3->upload($attachment_path, $s3_path, $attachment_id, $data);
        }
        return $data;
    }
    
    public static function getS3PathFromAttachmentPath($attachment_path, $settings)
    {
        $upload_dir = wp_upload_dir();
        $s3_path = str_replace($upload_dir['basedir'], '', $attachment_path);
        if (substr($s3_path, 0, 1) == DIRECTORY_SEPARATOR) {
            $s3_path = substr($s3_path, 1);
        }
        
        if (isset($settings['s3_bucket_path']) && $settings['s3_bucket_path']) {
            $s3_path = $settings['s3_bucket_path'] . '/' . $s3_path;
        }
        return $s3_path;
    }
    
    public static function paginationUrl() 
    {
        parse_str($_SERVER['QUERY_STRING'], $out);
        unset($out['s3ms_page']);
        $out = http_build_query($out);
        if ($out) {
            return '?' . $out . '&s3ms_page=';
        }
        return '?s3ms_page=';
    }
    
    public static function pagination($total, $per_page, $page, $page_action, $group = 10, $offset = 0, $classes = array()) 
    {
        if ($total <= $per_page) {
            return;
        }
    
        $classes = implode(' ', $classes);
        $str = '';
    
        $start = floor($page / $group) * $group;
    
        $total_pages = ceil($total / $per_page);
    
        if ($start == 0) {
            $start = 1;
        }
    
        // do some adjustment if someone is nearing the
        // end of the group, shift the stack back
        $end_of_group = ($start + $group) - 1;
        if ($end_of_group > $total_pages) {
            $end_of_group = $total_pages;
        }
    
        if ($page == $end_of_group && $page != $total_pages) {
            $start = $end_of_group;
            $end_of_group = ($start + $group) - 1;
        } elseif ($page > ($end_of_group - 2) && $end_of_group < $total_pages) {
            $start += 1;
            $end_of_group = ($start + $group) - 1;
        }
    
        if ($page == 1) {
            $prev = 1 - $offset;
        } elseif ($page == 0) {
            $prev = 0;
        } else {
            $prev = $page - 1;
        }
    
        if ($page + 1 > ($total_pages - $offset)) {
            $next = ($total_pages - $offset);
        } else {
            $next = $page + 1;
        }
    
        if ($next == 0) {
            $next = 1;
        }
    
        $end = $total_pages - $offset;
        if ($end == 0) {
            $end = 1;
        }
    
        $str .= '<a href="' . $page_action . (1 - $offset) . '">Start</a>&nbsp;';
        $str .= '<a href="' . $page_action . $prev . '">Prev</a>&nbsp;';
    
        for ($i = $start; $i <= $end_of_group; $i++) {
            if ($i > $total_pages) {
                break;
            }
            if (($i - $offset) == $page) {
                $str .= '&nbsp;<a href="#">' . $i . '</a>&nbsp;';
            } else {
                $str .= '&nbsp;<a href="' . $page_action . ($i - $offset). '">' . $i . '</a>&nbsp;';
            }
        }
        $str .= '&nbsp;<a href="' . $page_action . $next . '">Next</a>&nbsp;';
        $str .= '&nbsp;<a href="' . $page_action . $end . '">End</a>&nbsp;';
    
        return $str;
    }
}

/**
 * 
 * S3MS Tranfer Dispatcher
 * 
 * @category S3MS
 * @package S3 Media Storage
 * 
 * @author Anthony Gentile <asgentile@gmail.com>
 * 
 */
class S3MS_Transfer {
    
    public static $transfer_class = null;
    
    public static function getTransferClass($settings)
    {
        $transfer_class = isset($settings['s3_transfer_method']) ? $settings['s3_transfer_method'] : 's3class';
        if ($transfer_class == 's3class') {
            self::$transfer_class = new S3MS_Transfer_Adapter_S3Class($settings);
        } elseif ($transfer_class == 's3cmd') {
            self::$transfer_class = new S3MS_Transfer_Adapter_S3Cmd($settings);
        } elseif ($transfer_class == 'awscli') {
            self::$transfer_class = new S3MS_Transfer_Adapter_AwsCli($settings);
        } elseif ($transfer_class == 'background') {
            self::$transfer_class = new S3MS_Transfer_Adapter_Background($settings);
        } else {
            throw new S3MS_Transfer_Exception('Invalid Transfer Class.');
            return;
        }
        
        return self::$transfer_class;
    }
}

/**
 * 
 * S3MS Tranfer Exception
 * 
 * @category S3MS
 * @package S3 Media Storage
 * 
 * @author Anthony Gentile <asgentile@gmail.com>
 * 
 */
class S3MS_Transfer_Exception extends Exception {
    
    public function __construct($message = null)
    {
        parent::__construct($message);
    }
}

/**
 * 
 * Abstract base class for specific Transfer Adapters
 * 
 * @category S3MS
 * @package S3 Media Storage
 * 
 * @author Anthony Gentile <asgentile@gmail.com>
 * 
 */
abstract class S3MS_Transfer_Adapter {
    
    abstract public function delete($file);
    abstract public function upload($attachment_path, $s3_path, $attachment_id = false, $data = null);
}

/**
 * 
 * S3 Class Transfer Adapter
 * 
 * @category S3MS_Transfer_Adapter
 * @package S3 Media Storage
 * 
 * @author Anthony Gentile <asgentile@gmail.com>
 * 
 */
class S3MS_Transfer_Adapter_S3Class {
    
    public $s3 = null;
    public $settings = array();
    
    public function __construct($settings)
    {
        if (!class_exists('S3')) {
            require_once dirname( __FILE__ ) . DIRECTORY_SEPARATOR . 'S3.php';
        }
        
        $this->settings = $settings;
        $this->s3 = new S3($settings['s3_access_key'], $settings['s3_secret_key']);
        $this->s3->setSSL((bool) $this->settings['s3_ssl']);
        $this->s3->setExceptions(true);
    }
    
    public function delete($file) 
    {
        try {
            $this->s3->deleteObject($this->settings['s3_bucket'], $file);
            return true;
        } catch (Exception $e) {}
    }
    
    public function upload($attachment_path, $s3_path, $attachment_id = false, $data = null)
    {
        $meta_headers = array();
        // Allow for far reaching expires
        $request_headers = array();
        if (trim($this->settings['s3_expires']) != '') {
            $request_headers = array(
                "Cache-Control" => "max-age=315360000",
                "Expires" => gmdate("D, d M Y H:i:s T", strtotime(trim($this->settings['s3_expires'])))
            );
        }

        try {
            $this->s3->putObjectFile($attachment_path, $this->settings['s3_bucket'], $s3_path, S3::ACL_PUBLIC_READ, $meta_headers, $request_headers);
            if ($attachment_id) {
                // We store per file instead of always just referencing the settings, as if settings change we don't want to break previously
                // uploaded files that refer to different buckets/cloudfront/etc.
                update_post_meta($attachment_id, "S3MS_bucket", $this->settings['s3_bucket']);
                update_post_meta($attachment_id, "S3MS_bucket_path", $this->settings['s3_bucket_path']);
                update_post_meta($attachment_id, "S3MS_file", $s3_path);
                update_post_meta($attachment_id, "S3MS_cloudfront", $this->settings['s3_cloudfront']);
                if ((isset($data['S3MS_move']) && $data['S3MS_move']) || (isset($this->settings['s3_delete_local']) && $this->settings['s3_delete_local'])) {
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
                                self::imageMakeIntermediateSize($new_attachment_path);
                            }
                        }
                    }
                }
            } else {
                if (isset($this->settings['s3_delete_local']) && $this->settings['s3_delete_local']) {
                    @unlink($attachment_path);
                } 
            }
        } catch (Exception $e) {}
    }
}

/**
 * 
 * s3cmd Transfer Adapter
 * 
 * @category S3MS_Transfer_Adapter
 * @package S3 Media Storage
 * 
 * @author Anthony Gentile <asgentile@gmail.com>
 * 
 */
class S3MS_Transfer_Adapter_S3Cmd {
    
    public $s3cmd_path = null;
    public $config_path = null;
    public $settings = array();
    
    public function __construct($settings)
    {
        $this->settings = $settings;
        exec('which s3cmd', $exec_output);
        if (is_array($exec_output) && isset($exec_output[0]) && is_file($exec_output[0])) {
            $this->s3cmd_path = $exec_output[0];
        }
        
        if (isset($this->settings['s3_s3cmd_config'])) {
            $this->config_path = $this->settings['s3_s3cmd_config'];
        }
    }
    
    public function buildPartialCmd()
    {
        if ($this->config_path && is_file($this->config_path)) {
            $cmd = "{$this->s3cmd_path} -c {$this->config_path}";
        } else {
            // sadly this doesn't work as s3cmd still wants the config present and readable
            $cmd = "{$this->s3cmd_path} --access_key={$this->settings['s3_access_key']} --secret_key={$this->settings['s3_secret_key']}";
        }
        return $cmd;
    }
    
    public function delete($file) 
    {
        $p = $this->buildPartialCmd();
        $cmd = escapeshellcmd($p . " del s3://{$this->settings['s3_bucket']}/{$file}");
        try {
            exec($cmd, $exec_output, $ret);
            return true;
        } catch (Exception $e) {}
    }
    
    public function upload($attachment_path, $s3_path, $attachment_id = false, $data = null)
    {        
        $p = $this->buildPartialCmd();
        if (trim($this->settings['s3_expires']) != '') {
            $p .= ' --add-header="Cache-Control:max-age=315360000"';
            $p .= ' --add-header="Expires:' . gmdate("D, d M Y H:i:s T", strtotime(trim($this->settings['s3_expires']))) . '"';
        }
        $cmd = escapeshellcmd($p . " put -P -M --multipart-chunk-size-mb=15 {$attachment_path} s3://{$this->settings['s3_bucket']}/{$s3_path}");
        try {
            exec($cmd, $exec_output, $ret);
            if ($attachment_id) {
                // We store per file instead of always just referencing the settings, as if settings change we don't want to break previously
                // uploaded files that refer to different buckets/cloudfront/etc.
                update_post_meta($attachment_id, "S3MS_bucket", $this->settings['s3_bucket']);
                update_post_meta($attachment_id, "S3MS_bucket_path", $this->settings['s3_bucket_path']);
                update_post_meta($attachment_id, "S3MS_file", $s3_path);
                update_post_meta($attachment_id, "S3MS_cloudfront", $this->settings['s3_cloudfront']);
                if ((isset($data['S3MS_move']) && $data['S3MS_move']) || (isset($this->settings['s3_delete_local']) && $this->settings['s3_delete_local'])) {
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
                                self::imageMakeIntermediateSize($new_attachment_path);
                            }
                        }
                    }
                }
            } else {
                if (isset($this->settings['s3_delete_local']) && $this->settings['s3_delete_local']) {
                    @unlink($attachment_path);
                } 
            }
        } catch (Exception $e) {}
    }
}

/**
 * 
 * s3cmd AWS CLI Adapter
 * 
 * @category S3MS_Transfer_Adapter
 * @package S3 Media Storage
 * 
 * @author Anthony Gentile <asgentile@gmail.com>
 * 
 */
class S3MS_Transfer_Adapter_AwsCli {
    
    public $settings = array();
    
    public function __construct($settings)
    {
        $this->settings = $settings;
    }
    

    
    public function delete($file) 
    {

    }
    
    public function upload($attachment_path, $s3_path, $attachment_id = false, $data = null)
    {        

    }
}

/**
 * 
 * s3cmd Background Adapter
 * 
 * @category S3MS_Transfer_Adapter
 * @package S3 Media Storage
 * 
 * @author Anthony Gentile <asgentile@gmail.com>
 * 
 */
class S3MS_Transfer_Adapter_Background {
    
    public $settings = array();
    public $endpoint = null;
    public $plugin_path = null;
    public static $time_out = 30;
    
    public function __construct($settings)
    {
        $this->settings = $settings;
        
        $protocol = 'http://';
        if (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] === '443') {
            $protocol = 'https://';
        }
        
        $plugin_path = dirname(__FILE__);
        $parts = explode(DIRECTORY_SEPARATOR, $plugin_path);
        $this->plugin_path = end($parts);
        
        $this->endpoint = $protocol . $_SERVER['HTTP_HOST'] . '/wp-content/plugins/' . $this->plugin_path . '/s3-upload.php';

    }
    
    public function delete($file) 
    {
        $args = array(
            'action' => 'delete',
            'passphrase' => $this->settings['s3_background_passphrase'],
            'bucket' => $this->settings['s3_bucket'],
            'file' => $file,
            'http_host' => $_SERVER['HTTP_HOST'],
        );
        $response = $this->fetch($this->endpoint, $args, 'POST');
        $ret = $response->getResponse();
        if (is_array($ret) && isset($ret['success'])) {
            return true;
        }
    }
    
    public function upload($attachment_path, $s3_path, $attachment_id = false, $data = null)
    {        
        // Start background process to move assets to Amazon S3
        $args = array(
            'action' => 'upload',
            'passphrase' => $this->settings['s3_background_passphrase'],
            'attachment_path' => $attachment_path,
            'bucket' => $this->settings['s3_bucket'],
            's3_path' => $s3_path,
            'attachment_id' => $attachment_id,
            'data' => $data,
            'http_host' => $_SERVER['HTTP_HOST'],
        );
        $response = $this->fetch($this->endpoint, $args, 'POST');
    }
    
    /**
    * Make request s3-upload.php with PHP Streams (no cURL required, most portable)
    *
    * @param $url endpoint
    * @param $args
    * @param $method GET/POST
    * @param $headers array headers to send with request.
    *
    */
    public function fetch($url, $args = array(), $method = 'GET', $headers = array("Content-Type: application/x-www-form-urlencoded"))
    {
        $headers = array_merge($headers, array("User-Agent: S3MS"));
        $opts = array(
            'http' => array(
                'method' => strtoupper($method),
                'request_fulluri' => true,
                'timeout' => self::$time_out,
                'ignore_errors' => true,
            )
        );
        
        if (strtolower($method) == 'post') {
            $opts['http']['content'] = http_build_query($args, '', '&');
            $headers = array_merge($headers, array("Content-Length: " . strlen($opts['http']['content'])));
        } else if (strtolower($method) == 'get' && !empty($args)) {
            $url .= '?' . http_build_query($args);
        }
        
        if ($headers) {
            $opts['http']['header'] = implode("\r\n", $headers) . "\r\n";
        }

        $context = stream_context_create($opts);

        $fp = fopen($url, 'rb', FALSE, $context);
        
        // Get the status code
        preg_match("/\d{3}/", $http_response_header[0], $matches);
        $http_status = isset($matches[0]) ? (int) $matches[0] : null;
        
        if (!$fp) {
            throw new S3MS_Transfer_Exception("Problem connecting to $url");
        }
        
        $response = @stream_get_contents($fp);

        if ($response === FALSE) {
            throw new S3MS_Transfer_Exception("Problem reading data from $url");
        }
        return new S3MS_Transfer_Adapter_Background_Response($response, $http_status);
    }
}

/**
 *
 * Response Object
 *
 */
class S3MS_Transfer_Adapter_Background_Response {
    
    /**
     * Decoded JSON response array
     */
    protected $_response;
    
    /**
     * HTTP status code
     */
    protected $_status_code;
    
    /**
     * Constructor!
     * 
     * @param $response
     * @param $status_code
     * 
     * @return mixed
     */
    public function __construct($response, $status_code = null)
    {
        $json = json_decode($response, true);
        
        // cheap check to see if we have a file instead of JSON
        if ($json === null) {
            $json = $response;
        }
        
        $this->_response = $json;
        $this->_status_code = $status_code;
    }
    
    /**
     * Does this response contain an error?
     * 
     * @return null
     */
    public function containsError()
    {
        if (is_object($this->_response) && isset($this->_response->error)) {
            return true;
        }
        
        return false;
    }
    
    /**
     * HTTP status code getter
     *
     * @return mixed
     */
    public function getStatusCode()
    {
        return $this->_status_code;
    }
    
    /**
     * Response array getter
     *
     * @return mixed
     */
    public function getResponse()
    {
        return $this->_response;
    }
    
    /**
     * Combine error info into useful error message.
     *
     * @return mixed false if no error, string otherwise
     */
    public function getError()
    {
        if (!$this->containsError()) {
            return false;
        }
        
        if ($this->getStatusCode()) {
            $error = array($this->getStatusCode(), $this->_response->error->error_name, $this->_response->error->error_msg);
        } else {
            $error = array($this->_response->error->error_name, $this->_response->error->error_msg);
        }
        
        $error = implode(' : ', $error);

        if (php_sapi_name() == 'cli') {
            $error .= "\n";
        }
        
        return $error;
    }
}

/**
 * Register hooks/filters
 */
// Handle original image uploads and edits for that image
add_filter('wp_update_attachment_metadata', array('S3MS', 'updateAttachmentMetadata'), 9, 2);
// Handle thumbs that are created for that image
add_filter('image_make_intermediate_size', array('S3MS', 'imageMakeIntermediateSize'));
// Handle when image urls are requested.
add_action("wp_get_attachment_url", array('S3MS', 'attachmentUrl'), 9, 2);
add_action("wp_get_attachment_thumb_url", array('S3MS', 'attachmentUrl'), 9, 2);
// Handle when images are deleted.
add_action("wp_delete_file", array('S3MS', 'deleteAttachment'));
// We can't hook into add_attachment/edit_attachment actions as these occur too early in the chain as at that point in time, 
// metadata for the attachment has not been associated. So we want to wait, so we can handle both and then delete the local uploaded file.
// add_action("add_attachment", 's3_update_attachment');
// add_action("edit_attachment", 's3_update_attachment');

