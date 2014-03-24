<?php
/**
 * ADMIN OPTIONS
 */
function S3MSAdminMenu() {
    add_options_page('S3 Media Storage', 'S3 Media Storage', 's3ms-edit-files', 'S3MSAdminMenu', 'S3MSAdminContent');
}

add_action('admin_menu', 'S3MSAdminMenu');

function S3MSAdminContent() {
    if (isset($_POST['submit'])) {
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
        require_once dirname( __FILE__ ) . DIRECTORY_SEPARATOR . 'S3.php';
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
                's3_bucket_path' => isset($_POST['s3_bucket_path']) ? ltrim(rtrim(trim($_POST['s3_bucket_path']), '/') ,'/') : '',
                's3_access_key' => trim($_POST['s3_access_key']),
                's3_secret_key' => trim($_POST['s3_secret_key']),
                's3_ssl' => isset($_POST['s3_ssl']) ? 1 : 0,
                's3_delete_local' => isset($_POST['s3_delete_local']) ? 1 : 0,
                's3_delete' => isset($_POST['s3_delete']) ? 1 : 0,
                's3_expires' => trim($_POST['s3_expires']),
                's3_cloudfront' => trim($_POST['s3_cloudfront']),
                's3_protocol' => in_array(trim($_POST['s3_protocol']), array('http','https','relative')) ? trim($_POST['s3_protocol']) : 'relative',
                's3_transfer_method' => in_array(trim($_POST['s3_transfer_method']), array('s3class','s3cmd','awscli','background')) ? trim($_POST['s3_transfer_method']) : 's3class',
                'valid' => 1,
            );

            $settings = json_encode($settings);
            $ret = update_option('S3MS_settings', $settings);

            ?>
            <div class="updated"><p><strong><?php _e('Settings Saved!', 'S3MS' ); ?></strong></p></div>
            <?php
        }
    }

    if (isset($_POST['move_files']) || isset($_POST['copy_files'])) {
        $move = isset($_POST['move_files']) ? true : false;
        $label = isset($_POST['move_files']) ? 'Moved' : 'Copied';
        if (isset($_POST['selected']) && is_array($_POST['selected'])) {
            $success_count = 0;
            $error_count = 0;
            foreach ($_POST['selected'] as $id) {
                $ret = S3MS::updateAttachmentMetadata(array('S3MS_move' => $move), $id);
                if ($ret) {
                    $success_count++;
                } else {
                    $error_count++;
                }
            }
            ?>
            <div class="updated"><p><strong><?php _e(number_format($success_count) . ' File(s) '.$label.'!', 'S3MS' ); ?></strong></p></div>
            <?php
            if ($error_count > 0) {
                ?>
                <div class="error"><p><strong><?php _e(number_format($error_count) . ' File(s) Could Not Be '.$label.'!', 'S3MS' ); ?></strong></p></div>
                <?php
            }
        }
    }

    // Get existing/POST options
    $settings = json_decode(get_option('S3MS_settings'), true);

    $s3_bucket = isset($_POST['s3_bucket']) ? trim($_POST['s3_bucket']) : null;
    if (!$s3_bucket && is_array($settings) && isset($settings['s3_bucket'])) {
        $s3_bucket = $settings['s3_bucket'];
    }

    $s3_bucket_path = isset($_POST['s3_bucket_path']) ? trim($_POST['s3_bucket_path']) : null;
    if (!$s3_bucket_path && is_array($settings) && isset($settings['s3_bucket_path'])) {
        $s3_bucket_path = $settings['s3_bucket_path'];
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

    $s3_delete_local = isset($_POST['s3_delete_local']) ? (int) $_POST['s3_delete_local'] : null;
    if (!$s3_delete_local && is_array($settings) && isset($settings['s3_delete_local'])) {
        $s3_delete_local = (int) $settings['s3_delete_local'];
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

    $s3_transfer_method = isset($_POST['s3_transfer_method']) ? trim($_POST['s3_transfer_method']) : null;
    if (!$s3_transfer_method && is_array($settings) && isset($settings['s3_transfer_method'])) {
        $s3_transfer_method = $settings['s3_transfer_method'];
    }

    if ($s3_transfer_method == null) {
        $s3_transfer_method = 's3class';
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
                            <th><label for="key"><?php _e("S3 Bucket Path:", 'S3MS' ); ?></label></th>
                            <td>
                                <input style="width:300px;" type="text" name="s3_bucket_path" value="<?php echo $s3_bucket_path;?>" placeholder="Enter Additional S3 Bucket Path e.g. blog or blog/assets"/>
                                <p class="description">If 'blog' is entered, uploads go to https://bucketname.s3.amazonaws.com/blog/YYYY/MM/file.ext </p>
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
                            <th><label for="key"><?php _e("Delete Local Files:", 'S3MS' ); ?></label></th>
                            <td>
                                <input type="checkbox" name="s3_delete_local" value="1" <?php echo ($s3_delete_local) ? 'checked="checked"' : '';?>/>
                                <p class="description">Whether or not to keep files uploaded locally</p>
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
                            <th><label for="key"><?php _e("CloudFront Domain Name:", 'S3MS' ); ?></label></th>
                            <td>
                                <input style="width:400px;" type="text" name="s3_cloudfront" value="<?php echo $s3_cloudfront;?>" placeholder="Enter CloudFront Domain Name"/>
                                <p class="description">e.g. abcslfn3kg17h.cloudfront.net</p>
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

    <div id="poststuff">
		<div class="postbox">
		<h3><?php _e('Library Files'); ?></h3>
            <div class="inside">
            <form id="S3MS-transfer" method="post" action="" enctype="multipart/form-data">
                <input type="hidden" name="transfer" value="1"/>
                <?php
                // While we could use get_posts and get_post_meta instead of a custom query, it would mean more queries/data than necessary
                // So lets just do our own query.
                global $wpdb;
                $page = isset($_GET['s3ms_page']) ? (int) $_GET['s3ms_page'] : 1;
                $limit = 100;
                $offset = ($limit * $page) - $limit;

                $sql = "SELECT COUNT(1) as count
                        FROM {$wpdb->posts}
                        LEFT JOIN {$wpdb->postmeta} ON {$wpdb->postmeta}.post_id = {$wpdb->posts}.ID AND {$wpdb->postmeta}.meta_key = 'S3MS_file'
                        WHERE 1=1
                            AND {$wpdb->posts}.post_type = 'attachment'
                            AND ({$wpdb->posts}.post_status = 'inherit')";
                $r = $wpdb->get_row($sql);
                $total = $r->count;

                $page_action = S3MS::paginationUrl();
                $pages = ceil($total / $limit);

                $sql = "SELECT ID, guid, meta_key, meta_value
                        FROM {$wpdb->posts}
                        LEFT JOIN {$wpdb->postmeta} ON {$wpdb->postmeta}.post_id = {$wpdb->posts}.ID AND {$wpdb->postmeta}.meta_key = 'S3MS_file'
                        WHERE 1=1
                            AND {$wpdb->posts}.post_type = 'attachment'
                            AND ({$wpdb->posts}.post_status = 'inherit')
                        ORDER BY {$wpdb->posts}.post_date DESC
                        LIMIT $offset,$limit";
                $files = $wpdb->get_results($sql);
                ?>
                <table class="wp-list-table widefat">
                    <thead>
                        <tr>
                            <th scope="col">Select All<input type="checkbox" id="select-all"/></span></th>
                            <th scope="col">Local File</span></th>
                            <th scope="col">Exists Locally?</th>
                            <th scope="col">S3 File</span></th>
                            <th scope="col">Exists On S3?</span></th>
                        </tr>
                    </thead>
                    <tfoot>
                        <tr>
                            <th colspan="5" scope="row"><p style="float:left"><?php echo S3MS::pagination($total, $limit, $page, $page_action);?></p>&nbsp;<p style="float:right">Page <?php echo number_format($page);?> of <?php echo number_format($pages);?> Total: <?php echo number_format($total);?> Files</p></th>
                        </tr>
                    </tfoot>
                    <tbody>
                        <?php $ud = wp_upload_dir();?>
                        <?php foreach ($files as $file):?>
                        <tr>
                            <td><input type="checkbox" class="files" name="selected[]" value="<?php echo $file->ID;?>"/></td>
                            <td><?php echo '<a href="'.$file->guid.'" target="_blank">'.$file->guid.'</a>';?></td>
                            <td><?php echo file_exists(str_replace($ud['baseurl'], $ud['basedir'], $file->guid)) ? '&#10003;' : '';?></td>
                            <td><?php $s3_url = S3MS::attachmentUrl($file->guid, $file->ID); echo $s3_url == $file->guid ? '' : '<a href="'.$s3_url.'" target="_blank">'.$s3_url.'</a>';?></td>
                            <td><?php echo $file->meta_value ? '&#10003;' : '';?></td>
                        </tr>
                        <?php endforeach;?>
                    </tbody>
                </table>

                <p class="submit">
                    <input type="submit" name="copy_files" class="button button-primary" value="<?php _e('Copy Files To S3');?>"> <input type="submit" name="move_files" class="button button-primary" value="<?php _e('Move Files To S3');?>">
                </p>
            </form>
            </div>
        </div>
	</div>
</div>
<script>
jQuery('#select-all').click(function(e) {
    if (jQuery(this).attr('checked') == 'checked') {
        jQuery(':input[type=checkbox].files').attr('checked', 'checked');
    } else {
        jQuery(':input[type=checkbox].files').attr('checked', null);
    }
});
</script>
<?php
}
