<?php

/*
Plugin Name: Better OptimizeMember API
Plugin URI: https://bureauram.nl
Description: A better version of the OptimizeMember API, specially tailored for the Dutch service Autorespond.
Version: 0.6.3
Author: Rick Heijster @ Bureau RAM
Author URI: https://bureauram.nl
License: GPL2
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Text Domain: better-om-api
*/

if (!defined('BOA_VERSION_KEY'))
    define('BOA_VERSION_KEY', 'boa_version');

if (!defined('BOA_VERSION_NUM'))
    define('BOA_VERSION_NUM', '0.6.3');

/* !0. TABLE OF CONTENTS */

/*
	
	1. HOOKS

	2. SHORTCODES

	3. FILTERS
		3.1 boa_admin_menus()
		3.2 boa_plugin_action_links()

	4. EXTERNAL SCRIPTS
        4.1 boa_custom_css()
		
	5. ACTIONS
		5.1 boa_install()
        5.2 boa_upgrade()
        5.3 boa_update_check()
        5.4 boa_create_tables()
        5.5 boa_log_event()
        5.6 boa_show_log()

	6. HELPERS
		6.1 boa_check_is_om_active()
        6.2 boa_get_yesno_select()
        6.3 boa_get_current_options()
        6.4 boa_send_email_confirmation()
        6.5 boa_mail_contents()
		6.6 boa_register_user_data()
        6.7 boa_register_user_data()

	7. CUSTOM POST TYPES
	
	8. ADMIN PAGES
		8.1 boa_admin_page() - Main Admin Page

	9. SETTINGS
        9.1 boa_register_options()

    10. API
        10.1 Add User
        10.2 Add level
        10.3 Add Package
        10.4 Delete Package
        10.5 API

*/

/* !1. HOOKS */
// hint: register our custom menus
add_action('admin_menu', 'boa_admin_menus');

// hint: register plugin options
add_action('admin_init', 'boa_register_options');

// hint: register custom css
add_action('admin_head', 'boa_custom_css');

// hint: put the API in the loop
add_action('init', 'boa_better_om_api');

// hint: run install/upgrade
register_activation_hook( __FILE__, 'boa_install' );
add_action( 'plugins_loaded', 'boa_update_check' );

// hint: fire download when clicked on link in admin page
add_action('wp_ajax_boa_show_log', 'boa_show_log'); // admin users

// hint: Add settings link to Plugin page
add_filter('plugin_action_links', 'boa_plugin_action_links', 10, 2);

/* !2. SHORTCODES */

/* !3. FILTERS */

// 3.1
// hint: Registers custom plugin admin menus
// Since: 0.5.0
function boa_admin_menus() {

    $top_menu_item = 'boa_admin_page';
    add_submenu_page( 'options-general.php', 'Better OptimizeMember API', 'Better OptimizeMember API', 'manage_options', $top_menu_item, $top_menu_item );

}

// 3.2
// hint: add Settings link to Plugins page
// Sinrce 0.5.0
function boa_plugin_action_links($links, $file) {
    static $this_plugin;

    if (!$this_plugin) {
        $this_plugin = plugin_basename(__FILE__);
    }

    if ($file == $this_plugin) {
        $settings_link = '<a href="' . get_bloginfo('wpurl') . '/wp-admin/options-general.php?page=boa_admin_page">Settings</a>';
        array_unshift($links, $settings_link);
    }

    return $links;
}


/* !4. EXTERNAL SCRIPTS */

// 4.1
// hint: adds custom css to head
// Since 0.5.0
function boa_custom_css() {
    echo '<link rel="stylesheet" href="'. plugins_url( 'assets/css/style-admin.css', __FILE__ ) .'" type="text/css" media="all" />';
}

/* !5. ACTIONS */

// 5.1
// hint: Create table wp_boa_log
// Since 0.5.0
function boa_install() {
    add_option(BOA_VERSION_KEY, BOA_VERSION_NUM);

    boa_create_tables();
}

// 5.2
// hint: Runs upgrade scripts
// Since 0.5.0
function boa_upgrade() {
    boa_create_tables();

    update_option(BOA_VERSION_KEY, BOA_VERSION_NUM);
}

// 5.3
// Checks if upgrade is needed
// Since 0.5.0
function boa_update_check() {
    //Check for version and upgrade if necessary
    if (get_option('boa_version') != BOA_VERSION_NUM) boa_upgrade();
}

// 5.4
// hint: creates tables
// Since 0.5.0
function boa_create_tables() {
    global $wpdb;

    // setup return value
    $return_value = false;

    try {

        $table_name = $wpdb->prefix . "boa_log";
        $charset_collate = $wpdb->get_charset_collate();

        // sql for our table creation
        $sql = "CREATE TABLE ".$table_name." (
                id bigint(20) NOT NULL AUTO_INCREMENT,
                  datetime timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
                  request text NOT NULL,
                  status varchar(25) NOT NULL,
                  result text NOT NULL,
                  UNIQUE KEY id (id)
			) $charset_collate;";

        // make sure we include wordpress functions for dbDelta
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

        // dbDelta will create a new table if none exists or update an existing one
        dbDelta($sql);

        // return true
        $return_value = true;

    } catch( Exception $e ) {

        // php error

    }

    // return result
    return $return_value;
}

// 5.5
// hint: adds events to logfile
// Since 0.5.0
function boa_log_event( $query_string, $array_result ) {

    global $wpdb;

    // setup our return value
    $return_value = false;

    $req = $query_string;
    $status = $array_result['status'];
    $result = $array_result['message'];


    try {

        $table_name = $wpdb->prefix . "boa_log";

        $wpdb->insert(
            $table_name,
            array(
                'request' => $req,
                'status' => $status,
                'result' => $result,
            ),
            array(
                '%s',
                '%s',
                '%s',
            )
        );

        // return true
        $return_value = true;

    } catch( Exception $e ) {

        // php error

    }

    // return result
    return $return_value;

}

// 5.6
// hint: Show log items on screen
// Since 0.5.0
function boa_show_log() {
    global $wpdb;

    if ($_GET['key'] == get_option('boa_option_om_api_key')) {
        $table_name = $wpdb->prefix . "boa_log";

        // get the records in the log table
        $logs = $wpdb->get_results(
            "SELECT datetime, request, status, result
	             FROM " . $table_name . "	             
	             ORDER BY datetime DESC
	             LIMIT 500"
        );

        echo "<strong>Better OptimizeMember API Log</strong><br/><br/>";

        // IF we have rows in the log
        if ($wpdb->num_rows > 0):

            // loop over all our logitems
            foreach ($logs as $log):

                echo "<strong>Date</strong>: " . $log->datetime . " <strong>Request</strong>: " . $log->request . "<br/><strong>Status</strong>: " . $log->status . " <strong>Result</strong>: " . $log->result . "<br/><hr>";

            endforeach;

            exit;

        endif;
    }
}

/* !6. HELPERS */

// 6.1
// hint: Checks if OptimizeMember Member is active
// Since 0.5.0
function boa_check_is_om_active() {

	$om_is_active = false;

	if ( OPTIMIZEMEMBER_VERSION != "OPTIMIZEMEMBER_VERSION" ) {
		$om_is_active = true;
	}

	return $om_is_active;
}

// 6.2
// hint: returns html for a yes/no selector
// Since 0.5.0
function boa_get_yesno_select( $input_name="boa_yesno", $input_id="", $selected_value="" ) {

	// setup our select html
	$select = '<select name="'. $input_name .'" ';

	// IF $input_id was passed in
	if( strlen($input_id) ):
		// add an input id to our select html
		$select .= 'id="'. $input_id .'" ';

	endif;

	// setup our first select option
	$select .= '><option value="">- Select One -</option>';

	//Add Yes
	// check if this option is the currently selected option
	$selected = '';
	if( $selected_value == "1" ):
		$selected = ' selected="selected" ';
	endif;

	// build our option html
	$option = '<option value="1" '. $selected .'>Ja</option>';

	// append our option to the select html
	$select .= $option;

	//Add No
	// check if this option is the currently selected option
	$selected = '';
	if( $selected_value == "0" ):
		$selected = ' selected="selected" ';
	endif;

	// build our option html
	$option = '<option value="0" '. $selected .'>Nee</option>';

	// append our option to the select html
	$select .= $option;
	// close our select html tag
	$select .= '</select>';

	// return our new select
	return $select;

}

// 6.3
// hint: get's the current options and returns values in associative array
// Since 0.5.0
function boa_get_current_options() {

	// setup our return variable
	$current_options = array();

	try {
        $boa_option_om_api_key = (get_option('boa_option_om_api_key', null) !== null) ? get_option('boa_option_om_api_key') : 0;
		$boa_option_send_confirmation_email = (get_option('boa_option_send_confirmation_email', null) !== null) ? get_option('boa_option_send_confirmation_email') : 0;
		$boa_option_check_if_user_exists = (get_option('boa_option_check_if_user_exists', null) !== null) ? get_option('boa_option_check_if_user_exists') : 1;
		$boa_option_update_user_data = (get_option('boa_option_update_user_data', null) !== null) ? get_option('boa_option_update_user_data') : 1;
		$boa_option_destination_email = (get_option('boa_option_destination_email')) ? get_option('boa_option_destination_email') : get_option('admin_email');
        $boa_options_email_include_password = (get_option('boa_options_email_include_password')) ? get_option('boa_options_email_include_password') : 0;

		// build our current options associative array
		$current_options = array(
            'boa_option_om_api_key' => $boa_option_om_api_key,
			'boa_option_send_confirmation_email' => $boa_option_send_confirmation_email,
			'boa_option_check_if_user_exists' => $boa_option_check_if_user_exists,
			'boa_option_destination_email' => $boa_option_destination_email,
			'boa_option_update_user_data' => $boa_option_update_user_data,
            'boa_options_email_include_password' => $boa_options_email_include_password,
		);

	} catch( Exception $e ) {

		// php error

	}

	// return current options
	return $current_options;

}

// 6.4
// hint: Send email confirmations
// Since 0.5.0
function boa_send_email_confirmation($action, $user, $user_pass, $level) {
	// setup return variable
	$email_sent = false;

	$options = boa_get_current_options();

	$email_destination = explode(";", $options['boa_option_destination_email']);
    $email_include_password = $options['boa_options_email_include_password'];

	// get email data
	$email_contents = boa_mail_contents($action, $user, $user_pass, $level, $email_include_password);

	// IF email template data was found
	if( !empty( $email_contents ) ):

		// set wp_mail headers
		$wp_mail_headers = array('Content-Type: text/html; charset=UTF-8');

		// use wp_mail to send email
		$email_sent = wp_mail( implode(",",$email_destination) , $email_contents['subject'], $email_contents['contents'], $wp_mail_headers );
	endif;

	return $email_sent;
}

// 6.5
// hint: create email contents
// Since 0.5.0
function boa_mail_contents($action, $user, $user_pass, $level, $email_include_password) {

	$email_contents = array();

	if ($action == "new_user") {
		$email_contents['subject'] = "Nieuwe gebruiker toegevoegd aan OptimizeMember Member via Autorespond";
		$email_contents['contents'] = '
		<p>Hallo,</p>
		<p>Er is zojuist een nieuwe gebruiker toegevoegd aan OptimizeMember Member via Autorespond:</p>
		<p>Gebruiker: '.$user.'<br/>';
		   if ($email_include_password) $email_contents['contents'] = $email_contents['contents'].'Wachtwoord: '.$user_pass.'<br/>';


        $email_contents['contents'] = $email_contents['contents'].'
		Level: '.$level.'</p>
		<p>Met vriendelijke groet,<br/>
		   Better OptimizeMember API</p>
		';

	} elseif ($action == "add_level") {
		$email_contents['subject'] = "Nieuw level toegevoegd aan gebruiker in OptimizeMember Member via Autorespond";
		$email_contents['contents'] = '
		<p>Hallo,</p>
		<p>Er is zojuist een nieuw level toegevoegd aan gebruiker '.$user.' in OptimizeMember Member via Autorespond:</p>
		<p>Gebruiker: '.$user.'<br/>
		   Toegevoegd level: '.$level.'</p>
		<p>Met vriendelijke groet,<br/>
		   Better OptimizeMember API</p>
		';
	} elseif ($action == "add_package") {
        $email_contents['subject'] = "Nieuw package toegevoegd aan gebruiker in OptimizeMember Member via Autorespond";
        $email_contents['contents'] = '
		<p>Hallo,</p>
		<p>Er is zojuist een nieuwe package toegevoegd aan gebruiker '.$user.' in OptimizeMember Member via Autorespond:</p>
		<p>Gebruiker: '.$user.'<br/>
		   Toegevoegd level: '.$level.'</p>
		<p>Met vriendelijke groet,<br/>
		   Better OptimizeMember API</p>
		';
    } elseif ($action == "del_package") {
        $email_contents['subject'] = "Package verwijderd toegevoegd bij gebruiker in OptimizeMember Member via Autorespond";
        $email_contents['contents'] = '
		<p>Hallo,</p>
		<p>Er is zojuist een package verwijderd bij gebruiker '.$user.' in OptimizeMember Member via Autorespond:</p>
		<p>Gebruiker: '.$user.'<br/>
		   Toegevoegd level: '.$level.'</p>
		<p>Met vriendelijke groet,<br/>
		   Better OptimizeMember API</p>
		';
    }

	return $email_contents;

}

// 6.6
// hint: updates user data with first name, last name and display name
// Since 0.5.0
function boa_register_user_data ($userid, $fname, $lname) {
	$first = 0;
	$last = 0;
	$user_array = array( 'ID' => $userid);

    $user_info = get_userdata($userid);

    if (!strlen($user_info->first_name && !strlen($user_info->last_name))) {
        // Only update if there is no current first name and/or last name registered
        if (strlen($fname)) {
            $first_name = esc_attr($fname);
            $user_array['first_name'] = $first_name;
            $first = 1;
        }

        if (strlen($lname)) {
            $last_name = esc_attr($lname);
            $user_array['last_name'] = $last_name;
            $last = 1;
        }

        if ($first && !$last) {
            $user_array['display_name'] = $first_name;
            $result = "Name and Display name set to ".$first_name;
        } elseif ($first && $last) {
            $user_array['display_name'] = $first_name . " " . $last_name;
            $result = "Name and Display name set to ".$first_name. " " . $last_name;;
        } elseif (!$first && $last) {
            $user_array['display_name'] = $last_name;
            $result = "Name and Display name set to ".$last_name;
        }

        if ($first || $last) {
            $user_id = wp_update_user($user_array);
            if (is_wp_error($user_id)) {
                $error_string = $user_id->get_error_message();
                return "Error: ".$error_string;
            } else {
                return $result;
            }
        } else {
            return "No first or last name received. Name not set.";
        }
    } else {
        return "No first or last name received. Name not set.";
    }
}

// 6.7
// hint: checks current level of user
// Since 0.5.0

function boa_api_get_user_level($user_id) {
    $level = -1;

    if (user_is($user_id, "optimizemember_level0")) $level = 0;
    if (user_is($user_id, "optimizemember_level1")) $level = 1;
    if (user_is($user_id, "optimizemember_level2")) $level = 2;
    if (user_is($user_id, "optimizemember_level3")) $level = 3;
    if (user_is($user_id, "optimizemember_level4")) $level = 4;
    if (user_is($user_id, "optimizemember_level5")) $level = 5;
    if (user_is($user_id, "optimizemember_level6")) $level = 6;
    if (user_is($user_id, "optimizemember_level7")) $level = 7;
    if (user_is($user_id, "optimizemember_level8")) $level = 8;
    if (user_is($user_id, "optimizemember_level9")) $level = 9;
    if (user_is($user_id, "optimizemember_level10")) $level = 10;

    return $level;
}

// 6.8
// hint: checks current packages of user
// Since 0.6.0

function boa_api_get_user_packages($user_id) {

    $packages = array();

    $wp_capabilities = get_user_meta($user_id, "wp_capabilities", false); //return array of wp_capabilities

    foreach ($wp_capabilities[0] as $wp_capability => $able) {
        if (strpos($wp_capability, 'access_optimizemember_ccap_') !== false && $able) {
            $packages[] = substr($wp_capability, 27);
        }
    }

    return $packages;

}

/* !7. CUSTOM POST TYPES */

/* !8. ADMIN PAGES */

// 8.1 Main Admin Menu
// hint: create Admin menu
// Since 0.5.0

function boa_admin_page() {

	$options = boa_get_current_options();

    if (isset($_GET['downloadlog'])) boa_download_log_csv();

    if (!boa_check_is_om_active()) {
        $error_om_not_active = '
            <div class="error">
                <p>
                    <strong>De plugin OptimizeMember is niet gevonden.</strong>
                </p>
                <p>
                    Deze plugin is een uitbreiding van de plugin OptimizeMember
                </p>
                <p>
                    Zonder OptimizeMember heeft deze plugin geen functie.
                </p>
            </div>';
    }

	echo '
		<div class="wrap">
			<h2>Better OptimizeMember Member API</h2>
			<div class="boa-wrapper">
                <div id="content" class="wrapper-cell">
                    '.$error_om_not_active.'
                    <p>Deze plugin geeft je meer opties voor het integreren van Autorespond en OptimizeMember</p>
                    <p>Met name:</p>
                    <ul>
                        <li>* De mogelijkheid om via Autorespond opdrachten te geven aan OptimizeMember</li>
                        <li>* De mogelijkheid om van je website een bevestiging te krijgen van de aanmelding van de nieuwe gebruiker of het toekennen van het nieuwe level</li>
                    </ul>

                    <h2>Better OptimizeMember API Opties</h2>

                    <form action="options.php" method="post">';
                        // outputs a unique nounce for our plugin options
                        settings_fields('boa_plugin_options');
                        // generates a unique hidden field with our form handling url
                        @do_settings_fields('boa_plugin_options', 'default');

                        echo '<table class="form-table">

                            <tbody>
                                <tr>
                                    <th scope="row"><label for="boa_option_om_api_key">OptimizeMember API Key</label></th>
                                    <td>
                                        <input type="text" id="boa_option_om_api_key" name="boa_option_om_api_key" value="'.$options['boa_option_om_api_key'].'" size="100"/]<br/>
                                        <p class="description" id="boa_option_om_api_key-description">Om veilige communicatie te garanderen, moet Better OptimizeMember API de API Key van Optimize Member weten. Deze kun je vinden op de volgende pagina: OptimizeMember > API / Scripting > Pro API For Remote Operations</p>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><label for="boa_option_check_if_user_exists">Controleren bestaande gebruiker</label></th>
                                    <td>
                                        '.boa_get_yesno_select("boa_option_check_if_user_exists", "boa_option_check_if_user_exists", $options['boa_option_check_if_user_exists']) .'
                                        <p class="description" id="boa_option_check_if_user_exists-description">Als deze optie is ingeschakeld, zal Better OptimizeMember API controleren of de gebruiker al bestaat, door het opgegeven e-mailadres te vergelijken met bestaande gebruikers. Als een bestaande gebruiker wordt gevonden, zal er geen nieuwe gebruiker worden toegevoegd. In plaats daarvan zal het nieuwe level aan de bestaande gebruiker worden toegekend.</p>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><label for="boa_option_update_user_data">Registreer Naam</label></th>
                                    <td>
                                        '.boa_get_yesno_select("boa_option_update_user_data", "boa_option_update_user_data", $options['boa_option_update_user_data']) .'
                                        <p class="description" id="boa_option_update_user_data-description">Als deze optie is ingeschakeld, worden de voornaam, achternaam en schermnaam toegevoegd aan de gebruiker, als deze niet al zijn ingevuld.</p>
                                    </td>
                                </tr>
                                <tr>
                                <tr>
                                    <th scope="row"><label for="boa_option_send_confirmation_email">Monitoring e-mail</label></th>
                                    <td>
                                        '.boa_get_yesno_select("boa_option_send_confirmation_email", "boa_option_send_confirmation_email", $options['boa_option_send_confirmation_email']) .'
                                        <p class="description" id="boa_option_send_confirmation_email-description">Als deze optie is ingeschakeld, krijg je een e-mail als er via Better OptimizeMember API een nieuwe gebruiker is toegevoegd, of als er een level is toegevoegd aan een bestaande gebruiker.</p>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><label for="boa_options_email_include_password">Vermeld wachtwoord in e-mail</label></th>
                                    <td>
                                        '.boa_get_yesno_select("boa_options_email_include_password", "boa_options_email_include_password", $options['boa_options_email_include_password']) .'
                                        <p class="description" id="boa_options_email_include_password-description">Als deze optie is ingeschakeld, wordt in de bevestigingsmail ook het ingestelde wachtwoord vermeld bij een nieuwe gebruiker.</p>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><label for="boa_option_destination_email">E-mail adres</label></th>
                                    <td>
                                        <input type="text" id="boa_option_destination_email" name="boa_option_destination_email" value="'.$options['boa_option_destination_email'].'" size="100"/]<br/>
                                        <p class="description" id="boa_option_destination_email-description">Op welk e-mailadres wil je de bevestigingsmails ontvangen? Als je wilt dat de bevestigingen naar meerdere e-mailadressen gestuurd worden, zet ze dan achter elkaar, geschieden door een puntkomma (;)</p>
                                    </td>
                                </tr>

                            </tbody>

                        </table>';

                        @submit_button();

                    echo '</form>
                </div>
                <div id="sidebar" class="wrapper-cell">
                    <div class="sidebar_box info_box">
                        <h3>Plugin Info</h3>
                        <div class="inside">
                            <a href="http://www.autorespond.nl" target="_blank"><img  width="272px" src="'. plugins_url( 'img/logo-ar.jpg', __FILE__ ) .'" /></a>
                            <ul>
                                <li>Naam: Better OptimizeMember API</li>
                                <li>Auteur: Rick Heijster @ RAM ICT Services</li>
                            </ul>
                            <p>Deze plugin wordt je gratis aangeboden door Autorespond in samenwerking met RAM ICT Services.</p>
                            <a href="http://www.ram-ictservices.nl" target="_blank"><img  width="272px" src="'. plugins_url( 'img/Logo-Ram.png', __FILE__ ) .'" /></a>
                        </div>
                    </div>

                    <div class="sidebar_box info_box">
                        <h3>Log file</h3>
                        <div class="inside">
                            <p></p>Klik <a href="admin-ajax.php?action=boa_show_log&key='.$options['boa_option_om_api_key'].'" target="_blank">hier</a> om de log van Better OptimizeMember API te bekijken</p>
                        </div>
                    </div>
                </div>
            </div>
		</div>

	';

}


/* !9. SETTINGS */

// 9.1
// hint: registers all our plugin options
// Since 0.5.0
function boa_register_options() {
	// plugin options
    register_setting('boa_plugin_options', 'boa_option_om_api_key');
	register_setting('boa_plugin_options', 'boa_option_check_if_user_exists');
	register_setting('boa_plugin_options', 'boa_option_send_confirmation_email');
	register_setting('boa_plugin_options', 'boa_option_destination_email');
	register_setting('boa_plugin_options', 'boa_option_update_user_data');
    register_setting('boa_plugin_options', 'boa_options_email_include_password');

}

/* !10. API */

// 10.1
// hint: Add user function for API
// Since 0.5.0
function boa_api_add_user($api_key, $user_email, $user_pass, $level_id = 0, $user_first_name = "", $user_last_name = "", $addpack = "") {

    $op["op"] = "create_user"; // The Remote Operation.

    $op["api_key"] = $api_key; // Check your Dashboard for this value.
    // See: `optimizeMember -> API / Scripting -> Remote Operations API -› API Key`

    $op["data"] = array (
        "user_login" => $user_email, // Required. A unique Username. Lowercase alphanumerics/underscores.
        "user_email" => $user_email, // Required. A valid/unique Email Address for the new User.

        // These additional details are 100% completely optional.

        "modify_if_login_exists" => "1", // Optional. Update/modify if ``user_login`` value already exists in the database?
        // A non-zero value tells optimizeMember to update/modify an existing account with the details you provide, if this Username already exists.

        "user_pass" => $user_pass, // Optional. Plain text Password. If empty, this will be auto-generated.

        "first_name" => $user_first_name, // Optional. First Name for the new User.
        "last_name" => $user_last_name, // Optional. Last Name for the new User.

        "optimizemember_level" => $level_id, // Optional. Defaults to Level #0 (a Free Subscriber).
        "optimizemember_ccaps" => $addpack, // Optional. Comma-delimited list of Custom Capabilities / Packages.

        "custom_fields" => array ("Registered Via" => "Autorespond -> API"), // Optional. An array of Custom Registration/Profile Field ID's, with associative values.

        "optimizemember_notes" => "Created this User via Autoresond through an API Call on ". date('Y-m-d H:i:s').".", // Optional. Administrative notations.

        "opt_in" => "1", // Optional. A non-zero value tells optimizeMember to attempt to process any List Servers you've configured in the Dashboard area.
        // This may result in your mailing list provider sending the User/Member a subscription confirmation email (i.e. ... please confirm your subscription).

        "notification" => "0", // Optional. A non-zero value tells optimizeMember to email the new User/Member their Username/Password.
        // The "notification" parameter also tells optimizeMember to notify the site Administrator about this new account.
    );

    $post_data = stream_context_create (array ("http" => array ("method" => "POST", "header" => "Content-type: application/x-www-form-urlencoded", "content" => "optimizemember_pro_remote_op=" . urlencode (serialize ($op)))));

    $result = trim (file_get_contents (get_site_url()."/?optimizemember_pro_remote_op=1", false, $post_data));

    if (!empty ($result) && !preg_match ("/^Error\:/i", $result) && is_array ($user = @unserialize ($result))) {
        $endresult['status'] = "success";
        $endresult['message'] = "Success. New User created with ID: " . $user["ID"];
    } else {
        $endresult['status'] = "error";
        $endresult['message'] = "API error reads: " . $result;
    }

    return $endresult;
}

// 10.2
// hint: Add level function for API
// Since 0.6.0
function boa_api_add_level($api_key, $user_id, $level, $user_email, $user_first_name, $user_last_name, $force) {

    $current_level = boa_api_get_user_level($user_id);

    if ($level <= $current_level && !$force) {
        $endresult['status'] = "success";
        $endresult['message'] = "User already has higher or same level";
    } else {
        $op["op"] = "modify_user"; // The Remote Operation.

        $op["api_key"] = $api_key;

        $op["data"] = array(
            // You must supply one of these values.
            "user_id" => "$user_id",
            "optimizemember_level" => $level, // Optional  — if updating.
            "optimizemember_notes" => "Changed to level " . $level . " via API call on ". date('Y-m-d H:i:s').".", // Optional — if updating. A new administrative notation added to the User's account.
            "first_name" => $user_first_name, // Optional — if updating. First Name for this User.
            "last_name" => $user_last_name, // Optional — if updating. Last Name for this User.
            "display_name" => $user_first_name." ".$user_last_name, // Optional — if updating. Display Name for this User.
        );

        $post_data = stream_context_create(array("http" => array("method" => "POST", "header" => "Content-type: application/x-www-form-urlencoded", "content" => "optimizemember_pro_remote_op=" . urlencode(serialize($op)))));

        $result = trim(file_get_contents(get_site_url()."/?optimizemember_pro_remote_op=1", false, $post_data));

        if (!empty ($result) && !preg_match("/^Error\:/i", $result) && is_array($user = @unserialize($result))) {
            $endresult['status'] = "success";
            $endresult['message'] = "Success. Level ".$level." added to user ".$user_email;
        } else {
            $endresult['status'] = "error";
            $endresult['message'] = "API error reads: " . $result;
        }
    }

    return $endresult;
}
// 10.3
// hint: Add package function for API
// Since 0.6.0
function boa_api_add_package($api_key, $user_id, $user_email, $addpack) {

    $packages = explode(",", $addpack);

    $added_package = false;

    $user = new WP_User( $user_id);

    foreach ($packages as $package) {
        $capability = "access_optimizemember_ccap_".$package;
        if (!user_can($user_id, $capability)) {
            $user->add_cap("access_optimizemember_ccap_".$package);
            $added_package = true;
        }
    }

    if ($added_package) {
        $op["op"] = "modify_user"; // The Remote Operation.

        $op["api_key"] = $api_key;

        $op["data"] = array(
            // You must supply one of these values.
            "user_id" => "$user_id",
            "optimizemember_notes" => "Added package(s) " . $addpack . " via API call on ". date('Y-m-d H:i:s').".", // Optional — if updating. A new administrative notation added to the User's account.
        );

        $post_data = stream_context_create(array("http" => array("method" => "POST", "header" => "Content-type: application/x-www-form-urlencoded", "content" => "optimizemember_pro_remote_op=" . urlencode(serialize($op)))));

        $result = trim(file_get_contents(get_site_url()."/?optimizemember_pro_remote_op=1", false, $post_data));

        if (!empty ($result) && !preg_match("/^Error\:/i", $result) && is_array($user = @unserialize($result))) {
            $endresult['status'] = "success";
            $endresult['message'] = "Success. Added package(s) ".$addpack." for user ".$user_email;
        } else {
            $endresult['status'] = "error";
            $endresult['message'] = "API error reads: " . $result;
        }
    } else {
        $endresult['status'] = "success";
        $endresult['message'] = "Request to add to package(s) $addpack, but user already has package(s).";
    }

    return $endresult;
}

// 10.4
// hint: Delete package function for API
// Since 0.6.0

function boa_api_del_package($api_key, $user_id, $user_email, $delpack) {

    $packages = explode(",", $delpack);
    $deleted_package = false;

    $user = new WP_User( $user_id);

    foreach ($packages as $package) {
        $capability = "access_optimizemember_ccap_".$package;
        if (user_can($user_id, $capability)) {
            $user->remove_cap("access_optimizemember_ccap_".$package);
            $deleted_package = true;
        }
    }

    if ($deleted_package) {
        $op["op"] = "modify_user"; // The Remote Operation.

        $op["api_key"] = $api_key;

        $op["data"] = array(
            // You must supply one of these values.
            "user_id" => "$user_id",
            "optimizemember_notes" => "Removed package(s) " . $delpack . " via API call on ". date('Y-m-d H:i:s').".", // Optional — if updating. A new administrative notation added to the User's account.
        );

        $post_data = stream_context_create(array("http" => array("method" => "POST", "header" => "Content-type: application/x-www-form-urlencoded", "content" => "optimizemember_pro_remote_op=" . urlencode(serialize($op)))));

        $result = trim(file_get_contents(get_site_url()."/?optimizemember_pro_remote_op=1", false, $post_data));

        if (!empty ($result) && !preg_match("/^Error\:/i", $result) && is_array($user = @unserialize($result))) {
            $endresult['status'] = "success";
            $endresult['message'] = "Success. Removed package(s) ".$delpack." for user ".$user_email;
        } else {
            $endresult['status'] = "error";
            $endresult['message'] = "API error reads: " . $result;
        }


    } else {
        $endresult['status'] = "success";
        $endresult['message'] = "Request to remove package $delpack, but user does not have package.";
    }

    return $endresult;
}

// 10.5
// hint: API which is contacted by autoresponder
// Since 0.5.0
function boa_better_om_api ()
{

    // Check if there's a API request
    if (isset($_REQUEST['betteromapi']) || isset($_REQUEST['omapikey'])) {

		
        if ( !boa_check_is_om_active() ) { // If OptimizeMembers is not active, disengage.
            echo json_encode(array('success' => 0, 'message' => 'Request received, but OptimizeMembers is not active. Better OptimizeMember API ignored request.'));
            $result['message'] = "Request received, but OptimizeMembers is not active. Better OptimizeMember API ignored request.";
            $result['status'] = "Error";
            boa_log_event($_SERVER['QUERY_STRING'], $result);
            exit;
        }

        header('Content-type: application/json');

        $boa_options = boa_get_current_options();

        if (isset($_REQUEST['omapikey'])) $api_key = $_REQUEST['omapikey'];


        $om_api_key = $boa_options['boa_option_om_api_key'];

        // Check if the passed api key matches OM's api key
        if ($api_key == $om_api_key) {
            $result = array();

            if (isset($_REQUEST['betteromapi_method'])) $om_api_method = $_REQUEST['betteromapi_method'];
            if (isset($_REQUEST['betteromapi_method'])) $om_api_method = $_REQUEST['betteromapi_method'];

            // Check if the method passed is valid

            $current_methods = array('add_new_member', 'deactivate_member', 'edit_member');

            if (in_array($om_api_method, $current_methods)) {

                switch ($om_api_method) {
                    case 'add_new_member': //Add new member, or change exisiting member
                        $force_downgrade = 0;

                        if (isset($_REQUEST['force'])) {
                            $force_downgrade = $_REQUEST['force'];
                        }

                        $user_login = $_REQUEST['username'];
                        $user_email = $_REQUEST['useremail'];
                        $user_pass = $_REQUEST['userpass'];
                        $level_id = $_REQUEST['level'];
                        $addpack = $_REQUEST['addpack'];
                        if (!isset($_REQUEST['level']) && isset($_REQUEST['levelid'])) $level_id = $_REQUEST['levelid'];
                        $user_first_name = $_REQUEST['fname'];
                        $user_last_name = $_REQUEST['lname'];

                        if (empty($user_login) || empty($user_email) || empty($user_pass) || !is_numeric($level_id)) {
                            echo json_encode(array('success' => 0, 'message' => 'add_new_member method needs the the following data: username, useremail, userpass, levelid'));
                            $result['message'] = "Request add member, but no level ID or user login or email or password received.";
                            $result['status'] = "Error";
                        } else {
                            $exists = username_exists($user_email);

                            if ($exists && $boa_options['boa_option_check_if_user_exists']) {
                                //User already exists. Add level to user
                                $received_member_id = $exists;

                                if (is_numeric($level_id)) {
                                    //Add level to user
                                    $action = "add_level";
                                    $result_add_level = boa_api_add_level($boa_options['boa_option_om_api_key'], $received_member_id, $level_id, $user_email, $user_first_name, $user_last_name, $force_downgrade);

                                    if ($result_add_level['status'] == "success") {
                                        if ($boa_options['boa_option_send_confirmation_email']) {
                                            boa_send_email_confirmation($action, $user_email, $user_pass, $level_id);
                                        }
                                    }

                                    $result['status'] = $result_add_level['status'];
                                    $result['message'] = $result_add_level['message'];
                                }

                                if (!empty($addpack)) {
                                    //Add package to user
                                    echo "Add package";
                                    $action = "add_package";
                                    $result_add_pack = boa_api_add_package($boa_options['boa_option_om_api_key'], $received_member_id, $user_login, $addpack);

                                    if ($result_add_pack['status'] == "success") {
                                        if ($boa_options['boa_option_send_confirmation_email']) {
                                            boa_send_email_confirmation($action, $user_email, $user_pass, $level_id);
                                        }
                                    }

                                    if ($result['status'] != "error") $result['succes'] = $result_add_pack['status'];
                                    if ($result['message'] == "") {
                                        $result['message'] = $result_add_pack['message'];
                                    } else {
                                        $result['message'] = $result['message']." - ".$result_add_pack['message'];
                                    }
                                }

                                if ($result['status'] == "error") {
                                    echo json_encode(array('success' => 0, 'message' => $result['message']));
                                } else {
                                    echo json_encode(array('success' => 1, 'message' => $result['message']));
                                }

                            } else {
                                //User does not already exists. Add user
                                $result = boa_api_add_user($boa_options['boa_option_om_api_key'], $user_email, $user_pass, $level_id, $user_first_name, $user_last_name, $addpack);

                                $action = "new_user";

                                if ($boa_options['boa_option_send_confirmation_email']) {
                                    boa_send_email_confirmation($action, $user_email, $user_pass, $level_id);
                                }

                                echo json_encode(array('success' => 1, 'message' => $result));
                            }
                        }
                        break;

                    case 'edit_member':
                        $force_downgrade = 0;

                        if (isset($_REQUEST['force'])) {
                            $force_downgrade = $_REQUEST['force'];
                        }

                        $user_login = $_REQUEST['username'];
                        $user_email = $_REQUEST['useremail'];
                        $user_pass = $_REQUEST['userpass'];
                        $level_id = $_REQUEST['level'];
                        $addpack = $_REQUEST['addpack'];
                        $delpack = $_REQUEST['delpack'];
                        if (!isset($_REQUEST['level']) && isset($_REQUEST['levelid'])) $level_id = $_REQUEST['levelid'];
                        $user_first_name = $_REQUEST['fname'];
                        $user_last_name = $_REQUEST['lname'];

                        if (empty($user_login)) {
                            echo json_encode(array('success' => 0, 'message' => 'edit_member method needs the the following data: username'));
                            $result['message'] = "Request to edit member, but no user login received.";
                            $result['status'] = "Error";
                        } else {
                            $exists = username_exists($user_login);
                            $received_member_id = $exists;

                            if ($exists) { // User exists

                                if (is_numeric($level_id)) {
                                    //Add level to user
                                    $action = "add_level";
                                    $result_add_level = boa_api_add_level($boa_options['boa_option_om_api_key'], $received_member_id, $level_id, $user_email, $user_first_name, $user_last_name, $force_downgrade);

                                    if ($result_add_level['status'] == "success") {
                                        if ($boa_options['boa_option_send_confirmation_email']) {
                                            boa_send_email_confirmation($action, $user_email, $user_pass, $level_id);
                                        }
                                    }

                                    $result['status'] = $result_add_level['status'];
                                    $result['message'] = $result_add_level['message'];
                                }

                                if (!empty($addpack)) {
                                    //Add package to user
                                    $action = "add_package";
                                    $result_add_pack = boa_api_add_package($boa_options['boa_option_om_api_key'], $received_member_id, $user_login, $addpack);

                                    if ($result_add_pack['status'] == "success") {
                                        if ($boa_options['boa_option_send_confirmation_email']) {
                                            boa_send_email_confirmation($action, $user_email, $user_pass, $level_id);
                                        }
                                    }

                                    if ($result['status'] != "error") $result['succes'] = $result_add_pack['status'];
                                    if ($result['message'] == "") {
                                        $result['message'] = $result_add_pack['message'];
                                    } else {
                                        $result['message'] = $result['message']." - ".$result_add_pack['message'];
                                    }
                                }

                                if (!empty($delpack)) {
                                    //Delete package from user
                                    $action = "del_package";
                                    $result_del_pack = boa_api_del_package($boa_options['boa_option_om_api_key'], $received_member_id, $user_login, $delpack);

                                    if ($result_del_pack['status'] == "success") {
                                        if ($boa_options['boa_option_send_confirmation_email']) {
                                            boa_send_email_confirmation($action, $user_email, $user_pass, $level_id);
                                        }
                                    }

                                    if ($result['status'] != "error") $result['status'] = $result_del_pack['status'];
                                    if ($result['message'] == "") {
                                        $result['message'] = $result_del_pack['message'];
                                    } else {
                                        $result['message'] = $result['message']." - ".$result_del_pack['message'];
                                    }
                                }

                                if ($result['status'] == "error") {
                                    echo json_encode(array('success' => 0, 'message' => $result['message']));
                                } else {
                                    echo json_encode(array('success' => 1, 'message' => $result['message']));
                                }

                            } else {
                                //User does not already exists. Edit user not possible
                                echo json_encode(array('success' => 0, 'message' => 'edit_member method did not found user'));
                                $result['message'] = "Request to edit member, but user not found.";
                                $result['status'] = "Error";
                            }
                        }
                        break;
                    case 'deactivate_member': //Deactivate member: setting the user to level 0 (free subscriber)

                        $user_login = $_REQUEST['username'];
                        $user_email = $_REQUEST['useremail'];
                        $user_pass = $_REQUEST['userpass'];
                        $user_first_name = $_REQUEST['fname'];
                        $user_last_name = $_REQUEST['lname'];

                        if (empty($user_login)) {
                            echo json_encode(array('success' => 0, 'message' => 'deactivate_member method needs the the following data: username'));
                            $result['message'] = "Request to deactive member, but no username received.";
                            $result['status'] = "Error";
                        } else {
                            $exists = email_exists($user_email);

                            if ($exists) {
                                //User exists. Setting user to level 0
                                $received_member_id = $exists;
                                $action = "deactivate_member";

                                $result = boa_api_add_level($boa_options['boa_option_om_api_key'], $received_member_id, 0, $user_email, $user_first_name, $user_last_name, 1);

                                if ($result['status'] == "success") {
                                    if ($boa_options['boa_option_send_confirmation_email']) {
                                        boa_send_email_confirmation($action, $user_email, $user_pass, 0);
                                    }

                                    echo json_encode(array('success' => 1, 'message' => $result['message']));
                                } else {
                                    echo json_encode(array('success' => 1, 'message' => $result['message']));
                                }

                            } else {
                                //User does not exist.

                                echo json_encode(array('success' => 0, 'message' => 'No user found with username ' . $user_login));
                                $result['message'] = "Request to deactive member, but no user found with username " . $user_login;
                                $result['status'] = "Error";
                            }
                        }
                        break;
                }

            } else {
                echo json_encode(array('success' => 0, 'message' => 'Wrong Method, supported methods are add_new_member, deactivate_member and edit_member'));
                $result['message'] = "Wrong Method, supported methods are add_new_member, deactivate_member, edit_member";
                $result['status'] = "Error";
            }

        } else {
            echo json_encode(array('success' => 0, 'message' => 'Wrong API Key'));
            $result['message'] = "Wrong API Key";
            $result['status'] = "Error";
        }

        boa_log_event(serialize($_REQUEST), $result);

        exit;
    }
}

