<?php
/*
Plugin Name: Serial Checker Plugin
Description: A plugin to verify serial numbers and track visits.
Version: 1.1
Author: Donia Alhosin
*/


date_default_timezone_set(get_option('timezone_string', 'UTC'));
// Activation Hook: Create Database Table
function create_serial_table() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'serial_numbers';
    $charset_collate = $wpdb->get_charset_collate();
    $sql = "CREATE TABLE $table_name (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        serial_number varchar(50) NOT NULL UNIQUE,
        status varchar(20) DEFAULT 'unverified',
        date_of_verification datetime DEFAULT NULL,
        last_visit datetime DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY  (id)
    ) $charset_collate;";
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}
register_activation_hook(__FILE__, 'create_serial_table');

// Add Admin Menu
function serial_checker_admin_menu() {
    add_menu_page(
        'Serial Checker',              
        'Serial Checker',              
        'manage_options',              
        'serial-checker',              
        'serial_checker_admin_page'    
    );
    add_submenu_page(
        'serial-checker',
        'Manage Serial Numbers',
        'Manage Serials',
        'manage_options',
        'manage-serials',
        'manage_serial_numbers_page'
    );
}
add_action('admin_menu', 'serial_checker_admin_menu');

// Admin Page Content
function serial_checker_admin_page() {
    ?>
    
    <div class="wrap">
        <h1>Serial Checker Import/Export</h1>
        
        <!-- Import Section -->
        <h2>Import Serial Numbers</h2>
        <form method="post" enctype="multipart/form-data">
            <?php wp_nonce_field('import_serial_action', 'import_serial_nonce'); ?>
            <input type="file" name="import_file" accept=".csv" required>
            <input type="submit" name="import_serials" class="button button-primary" value="Import">
        </form>

        <!-- Export Section -->
        <h2>Export Serial Numbers</h2>
        <form method="post">
            <?php wp_nonce_field('export_serial_action', 'export_serial_nonce'); ?>
            <input type="submit" name="export_serials" class="button button-secondary w-100"  value="Export">
        </form>
    </div>
    <?php

    // Handle Import
    if (isset($_POST['import_serials'])) {
        check_admin_referer('import_serial_action', 'import_serial_nonce');
        import_serial_numbers();
    }

    // Handle Export
    if (isset($_POST['export_serials'])) {
        check_admin_referer('export_serial_action', 'export_serial_nonce');
        export_serial_numbers();
    }

    if (isset($_POST['save_email'])) {
        check_admin_referer('save_email_setting', 'email_setting_nonce');
        update_option('serial_checker_email', sanitize_email($_POST['admin_email']));
        echo "<div class='updated'><p>Email setting saved successfully.</p></div>";
    }
}

// Import Functionality
function import_serial_numbers() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'serial_numbers';

    if ($_FILES['import_file']['error'] === UPLOAD_ERR_OK) {
        $file = fopen($_FILES['import_file']['tmp_name'], 'r');
        while (($data = fgetcsv($file, 1000, ',')) !== FALSE) {
            $serial_number = sanitize_text_field($data[0]);
            if (!empty($serial_number)) {
                $wpdb->insert($table_name, array(
                    'serial_number' => $serial_number,
                    'status' => 'unverified'
                ), array('%s', '%s'));
            }
        }
        fclose($file);
        echo "<div class='updated'><p>Serial numbers imported successfully.</p></div>";
    } else {
        echo "<div class='error'><p>Error uploading file.</p></div>";
    }
}

// Export Functionality
function export_serial_numbers() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'serial_numbers';
    $results = $wpdb->get_results("SELECT * FROM $table_name");


    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="serial_numbers.csv"');
    header('Pragma: no-cache');
    header('Expires: 0');

  
    $output = fopen('php://output', 'w');

    
    fputcsv($output, array('ID', 'Serial Number', 'Status', 'Date of Verification', 'Last Visit'));


    foreach ($results as $row) {
        fputcsv($output, array(
            $row->id,
            $row->serial_number,
            $row->status,
            $row->date_of_verification,
            $row->last_visit
        ));
    }

    fclose($output); 
    exit(); 
}

function manage_serial_numbers_page() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'serial_numbers';

    $query = "SELECT * FROM $table_name WHERE 1=1";

    if (isset($_GET['status'])) {
        $query .= $wpdb->prepare(" AND status = %s", sanitize_text_field($_GET['status']));
    }

    $results = $wpdb->get_results($query);

    echo "<form method='get'>";
    echo "<input type='hidden' name='page' value='manage-serials'>";
    echo "<select name='status'>
            <option value=''>-- Select Status --</option>
            <option value='verified'>Verified</option>
            <option value='unverified'>Unverified</option>
          </select>";
    echo "<button type='submit'>Filter</button>";
    echo "</form>";

    echo "<table class='wp-list-table widefat fixed'>";
    echo "<thead><tr><th>ID</th><th>Serial Number</th><th>Status</th><th>Date of Verification</th><th>Last Visit</th></tr></thead>";
    echo "<tbody>";
    foreach ($results as $row) {
        echo "<tr>
            <td>{$row->id}</td>
            <td>{$row->serial_number}</td>
            <td>{$row->status}</td>
            <td>{$row->date_of_verification}</td>
            <td>{$row->last_visit}</td>
          </tr>";
    }
    echo "</tbody>";
    echo "</table>";
}

function serial_checker_form() {
    ob_start(); ?>

    <!-- Include Bootstrap CSS -->
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.1/css/all.min.css" />

    <style>
        /* Full Page Centering */
        .full-page-container {
            display: flex;
            justify-content: center;
            align-items: center;
       
   
        }

        /* Form Container Styling */
        .serial-checker-form-container {
            border: 2px solid #5a7caa;
            border-radius: 20px;
            padding: 30px;
            background: #ffffff;
            box-shadow: 0 8px 15px rgba(0, 0, 0, 0.1);
            width: 100%;
            max-width: 400px;
            text-align: center;
        }

        .serial-checker-form input {
            border-radius: 25px;
        }

        .serial-checker-form button {
            border-radius: 25px;
        }

        .alert {
            border-radius: 15px;
        }

        .sealy-logo {
            max-height: 100px;
            margin-bottom: 20px;
        }
    </style>

    <div class="full-page-container">
        <!-- Form Container -->
        <div class="serial-checker-form-container">
            <!-- Sealy Logo -->
            <div class="text-center">
                <img src="https://limegreen-mouse-524899.hostingersite.com/wp-content/uploads/2024/11/WhatsApp-Image-2024-06-27-at-13.08.38_e4aca2be-1.png" 
                     alt="Sealy Logo" 
                     class="sealy-logo img-fluid">
            </div>

            <!-- Form Title -->
            <h2 class="mb-4">التحقق من الرقم التسلسلي</h2>

            <!-- Verification Form -->
            <form method="post" class="serial-checker-form">
                <?php wp_nonce_field('verify_serial_action', 'verify_serial_nonce'); ?>
                <div class="form-group">
                    <input type="text" name="serial_code" class="form-control text-right" placeholder="أدخل الرقم التسلسلي" required>
                </div>
                <button type="submit" name="check_serial" class="btn btn-primary btn-block">تحقق</button>
            </form>

            <?php
            if (isset($_POST['check_serial']) && check_admin_referer('verify_serial_action', 'verify_serial_nonce')) {
                global $wpdb;
                $serial = sanitize_text_field($_POST['serial_code']);
                $table_name = $wpdb->prefix . 'serial_numbers';

                $result = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE serial_number = %s", $serial));

                echo '<div class="mt-4">';
                if ($result) {
                    // Card for verified status
                    echo '<div class="card text-center p-4">';
                    if ($result->status === 'unverified') {
                        $wpdb->update(
                            $table_name,
                            array(
                                'status' => 'verified',
                                'date_of_verification' => current_time('mysql')
                            ),
                            array('serial_number' => $serial)
                        );
                        echo '<div class="alert alert-success">     !الرقم التسلسلي تم التحقق منه بنجاح   <i class="fas fa-check-circle"></i>   </div>';
                    } else {
                        echo ' <div class="alert alert-warning">   تم التحقق من هذا الرقم التسلسلي مسبقاً. <i class="fas fa-exclamation-circle"></i></div>';
                        echo '<div class="status-info text-right">';
                        echo '<p><strong>تاريخ التحقق:</strong> ' . date_i18n('Y-m-d H:i:s', strtotime($result->date_of_verification)) . '</p>';
                        
                        // Display last visit if available
                        if (!empty($result->last_visit)) {
                            echo '<p><strong>آخر زيارة:</strong> ' . date_i18n('Y-m-d H:i:s', strtotime($result->last_visit)) . '</p>';
                        }
                        echo '</div>';
                    }
                    echo '</div>'; // Close card div
                } else {
                    echo '<div class="alert alert-danger text-center"> الرقم التسلسلي غير صحيح. <i class="fas fa-times-circle"></i> </div>';
                }
                echo '</div>'; 
            }
            ?>
        </div>
    </div>

    <?php return ob_get_clean();
}




add_shortcode('serial_checker', 'serial_checker_form');
?>
