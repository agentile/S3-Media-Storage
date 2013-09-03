<?php
/**
 * ADMIN OPTIONS
 */
function S3MSAdminMenu() {
    add_options_page('S3 Media Storage', 'S3 Media Storage', '4', 'S3MSAdminMenu', 'S3MSAdminContent');
}

add_action('admin_menu', 'S3MSAdminMenu');

function S3MSAdminContent() {    
    if ($_POST['submit']) {
        $errors = array();
        
        if (!isset($_POST['s3_bucket']) || trim($_POST['s3_bucket']) == '') {
            $errors[] = "S3 Bucket Name Missing!";
        }
        
        if (!isset($_POST['s3_access_key']) || trim($_POST['s3_access_key']) == '') {
            $errors[] = "S3 Access Key Missing!";
        }
        
        if (!isset($_POST['s3_secret_key']) || trim($_POST['s3_secret_key']) == '') {
            $errors[] = "S3 Secret Key Missing!";
        }
        
        $bucket = trim($_POST['s3_bucket']);
        $access_key = trim($_POST['s3_access_key']);
        $secret_key = trim($_POST['s3_secret_key']);
        $ssl = isset($_POST['s3_ssl']) ? 1 : 0;
        
        // Test connectivity
        require_once dirname( __FILE__ ) . '/S3.php';
        $s3 = new S3($access_key, $secret_key);
        $s3->setSSL((bool) $ssl);
        $s3->setExceptions(true);
        
        try {
            $s3->getBucketLocation($bucket);
        } catch (Exception $e) {
            $errors[] = "Could not connect to bucket with the provided credentials!";
            $errors[] = $e->getMessage();
        }
        
        if (!empty($errors)) {
            $msg = implode('<br/>', $errors);
            ?>
            <div class="error"><p><strong><?php _e($msg, 'S3MS' ); ?></strong></p></div>
            <?php
        } else {
            // No errors!
            $settings = array(
                's3_bucket' => trim($_POST['s3_bucket']),
                's3_access_key' => trim($_POST['s3_access_key']),
                's3_secret_key' => trim($_POST['s3_secret_key']),
                's3_ssl' => isset($_POST['s3_ssl']) ? 1 : 0,
                's3_delete' => isset($_POST['s3_delete']) ? 1 : 0,
                's3_expires' => trim($_POST['s3_expires']),
                's3_cloudfront' => trim($_POST['s3_cloudfront']),
                's3_protocol' => in_array(trim($_POST['s3_protocol']), array('http','https','relative')) ? trim($_POST['s3_protocol']) : 'relative',
                'valid' => 1,
            );
            
            $settings = json_encode($settings);
            $ret = update_option('S3MS_settings', $settings);
            
            ?>
            <div class="updated"><p><strong><?php _e('Settings Saved!', 'S3MS' ); ?></strong></p></div>
            <?php
        }
    }
    
    // Get existing/POST options
    $settings = json_decode(get_option('S3MS_settings'), true);
    
    $s3_bucket = isset($_POST['s3_bucket']) ? trim($_POST['s3_bucket']) : null;
    if (!$s3_bucket && is_array($settings) && isset($settings['s3_bucket'])) {
        $s3_bucket = $settings['s3_bucket'];
    }
    
    $s3_access_key = isset($_POST['s3_access_key']) ? trim($_POST['s3_access_key']) : null;
    if (!$s3_access_key && is_array($settings) && isset($settings['s3_access_key'])) {
        $s3_access_key = $settings['s3_access_key'];
    }
    
    $s3_secret_key = isset($_POST['s3_secret_key']) ? trim($_POST['s3_secret_key']) : null;
    if (!$s3_secret_key && is_array($settings) && isset($settings['s3_secret_key'])) {
        $s3_secret_key = $settings['s3_secret_key'];
    }
    
    $s3_ssl = isset($_POST['s3_ssl']) ? (int) $_POST['s3_ssl'] : null;
    if (!$s3_ssl && is_array($settings) && isset($settings['s3_ssl'])) {
        $s3_ssl = (int) $settings['s3_ssl'];
    }
    
    $s3_delete = isset($_POST['s3_delete']) ? (int) $_POST['s3_delete'] : null;
    if (!$s3_delete && is_array($settings) && isset($settings['s3_delete'])) {
        $s3_delete = (int) $settings['s3_delete'];
    }
    
    $s3_expires = isset($_POST['s3_expires']) ? trim($_POST['s3_expires']) : null;
    if (!$s3_expires && is_array($settings) && isset($settings['s3_expires'])) {
        $s3_expires = $settings['s3_expires'];
    }
    
    $s3_cloudfront = isset($_POST['s3_cloudfront']) ? trim($_POST['s3_cloudfront']) : null;
    if (!$s3_cloudfront && is_array($settings) && isset($settings['s3_cloudfront'])) {
        $s3_cloudfront = $settings['s3_cloudfront'];
    }
    
    $s3_protocol = isset($_POST['s3_protocol']) ? trim($_POST['s3_protocol']) : null;
    if (!$s3_protocol && is_array($settings) && isset($settings['s3_protocol'])) {
        $s3_protocol = $settings['s3_protocol'];
    }
?>
<div class="wrap">
	<h2>S3 Media Storage Options</h2>

	<div id="poststuff">
		<div class="postbox">
		<h3><?php _e('Settings'); ?></h3>
            <div class="inside">
            <form id="S3MS-config" method="post" action="" enctype="multipart/form-data">
                
                <table class="form-table">
                    <tbody>
                        <tr>
                            <th><label for="key"><?php _e("S3 Bucket Name:", 'S3MS' ); ?></label></th>
                            <td>
                                <input style="width:300px;" type="text" name="s3_bucket" value="<?php echo $s3_bucket;?>" placeholder="Enter S3 Bucket Name e.g. media.myblog"/>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="key"><?php _e("S3 Access Key:", 'S3MS' ); ?></label></th>
                            <td>
                                <input style="width:400px;" type="text" name="s3_access_key" value="<?php echo $s3_access_key;?>" placeholder="Enter S3 Access Key"/>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="key"><?php _e("S3 Secret Key:", 'S3MS' ); ?></label></th>
                            <td>
                                <input style="width:400px;" type="text" name="s3_secret_key" value="<?php echo $s3_secret_key;?>" placeholder="Enter S3 Secret Key"/>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="key"><?php _e("Use SSL:", 'S3MS' ); ?></label></th>
                            <td>
                                <input type="checkbox" name="s3_ssl" value="1" <?php echo ($s3_ssl) ? 'checked="checked"' : '';?>/>
                                <p class="description">Encrypt traffic for data sent to S3?</p>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="key"><?php _e("Delete from S3:", 'S3MS' ); ?></label></th>
                            <td>
                                <input type="checkbox" name="s3_delete" value="1" <?php echo ($s3_delete) ? 'checked="checked"' : '';?>/>
                                <p class="description">Deleting from Media Library deletes from S3? (May not wish to for cost reasons)</p>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="key"><?php _e("Expires:", 'S3MS' ); ?></label></th>
                            <td>
                                <input style="width:400px;" type="text" name="s3_expires" value="<?php echo $s3_expires;?>" placeholder="Enter expires format"/>
                                <p class="description">To set far reaching expires for assets, enter it in a <a href="http://us1.php.net/manual/en/datetime.formats.php" target="_blank">valid strtotime format</a> e.g. +15 years</p>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="key"><?php _e("Cloudfront:", 'S3MS' ); ?></label></th>
                            <td>
                                <input style="width:400px;" type="text" name="s3_cloudfront" value="<?php echo $s3_cloudfront;?>" placeholder="Enter Cloudfront"/>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="key"><?php _e("Protocol:", 'S3MS' ); ?></label></th>
                            <td>
                                <input type="radio" name="s3_protocol" value="http" <?php echo ($s3_protocol == 'http') ? 'checked="checked"' : '';?>/> Always serve from HTTP.<br/>
                                <input type="radio" name="s3_protocol" value="https" <?php echo ($s3_protocol == 'https') ? 'checked="checked"' : '';?>/> Always serve from HTTPS.<br/>
                                <input type="radio" name="s3_protocol" value="relative" <?php echo ($s3_protocol == 'relative') ? 'checked="checked"' : '';?>/> Serve from same protocol as requested page.<br/>
                            </td>
                        </tr>
                    </tbody>
                </table>
                
                <p class="submit">
                    <input type="submit" name="submit" id="submit" class="button button-primary" value="<?php _e('Save Changes');?>">
                </p>
            </form>
            </div>
        </div>
	</div>
<?php
}
