<?php
/**
 * Plugin Name: WordPress File Download Handler
 * Description: Tracks and handles download requests from visitors at WordPress enabled site.
 * Version: 1.0
 * Author: Wilson Tobar
 */

if(!class_exists('WP_List_Table')){
    require_once( ABSPATH . 'wp-admin/includes/class-wp-list-table.php' );
}

register_activation_hook(__FILE__, 'create_plugin_tables');

function create_plugin_tables() {
    global $wpdb;
    $charset_collate = $wpdb->get_charset_collate();

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

    $sql1 = "CREATE TABLE IF NOT EXISTS `{$wpdb->prefix}download_file_data` (
        `id` mediumint(9) NOT NULL AUTO_INCREMENT,
        `given_name` tinytext NOT NULL,
        `file_name` tinytext NOT NULL,
        `file_path` text NOT NULL,
        `time` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`)
    ) $charset_collate;";
    dbDelta($sql1);

    $sql2 = "CREATE TABLE IF NOT EXISTS `{$wpdb->prefix}download_user_data` (
        `id` mediumint(9) NOT NULL AUTO_INCREMENT,
        `name` tinytext NOT NULL,
        `email` varchar(320) NOT NULL,
        `affiliation` tinytext NOT NULL,
        `ipaddress` tinytext NOT NULL,
        `package` tinytext NOT NULL,
        `time` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`)
    ) $charset_collate;";
    dbDelta($sql2);
}

class Download_File_Data_List_Table extends WP_List_Table {
    function __construct(){
        parent::__construct([
            'singular' => __('File Data', 'download_handler'),
            'plural'   => __('File Data', 'download_handler'),
            'ajax'     => false
        ]);
    }

    function get_columns() {
        return [
            // 'cb'            => '<input type="checkbox" />',
            'given_name'    => __('Package Name', 'download_handler'),
            'file_name'     => __('File Name', 'download_handler'),
            'file_path'     => __('File Path', 'download_handler'),
            'time'          => __('Date and Time', 'download_handler'),
            'delete'        => __('Delete', 'download_handler'),
        ];
    }

    function prepare_items() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'download_file_data';
    
        $per_page = 10; // Number of items per page
        $current_page = $this->get_pagenum();
    
        // Handle search
        $search = '';
        if (isset($_REQUEST['s'])) {
            $search = sanitize_text_field($_REQUEST['s']);
        }
    
        // Set up pagination
        $total_items = $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE given_name LIKE '%%" . $search . "%%'");
        $this->set_pagination_args([
            'total_items' => $total_items,
            'per_page'    => $per_page
        ]);
    
        // Fetch data from the database with search and order by 'time' descending
        $data = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table_name WHERE given_name LIKE '%%" . $search . "%%' ORDER BY time DESC LIMIT %d OFFSET %d",
            $per_page,
            ($current_page - 1) * $per_page
        ), ARRAY_A);
    
        // Set the items for the table
        $this->items = $data;
    
        // Define the columns
        $columns = $this->get_columns();
        $hidden = []; // Optional, columns to hide
        $sortable = $this->get_sortable_columns();
    
        $this->_column_headers = [$columns, $hidden, $sortable];
    }
    

    function column_file_name($item) {
        return $item['file_name'];
    }

    function column_delete($item) {
        $delete_nonce = wp_create_nonce('delete_file');
        $delete_link = admin_url('admin-post.php?action=delete_file&file_id=' . absint($item['id']) . '&_wpnonce=' . $delete_nonce);
    
        return sprintf(
            '<a href="%s" class="button button-secondary delete-button">%s</a>',
            $delete_link,
            __('Delete', 'download_handler')
        );
    }

    function column_default($item, $column_name) {
        switch($column_name){
            case 'given_name':
                return sprintf(
                    '<span class="editable-given-name" data-id="%d" data-current-name="%s">%s</span> 
                    <a href="#" class="edit-given-name-link" data-id="%d">Edit</a>',
                    $item['id'],
                    esc_attr($item['given_name']),
                    esc_html($item['given_name']),
                    $item['id']
                );
            case 'file_path':
            case 'time':
                return $item[$column_name];
            case 'file_name':
                return $this->column_file_name($item); // Explicitly call custom method
            default:
                return print_r($item, true); // For debugging
        }
    }

    function search_box($text, $input_id) {
        $search_value = isset($_REQUEST['s']) ? esc_attr($_REQUEST['s']) : '';
        ?>
        <p class="search-box">
            <label class="screen-reader-text" for="<?php echo $input_id; ?>"><?php echo $text; ?>:</label>
            <input type="search" id="<?php echo $input_id; ?>" name="s" value="<?php echo $search_value; ?>" />
            <input type="submit" name="" id="search-submit" class="button" value="<?php echo esc_attr__('Search', 'download_handler'); ?>" />
        </p>
        <?php
    }
}

class Download_User_Data_List_Table extends WP_List_Table {

    function __construct(){
        parent::__construct([
            'singular' => __('User Data', 'download_handler'),
            'plural'   => __('User Data', 'download_handler'),
            'ajax'     => false
        ]);
    }

    function get_columns() {
        return [
            'cb'            => '<input type="checkbox" />',
            'name'          => __('Name', 'download_handler'),
            'email'         => __('Email', 'download_handler'),
            'affiliation'   => __('Affiliation', 'download_handler'),
            'package'       => __('Package', 'download_handler'),
            'ipaddress'     => __('IP Address', 'download_handler'),
            'time'          => __('Date and Time', 'download_handler')
        ];
    }

    function prepare_items() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'download_user_data';
    
        $per_page = 25; // Number of items per page
        $current_page = $this->get_pagenum();
    
        // Handle search
        $search = '';
        if (isset($_REQUEST['s'])) {
            $search = sanitize_text_field($_REQUEST['s']);
        }
    
        // Set up pagination
        $total_items = $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE name LIKE '%%" . $search . "%%'");
        $this->set_pagination_args([
            'total_items' => $total_items,
            'per_page'    => $per_page
        ]);
    
        // Fetch data from the database with search and order by 'time' descending
        $data = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table_name WHERE name LIKE '%%" . $search . "%%' ORDER BY time DESC LIMIT %d OFFSET %d",
            $per_page,
            ($current_page - 1) * $per_page
        ), ARRAY_A);
    
        // Set the items for the table
        $this->items = $data;
    
        // Define the columns
        $columns = $this->get_columns();
        $hidden = []; // Optional, columns to hide
        $sortable = $this->get_sortable_columns();
    
        $this->_column_headers = [$columns, $hidden, $sortable];
    }    

    // Render the checkbox column
    function column_cb($item) {
        return sprintf(
            '<input type="checkbox" name="bulk-action[]" value="%s" />',
            $item['id']
        );
    }

    function column_default($item, $column_name) {
        switch($column_name){
            case 'name':
            case 'email':
            case 'affiliation':
            case 'ipaddress':
            case 'package':
            case 'time':
                return $item[$column_name];
            default:
                return print_r($item, true);
        }
    }

    function get_bulk_actions() {
        $actions = array(
            'bulk-delete' => 'Delete',
            'bulk-export' => 'Export',
        );
        return $actions;
    }

    // Handle bulk action (delete)
    function process_bulk_action() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'download_user_data';

        if ('bulk-delete' === $this->current_action()) {
            $ids = isset($_REQUEST['bulk-action']) ? $_REQUEST['bulk-action'] : [];

            if (empty($ids)) return;

            foreach ($ids as $id) {
                $wpdb->delete($table_name, ['id' => $id], ['%d']);
            }

            // Display a success message and refresh page after timeout
            echo '<div class="notice notice-success"><p>Entries deleted successfully!<br>Page will refresh shortly...</p></div>';
            ?>
            <script>
                setTimeout(function(){ window.location.reload(true); }, 2500);
            </script>
            <?php
            exit;
        }
    }

    function search_box($text, $input_id) {
        $search_value = isset($_REQUEST['s']) ? esc_attr($_REQUEST['s']) : '';
        ?>
        <p class="search-box">
            <label class="screen-reader-text" for="<?php echo $input_id; ?>"><?php echo $text; ?>:</label>
            <input type="search" id="<?php echo $input_id; ?>" name="s" value="<?php echo $search_value; ?>" />
            <input type="submit" name="" id="search-submit" class="button" value="<?php echo esc_attr__('Search', 'download_handler'); ?>" />
        </p>
        <?php
    }
}

function download_user_data_csv() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'download_user_data';

    if (isset($_POST['ids']) && !empty($_POST['ids'])) {
        $ids = array_map('intval', $_POST['ids']);

        // Generate CSV file content
        $csv_output = '';
        $csv_output .= '"Name","Email","Affiliation","IP Address","Package","Time"' . "\n";

        foreach ($ids as $id) {
            $entry_data = $wpdb->get_row($wpdb->prepare("SELECT name, email, affiliation, ipaddress, package, time FROM $table_name WHERE id = %d", $id), ARRAY_A);
            if ($entry_data) {
                $csv_output .= '"' . implode('","', $entry_data) . '"' . "\n";
            }
        }

        // Save CSV content to a temporary file
        $temp_file = tempnam(sys_get_temp_dir(), 'CSV');
        file_put_contents($temp_file, $csv_output);

        // Send back the temporary file URL
        echo admin_url('admin-ajax.php?action=download_csv&file=' . basename($temp_file));
    } else {
        echo 'Error: No data available for export';
    }

    wp_die(); // This is required to terminate immediately and return a proper response
}
add_action('wp_ajax_download_user_data_csv', 'download_user_data_csv');

function export_all_user_data() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'download_user_data';

    if (isset($_POST['export_all_nonce']) && wp_verify_nonce($_POST['export_all_nonce'], 'export_all_user_data')) {
        // Generate CSV file content
        $csv_output = '';
        $csv_output .= '"Name","Email","Affiliation","IP Address","Package","Time"' . "\n";

        $entries = $wpdb->get_results("SELECT name, email, affiliation, ipaddress, package, time FROM $table_name", ARRAY_A);
        foreach ($entries as $entry) {
            $csv_output .= '"' . implode('","', $entry) . '"' . "\n";
        }

        // Set headers for file download
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="exported_data.csv"');

        // Output the CSV content
        echo $csv_output;
        exit;
    } else {
        echo 'Error: Invalid nonce.';
    }

    wp_die(); // This is required to terminate immediately and return a proper response
}
add_action('wp_ajax_export_all_user_data', 'export_all_user_data');

function serve_csv_file() {
    $temp_file = sys_get_temp_dir() . '/' . $_GET['file'];

    if (file_exists($temp_file)) {
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="exported_data.csv"');
        readfile($temp_file);
        unlink($temp_file); // Optional, delete the file after serving
    } else {
        wp_die('Error: File not found.', 404);
    }

    exit;
}
add_action('wp_ajax_download_csv', 'serve_csv_file');

function display_download_requests() {
    $userdataListTable = new Download_User_Data_List_Table();
    $userdataListTable->prepare_items();

    if (isset($_REQUEST['action'])) {
        // Check if the selected action is "bulk-delete" or "bulk-export"
        if ($_REQUEST['action'] == 'bulk-delete') {
            $userdataListTable->process_bulk_action('bulk-delete');
        } elseif ($_REQUEST['action'] == 'bulk-export') {
            $userdataListTable->process_bulk_action('bulk-export');
        }
    }

    ?>
    <div class="wrap">
        <h2><?php _e('Download Requests', 'download_handler'); ?></h2>

        <form method="post" action="<?php echo admin_url('admin-ajax.php'); ?>">
            <input type="submit" class="button-primary" value="<?php _e('Export All', 'download_handler'); ?>">
            <input type="hidden" name="action" value="export_all_user_data">
            <?php wp_nonce_field('export_all_user_data', 'export_all_nonce'); ?>
        </form>

        <form method="post" id="bulk-action-form">
            <?php
            $userdataListTable->search_box('Search', 'search_id');
            $userdataListTable->display();
            ?>
        </form>

        <form method="post" action="<?php echo admin_url('admin-ajax.php'); ?>">
            <input type="submit" class="button-primary" value="<?php _e('Export All', 'download_handler'); ?>">
            <input type="hidden" name="action" value="export_all_user_data">
            <?php wp_nonce_field('export_all_user_data', 'export_all_nonce'); ?>
        </form>
    </div>

    <script type="text/javascript">
        document.getElementById('bulk-action-form').addEventListener('submit', function(e) {
            var action = document.querySelector('[name="action"]').value;
            if (action === 'bulk-delete') {
                var confirmDeletion = confirm("Are you sure you want to delete the selected entries?");
                if (!confirmDeletion) {
                    e.preventDefault();
                }
            } else if (action === 'bulk-export') {
                e.preventDefault();
                var ids = jQuery('input[name="bulk-action[]"]:checked').map(function() { return this.value; }).get();

                jQuery.ajax({
                    type: "POST",
                    url: ajaxurl,
                    data: {
                        action: 'download_user_data_csv',
                        ids: ids
                    },
                    success: function(response) {
                        window.location = response;
                    },
                    error: function() {
                        alert('Error: Could not export data.');
                    }
                });
            }
        });
    </script>
    <?php
}

function handle_delete_file_action() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'download_file_data';
    
    if (isset($_GET['action'], $_GET['file_id'], $_GET['_wpnonce']) && $_GET['action'] == 'delete_file') {
        if (!wp_verify_nonce($_GET['_wpnonce'], 'delete_file')) {
            die('Nonce verification failed');
        }

        $file_id = absint($_GET['file_id']);
        $file_path = ABSPATH . $wpdb->get_var($wpdb->prepare("SELECT file_path FROM $table_name WHERE id = %d", $file_id));

        // Perform the delete operation
        $wpdb->delete($table_name, array('id' => $file_id));

        // Delete the file from the server
        if (file_exists($file_path)) {
            unlink($file_path);
        }

        wp_redirect(admin_url('admin.php?page=uploads'));
        exit;
    }
}
add_action('admin_init', 'handle_delete_file_action');

function display_uploads() {   
    $filedataListTable = new Download_File_Data_List_Table();
    $filedataListTable->prepare_items();
    handle_file_upload();

    ?>
    <div class="wrap">
        <form method="post" id="search-form">
            <?php
            $filedataListTable->search_box('Search', 'search_id');
            ?>
        </form>
    </div>

    <div class="wrap">
        <h2>
            <?php _e('Uploads', 'download_handler'); ?>
            <a href="<?php echo admin_url('admin.php?page=upload-new-file'); ?>" class="page-title-action"><?php _e('Upload New File', 'download_handler'); ?></a>
        </h2>
        <form method="post">
            <?php $filedataListTable->display(); ?>
        </form>
    </div>
    <?php
}

function display_upload_item_form() {
    handle_file_upload();
    
    ?>
    <div class="wrap">
        <h2>
            <?php _e('Upload New File', 'download_handler'); ?>
        </h2>
        <form method="post" action="" enctype="multipart/form-data">
            <?php wp_nonce_field('custom_file_upload', 'custom_file_upload_nonce'); ?>

            <div class="form-group">
                <label for="given_name_text"><?php _e('File Name:', 'download_handler'); ?></label>
                <input type="text" name="given_name" id="given_name_text" placeholder="Give the file a name" required>
            </div>

            <div class="form-group">
                <label for="custom_file"><?php _e('Select a File:', 'download_handler'); ?></label>
                <input type="file" name="custom_file" id="custom_file" required>
            </div>

            <input type="submit" name="submit_custom_file" value="Upload" class="button-primary">
        </form>
    </div>
    <?php
}

function handle_file_upload() {
    if (isset($_POST['submit_custom_file'], $_POST['given_name']) && isset($_FILES['custom_file'])) {
        if (wp_verify_nonce($_POST['custom_file_upload_nonce'], 'custom_file_upload')) {
            $file = $_FILES['custom_file'];
            $given_name = sanitize_text_field($_POST['given_name']);

            // Handle file upload
            $upload = wp_handle_upload($file, array('test_form' => false));

            if (isset($upload['file'])) {
                // File is uploaded successfully
                $file_name = basename($upload['file']);

                // Get the relative path from ABSPATH
                $relative_path = str_replace(ABSPATH, '', $upload['file']);

                // Store file info in the database
                global $wpdb;
                $table_name = $wpdb->prefix . 'download_file_data';
                $wpdb->insert($table_name, array(
                    'given_name' => $given_name,
                    'file_name' => $file_name,
                    'file_path' => $relative_path
                ));

                // Display a success message
                echo '<div class="notice notice-success"><p>File uploaded successfully!<br>Page will refresh shortly...</p></div>';

                // Add JavaScript to redirect after timeout
                $redirect_url = admin_url('admin.php?page=uploads'); // Redirect URL
                echo '<script>';
                echo 'setTimeout(function(){ window.location.href = "' . esc_js($redirect_url) . '"; }, 2500);';
                echo '</script>';
            } else {
                // Handle the error
                echo '<div class="notice notice-error"><p>Error in file upload.</p></div>';
            }
        }
    }
}

function custom_mime_types($mime_types) {
    $mime_types['tar'] = 'application/x-tar';
    $mime_types['bz2'] = 'application/x-bzip2';
    $mime_types['tbz'] = 'application/x-bzip-compressed-tar';
    $mime_types['tbz2'] = 'application/x-bzip-compressed-tar';

    return $mime_types;
}
add_filter('upload_mimes', 'custom_mime_types');

function update_given_name() {
    global $wpdb;

    if (isset($_POST['file_id'], $_POST['new_name'])) {
        $file_id = intval($_POST['file_id']);
        $new_name = sanitize_text_field($_POST['new_name']);

        $table_name = $wpdb->prefix . 'download_file_data';
        $updated = $wpdb->update(
            $table_name,
            array('given_name' => $new_name),
            array('id' => $file_id),
            array('%s'),
            array('%d')
        );

        if ($updated !== false) {
            wp_send_json_success();
        }
    }

    wp_send_json_error();
}
add_action('wp_ajax_update_given_name', 'update_given_name');
add_action('wp_ajax_nopriv_update_given_name', 'update_given_name');

function add_download_requests_menu_item() {
    add_menu_page(
        'Download Requests', // Page title
        'Download Requests', // Menu title
        'manage_options', // Capability
        'download-requests', // Menu slug
        'display_download_requests', // Function to display the download requests data.
        'dashicons-arrow-down-alt', // Icon
        6 // Position
    );
}
add_action('admin_menu', 'add_download_requests_menu_item');

function add_uploads_menu_item() {
    add_menu_page(
        'Uploads', // Page title
        'Uploads', // Menu title
        'manage_options', // Capability
        'uploads', // Menu slug
        'display_uploads', // Function to display the uploads data.
        'dashicons-arrow-up-alt', // Icon
        7 // Position
    );
}
add_action('admin_menu', 'add_uploads_menu_item');

function add_uploads_submenu_item() {
    add_submenu_page(
        'uploads', // Parent slug (the main menu under which this submenu will appear)
        'Upload New File', // Page title
        'Upload New File', // Menu title
        'manage_options', // Capability
        'upload-new-file', // Menu slug
        'display_upload_item_form' // Function to display the upload file form
    );
}
add_action('admin_menu', 'add_uploads_submenu_item');

// Form submit handle function for downloads
function handle_download_form_submission() {
    if (isset($_POST['name'], $_POST['email'], $_POST['affiliation'], $_POST['package']) && check_admin_referer('custom_form_nonce')) {
		// Sanitize received data to prevent SQL injection
        $name = sanitize_text_field($_POST['name']);
        $email = sanitize_email($_POST['email']);
        $affiliation = sanitize_text_field($_POST['affiliation']);
        $package = sanitize_text_field($_POST['package']);
        $ip_address = $_SERVER['REMOTE_ADDR'];
		
        // Save form data to the database
		global $wpdb;
		$table_name = $wpdb->prefix . 'download_user_data';

        $wpdb->insert($table_name, array(
            'name' => $name,
            'email' => $email,
            'affiliation' => $affiliation,
			'package' => $package,
            'ipaddress' => $ip_address
        ));
		
        // Retrieve the file URL from the database based on the package
        $file_data = $wpdb->get_row($wpdb->prepare(
            "SELECT file_path FROM {$wpdb->prefix}download_file_data WHERE given_name = %s",
            $package
        ));

        $file_path = ABSPATH . $file_data->file_path;

        if ($file_data && file_exists($file_path)) {
            // Serve the file
            header('Content-Description: File Transfer');
            header('Content-Type: application/octet-stream');
            header('Content-Disposition: attachment; filename="' . basename($file_path) . '"');
            header('Expires: 0');
            header('Cache-Control: must-revalidate');
            header('Pragma: public');
            header('Content-Length: ' . filesize($file_path));
            readfile($file_path);
            exit;
        } else {
            // File not found, handle the error
            wp_redirect(home_url('/file-not-found')); // Redirect to a file not found page
            exit;
        }
    } else {
        // Log an error if the nonce check fails
        wp_redirect(home_url('/form-error')); // Redirect to a form error page
        exit;
    }
}
add_action('admin_post_custom_form_action', 'handle_download_form_submission');
add_action('admin_post_nopriv_custom_form_action', 'handle_download_form_submission'); // For logged out users

function download_request_form_shortcode() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'download_file_data';

    $packages = $wpdb->get_results("SELECT DISTINCT given_name FROM $table_name", ARRAY_A);

    $form_html = '<form action="' . esc_url(admin_url('admin-post.php')) . '" method="post" class="download-form-class">
        <input type="hidden" name="action" value="custom_form_action">
        <label for="package_name">Package:</label>
        <select name="package" id="package_name">';

    foreach ($packages as $package) {
        $form_html .= '<option value="' . esc_attr($package['given_name']) . '">' . esc_html($package['given_name']) . '</option>';
    }

    $form_html .= '</select>
    <br>
    <br>
    <label>Please kindly provide your information for our reference:</label>
    <br>
    <br>
    <label for="name_text">*Required Fields</label>
    <br>
    <br>
    <div class="form-container">
        <div class="form-row">
            <label for="name_text">*Name:</label>
            <input type="text" name="name" placeholder="Your Name" id="name_text" required>
        </div>
        <div class="form-row">
            <label for="email_text">*Email:</label>
            <input type="email" name="email" placeholder="Your Email" id="email_text" required>
        </div>
        <div class="form-row">
            <label for="affiliation_text">*Affiliation:</label>
            <input type="text" name="affiliation" placeholder="Your Affiliation" id="affiliation_text" required>
        </div>
    </div>	
    <br>
    <input type="checkbox" name="gdpr_consent" id="gdpr_consent" required>
    <label for="gdpr_consent">*I consent to having website store my submitted information so they can respond to my inquiry.</label>
    <br>
    <br>
    <div class="button-container">
        <button aria-label="Download" class="wp-block-search__button has-small-font-size wp-element-button" type="submit">Download</button>
    </div>';
    $form_html .= wp_nonce_field('custom_form_nonce', '_wpnonce', true, false);
    $form_html .= '</form>';

    return $form_html;
}
add_shortcode('download_request_form', 'download_request_form_shortcode');

function enqueue_scripts() {
    ?>
    <script type="text/javascript">
        jQuery(document).ready(function($) {
            $('.column-delete a').click(function() {
                return confirm("<?php _e('Are you sure you want to delete this file?', 'download_handler'); ?>");
            });
        });

        jQuery(document).ready(function($) {
            $('.edit-given-name').on('click', function() {
                var $row = $(this).closest('tr');
                var $span = $row.find('.editable-given-name');
                var currentName = $span.data('current-name');
                var newName = prompt('Enter a new name:', currentName);

                if (newName !== null) {
                    // Update the span element and data attribute
                    $span.text(newName).data('current-name', newName);
                    var fileId = $span.data('id');

                    // Send an AJAX request to save the updated name
                    $.ajax({
                        type: 'POST',
                        url: ajaxurl, // WordPress AJAX URL
                        data: {
                            action: 'update_given_name',
                            file_id: fileId,
                            new_name: newName,
                        },
                        success: function(response) {
                            if (response.success) {
                                // Successfully updated the given_name
                                alert('Name updated successfully!');
                            } else {
                                alert('Error updating name.');
                            }
                        }
                    });
                }
            });
        });
    </script>
    <?php
}
add_action('admin_footer', 'enqueue_scripts');

function enqueue_styles() {
    ?>
    <style>
        /* Red color for the "Delete" button */
        .delete-button {
            background-color: #ff0000 !important; /* Red background color */
            color: #ffffff !important; /* White text color */
            border: 1px solid #ff0000 !important; /* Red border */
        }

        /* Hover effect for the "Delete" button */
        .delete-button:hover {
            background-color: #ff3333 !important; /* Darker red on hover */
        }
    </style>
    <?php
}
add_action('admin_head', 'enqueue_styles');

