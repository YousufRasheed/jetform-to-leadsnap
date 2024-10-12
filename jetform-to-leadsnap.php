<?php

/**
 * Plugin Name: JetForm to Leadsnap Integration
 * Description: Sends JetFormBuilder form submissions to Leadsnap. Provides settings to configure the API Key and Source ID.
 * Version: 1.0
 */

// Hook to send form data after form submission
add_action('jet-form-builder/form-handler/after-send', 'jfl_send_to_leadsnap');
function jfl_send_to_leadsnap($form)
{
    if (!$form->is_success) {
        return;
    }

    // Get the API key and source ID from plugin settings
    $api_key = get_option('jfl_api_key', '');
    $source_id = get_option('jfl_source_id', '');

    // Define the log file path in the plugin directory
    $log_file = plugin_dir_path(__FILE__) . 'error-log.txt';

    if (empty($api_key) || empty($source_id)) {
        error_log('Leadsnap API key or Source ID is not configured.' . PHP_EOL, 3, $log_file);
        return;
    }

    // Get the full page URL where the form was submitted
    $full_page_url = home_url($_POST['_wp_http_referer']);

    // Prepare the data to send to Leadsnap
    $data = array(
        'email' => $_POST['email'] ?? '',
        'phone' => $_POST['phone'] ?? '',
        'name' => $_POST['name'] ?? '',
        'address' => $_POST['address'] ?? '',
        'source' => array(
            'id' => intval($source_id),
            'website' => get_site_url(),
            'name' => get_bloginfo('name')
        ),
        'additional_data' => array(),
        'meta' => array(
            'form_title' => 'Contact Form',
            'post_url' => $full_page_url,
            'remote_ip' => $_SERVER['REMOTE_ADDR'],
            'plugin_name' => 'jet_form_builder_to_leadsnap',
        ),
    );

    // Exclude unnecessary fields and add remaining fields to additional_data
    $exclude_fields = [
        '_wpnonce',
        '_wp_http_referer',
        '_jfb_current_render_states',
        '__queried_post_id',
        '_jet_engine_booking_form_id',
        '_jet_engine_refer',
        'email',
        'phone',
        'name',
        'address'
    ];
    foreach ($_POST as $key => $value) {
        if (!in_array($key, $exclude_fields)) {
            $data['additional_data'][$key] = array(
                'value' => $value,
                'name' => $key,
                'basetype' => 'text',
                'raw_values' => array(),
                'label' => ucfirst($key),
                'show' => 'show'
            );
        }
    }

    // Send the data to Leadsnap API
    $args = array(
        'body' => json_encode($data),
        'headers' => array(
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
            'Thrive-Api-Key' => $api_key
        ),
        'timeout' => 30,
    );

    $response = wp_remote_post('https://app.leadsnap.com/public/api/lead/create', $args);

    if (is_wp_error($response)) {
        // Log errors to the custom log file
        error_log('Leadsnap API request failed: ' . $response->get_error_message() . PHP_EOL, 3, $log_file);
    } else {
        $response_code = wp_remote_retrieve_response_code($response);
        if ($response_code !== 200) {
            // Log non-200 responses to the custom log file
            error_log('Leadsnap API responded with code: ' . $response_code . PHP_EOL, 3, $log_file);
        }
    }
}

// Add settings page
add_action('admin_menu', 'jfl_add_settings_page');
function jfl_add_settings_page()
{
    add_options_page(
        'JetForm to Leadsnap Settings',
        'JetForm to Leadsnap',
        'manage_options',
        'jfl-settings',
        'jfl_settings_page_content'
    );
}

// Display the settings page content
function jfl_settings_page_content()
{
    // Get the plugin directory URL for assets
    $plugin_url = plugin_dir_url(__FILE__);
    $jetform_img_url = $plugin_url . 'assets/jetform.png';
    $leadsnap_img_url = $plugin_url . 'assets/leadsnap.png';
?>
    <div class="wrap">
        <div style="
            padding: 20px;
            border-radius: 5px;
            background-color: #1CBB9C!important;
        ">
            <div style="display: flex; align-items: center; gap: 20px;">
                <img src="<?php echo esc_url($jetform_img_url); ?>" alt="JetForm" style="height: 40px; max-width: 150px; object-fit: contain; background-color: white; padding: 5px; border-radius: 5px">
                <img src="<?php echo esc_url($leadsnap_img_url); ?>" alt="Leadsnap" style="height: 40px; max-width: 150px; object-fit: contain; background-color: #2B3E54; padding: 5px; border-radius: 5px">
            </div>
            <h1 style="color: white; text-transform: uppercase; font-weight: 700;">JetForm to Leadsnap Settings</h1>
        </div>
        <form
            method="post"
            action="options.php"
            style="
                margin-top: 20px;
                padding: 20px;
                border-radius: 5px;
                border: 1px solid #ddd;
                background-color: #f9f9f9;
                box-shadow: 0 0 10px rgba(0, 0, 0, 0.03);
            ">
            <?php
            settings_fields('jfl_settings_group');
            do_settings_sections('jfl-settings');
            submit_button('Save Changes &rArr;', 'primary', '', true, [
                'style' => 'background-color: #1CBB9C; color: white; border-radius: 5px; border-color: #1CBB9C;'
            ]);

            ?>
        </form>
    </div>
<?php
}

// Register settings for API key and Source ID
add_action('admin_init', 'jfl_register_settings');
function jfl_register_settings()
{
    register_setting('jfl_settings_group', 'jfl_api_key');
    register_setting('jfl_settings_group', 'jfl_source_id');

    add_settings_section(
        'jfl_settings_section',
        'API Settings',
        null,
        'jfl-settings'
    );

    add_settings_field(
        'jfl_api_key',
        'Leadsnap API Key',
        'jfl_api_key_field_html',
        'jfl-settings',
        'jfl_settings_section'
    );

    add_settings_field(
        'jfl_source_id',
        'Leadsnap Source ID',
        'jfl_source_id_field_html',
        'jfl-settings',
        'jfl_settings_section'
    );
}

// API Key Field HTML
function jfl_api_key_field_html()
{
    $api_key = get_option('jfl_api_key', '');
    echo '<input type="text" name="jfl_api_key" value="' . esc_attr($api_key) . '" class="regular-text">';
}

// Source ID Field HTML
function jfl_source_id_field_html()
{
    $source_id = get_option('jfl_source_id', 8115); // Default source ID 8115
    echo '<input type="text" name="jfl_source_id" value="' . esc_attr($source_id) . '" class="regular-text">';
}
