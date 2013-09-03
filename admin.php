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
