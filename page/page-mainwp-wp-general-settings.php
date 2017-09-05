<?php

class MainWP_WP_General_Settings {
	
    private static $instance = null;
    
    private $settings = null;
    private $selected_fields = null;
    
    public function __construct() {                      
     
    }
    
    public static function getClassName() {
		return __CLASS__;
	}
    
	static function Instance() {
		if (self::$instance == null ) {
			self::$instance = new self();
		}

		return self::$instance;
	}
    
    public static function init() {     
        add_action( 'admin_init', array( self::Instance(), 'admin_init' ) );
        add_action( 'wp_ajax_mainwp_wpgeneral_settings_save', array( self::Instance(), 'ajax_wpgeneral_settings_save' ) );
    }
    
    public function admin_init() {        
        $this->handle_posting();
    }
        
    public function get_option($option, $default = '') {
        if ($this->settings === null) {
            $this->settings = get_option('mainwp_settings_wpgeneral', array());            
        }        
        if (!is_array($this->settings))
            $this->settings = array();
        
        if (isset($this->settings[$option]))
            return $this->settings[$option];
        
        return $default;
    }
    
    public function update_option($option, $value) {
        if ($this->settings === null) {
            $this->settings = get_option('mainwp_settings_wpgeneral', array());
        }
        
        if (!is_array($this->settings))
            $this->settings = array();        
        
        $this->settings[$option] = $value;
        
        return update_option('mainwp_settings_wpgeneral', $this->settings);        
    }

    function checked_field($option_name) {
        
        if ($this->selected_fields === null) {
            $this->selected_fields = get_option('mainwp_wpgeneral_checked_fields', array());
        }
        
        if (!is_array($this->selected_fields))
            $this->selected_fields = array();
        
        $checked = in_array( $option_name, $this->selected_fields ) ? true : false;
        ?>
        <td class="check-column" scope="row"><input name="checked_bsm_settings[]"  value="<?php echo $option_name; ?>" <?php checked( $checked ); ?> type="checkbox"></td>
        <?php
    }    
	    
    function ajax_wpgeneral_settings_save(){         
        
        $_nonce = isset($_POST['nonce']) ? $_POST['nonce'] : '';
        if (empty($_nonce) || !wp_verify_nonce( $_nonce, 'mainwp_ajax' )) {
            die( json_encode( array('error' => 'Invalid request.') ) );        
        }
        
        $websiteId = intval($_POST['siteId']);
        
        if (empty($websiteId)) {
            die( json_encode( array('error' => 'Empty site id.') ) );
        }
                 
        $checked_fields = get_option('mainwp_wpgeneral_checked_fields', array());
        if (!is_array($checked_fields) || count($checked_fields) == 0) {
            die( json_encode( array('error' => 'No selected fields.') ) );
        }
        
        if (in_array('timezone_string', $checked_fields)) {
            $checked_fields[] = 'gmt_offset';
        }
        
        $settings = get_option('mainwp_settings_wpgeneral', array()); 
        $select_settings = array();
        
        if (is_array($settings)) {
            foreach($checked_fields as $opt) {
                if (isset($settings[$opt])) {
                    $select_settings[$opt] = $settings[$opt];
                }
            }
        }
        
        if (count($select_settings) == 0) {
            die( json_encode( array('error' => 'Empty settings.') ) );
        }        
            
        $post_data = array(
            'action' => 'save_settings',
            'settings' => $select_settings
        );
        $website = MainWP_DB::Instance()->getWebsiteById( $websiteId );
        //Send request to the childsite!
        $information = MainWP_Utility::fetchUrlAuthed( $website, 'skeleton_key', array(
            'action' => 'save_settings',
            'settings' => $select_settings
        ) );
       
        if ( is_array( $information )) {
            if ( isset( $information['result'] ) ) {
                die(json_encode(array('result' => 'ok')));
            } else if ( isset( $information['error'] ) ) {
                die(json_encode(array('error' => $information['error'])));  
            }
        }
        die( json_encode( array( 'error' => 'Undefined error.', 'extra' => $information) ) );	
    }
    
    public function renderWPGeneralSettings() {
		if (!mainwp_current_user_can('dashboard', 'manage_dashboard_settings')) {
			mainwp_do_not_have_permissions(__('manage dashboard settings', 'mainwp'));
			return;
		}

        require_once( ABSPATH . 'wp-admin/includes/translation-install.php' );        
        
        $save_nonce = get_transient('bsm_save_settings_nonce');                             
        
        $saving = false;
        if ($save_nonce && wp_verify_nonce( $save_nonce, 'bsm_nonce' )) {   
             delete_transient( 'bsm_save_settings_nonce' );
            $saving = true;
            settings_errors();
        }
      
		MainWP_Settings::renderHeader('WPGeneral');
		?>
		<form method="POST" action="" id="mainwp-settings-page-form">
			<input type="hidden" name="wp_nonce" value="<?php echo wp_create_nonce('WPGeneralSettings'); ?>" />
            <form method="post" action="admin.php?page=mainwp-wp-general-settings" novalidate="novalidate">        
            <?php
                if (!$saving) {
                    ?>
                    <div id="wpgeneral_settings_select_sites_wrap">
                    <?php 
                    do_action( 'mainwp_select_sites_box', __( "Select Sites", 'mainwp' ), 'checkbox', true, true, 'mainwp_select_sites_box_right mainwp_select_sites_key', "", array(), array() ); 
                    ?>
                    </div>
                    <?php
                }
            ?>        
                <div class="postbox" <?php echo (!$saving ? 'style="width: calc(100% - 280px) !important; float: left;"' : ''); ?>>
                    <h3 class="mainwp_box_title"><i
                            class="fa fa-cog"></i> <?php _e( 'Wordpress General Settings', 'mainwp-bulk-settings-manager' ); ?></h3>
                    <div class="inside">

                    <div  class="mainwp-notice mainwp-notice-red" id="mwp-bsm-general-settings-error-box" style="display:none;"></div>

                    <?php
                        if ($saving) {                             
                            $this->gen_saving_settings();             
                        } else {
                            $this->gen_settings(); 
                        }   
                    ?>                            
                    </div>
                </div>
            </form>
            <div style="clear: both;"></div>
		</form>
		<?php
		MainWP_Settings::renderFooter('WPGeneral');
	}
	
    
    function handle_posting() {
        
        $action = isset($_POST['action']) ? $_POST['action'] : false;
        $option_page = isset($_POST['option_page']) ? $_POST['option_page'] : false;
                            
        if ( 'update' == $action && 'mainwp_wpgeneral_general' == $option_page) {                            
                        
            check_admin_referer( $option_page . '-options' );     
            
			$selected_sites = $selected_groups = array();
            if ( isset( $_POST['select_by'] ) ) {
                    if ( isset( $_POST['selected_sites'] ) && is_array( $_POST['selected_sites'] ) ) {
                            foreach ( $_POST['selected_sites'] as $selected ) {
                                    $selected_sites[] = intval( $selected );
                            }
                    }

                    if ( isset( $_POST['selected_groups'] ) && is_array( $_POST['selected_groups'] ) ) {
                            foreach ( $_POST['selected_groups'] as $selected ) {
                                    $selected_groups[] = intval( $selected );
                            }
                    }
            }
            
            update_option('mainwp_wpgeneral_selected_sites', $selected_sites);
            update_option('mainwp_wpgeneral_selected_groups', $selected_groups);
            
            // Handle custom date/time formats.
            if ( !empty($_POST['date_format']) && isset($_POST['date_format_custom']) && '\c\u\s\t\o\m' == wp_unslash( $_POST['date_format'] ) )
                $_POST['date_format'] = $_POST['date_format_custom'];
            if ( !empty($_POST['time_format']) && isset($_POST['time_format_custom']) && '\c\u\s\t\o\m' == wp_unslash( $_POST['time_format'] ) )
                $_POST['time_format'] = $_POST['time_format_custom'];
            // Map UTC+- timezones to gmt_offsets and set timezone_string to empty.
            if ( !empty($_POST['timezone_string']) && preg_match('/^UTC[+-]/', $_POST['timezone_string']) ) {
                $_POST['gmt_offset'] = $_POST['timezone_string'];
                $_POST['gmt_offset'] = preg_replace('/UTC\+?/', '', $_POST['gmt_offset']);
                $_POST['timezone_string'] = '';
            }

            // Handle translation install.
            if ( ! empty( $_POST['WPLANG'] ) ) {
                require_once( ABSPATH . 'wp-admin/includes/translation-install.php' );
                if ( wp_can_install_language_pack() ) {
                    $language = wp_download_language_pack( $_POST['WPLANG'] );
                    if ( $language ) {
                        $_POST['WPLANG'] = $language;
                    }
                }
            }
            
            $whitelist_options = array(
                'general' => array( 'blogname', 'blogdescription', 'gmt_offset', 'date_format', 'time_format', 'start_of_week', 'timezone_string', 'WPLANG' ),
            );        
//            $whitelist_options['general'][] = 'siteurl';
//            $whitelist_options['general'][] = 'home';
            $whitelist_options['general'][] = 'admin_email';
            $whitelist_options['general'][] = 'users_can_register';
            $whitelist_options['general'][] = 'default_role';
       
            $whitelist_general = $whitelist_options[ 'general' ];
        

            foreach ( $whitelist_general as $option ) {                    
                $option = trim( $option );
                $value = null;
                if ( isset( $_POST[ $option ] ) ) {
                    $value = $_POST[ $option ];
                    if ( ! is_array( $value ) )
                        $value = trim( $value );
                    $value = wp_unslash( $value );

                    if ($option == 'admin_email') {
                        if (!preg_match('/^[a-zA-Z0-9_.+-]+@[a-zA-Z0-9-]+\.[a-zA-Z0-9-.]+$/is', $value)) {
                            $value = $this->get_option('admin_email');
                        }
                    }

                }                                        
                $this->update_option( $option, $value );
            }
            
            update_option('mainwp_wpgeneral_checked_fields', $_POST['checked_bsm_settings']);
            
            add_settings_error('mainwp_wpgeneral_general', 'settings_updated', __('Settings saved.'), 'updated');
            set_transient('settings_errors', get_settings_errors(), 30);
            set_transient('bsm_save_settings_nonce', wp_create_nonce( 'bsm_nonce' ), 30);

            $goback = add_query_arg( 'settings-updated', 'true',  wp_get_referer() );
            wp_redirect( $goback );
            exit;
        }
    }

    public function gen_settings() {        

    $timezone_format = _x('Y-m-d H:i:s', 'timezone date format');
    
    $selected_fields = get_option('mainwp_wpgeneral_checked_fields', array());
    
    if (!is_array($selected_fields))
        $selected_fields = array();
    
    ?>     
    
        <?php settings_fields('mainwp_wpgeneral_general'); ?>

        <table class="widefat striped" id="mainwp-wpgs-table-settings">
            <thead>
                <tr>        
                <th scope="col" id="cb" class="manage-column column-cb check-column"><label class="screen-reader-text" for="cb-select-all-1">Select All</label><input id="cb-select-all-1" type="checkbox"></th>
                <td>&nbsp;</td>
                <td>&nbsp;</td>
                </tr>
            </thead>
            <tfoot>
                 <tr>        
                <th scope="col" id="cb" class="manage-column column-cb check-column"><label class="screen-reader-text" for="cb-select-all-2">Select All</label><input id="cb-select-all-2" type="checkbox"></th>
                <td>&nbsp;</td>
                <td>&nbsp;</td>
                </tr>
            </tfoot>
        <tbody>
        <tr>
        <?php $this->checked_field('blogname'); ?><th scope="row"><label for="blogname"><?php _e('Site Title') ?></label></th>
        <td><input name="blogname" type="text" id="blogname" value="<?php echo esc_attr($this->get_option('blogname')); ?>" class="regular-text" /></td>
        </tr>
        <tr>
        <?php $this->checked_field('blogdescription'); ?><th scope="row"><label for="blogdescription"><?php _e('Tagline') ?></label></th>
        <td><input name="blogdescription" type="text" id="blogdescription" aria-describedby="tagline-description" value="<?php echo esc_attr($this->get_option('blogdescription')); ?>" class="regular-text" />
        <p class="description" id="tagline-description"><?php _e( 'In a few words, explain what this site is about.' ) ?></p></td>
        </tr>

<!--        <tr>
        <?php $this->checked_field('siteurl'); ?><th scope="row"><label for="siteurl"><?php _e('WordPress Address (URL)') ?></label></th>
        <td><input name="siteurl" type="url" id="siteurl" value="<?php echo esc_attr($this->get_option( 'siteurl' )); ?>" class="regular-text code" /></td>
        </tr>-->
<!--        <tr>
        <?php $this->checked_field('home'); ?><th scope="row"><label for="home"><?php _e('Site Address (URL)') ?></label></th>
        <td><input name="home" type="url" id="home" aria-describedby="home-description" value="<?php echo esc_attr($this->get_option( 'home' )); ?>" class="regular-text code" />
        </tr>-->
        <tr>
        <?php $this->checked_field('admin_email'); ?><th scope="row"><label for="admin_email"><?php _e('Email Address') ?> </label></th>
        <td><input name="admin_email" type="email" id="admin_email" aria-describedby="admin-email-description" value="<?php echo esc_attr($this->get_option( 'admin_email' )); ?>" class="regular-text ltr" />
        <p class="description" id="admin-email-description"><?php _e( 'This address is used for admin purposes, like new user notification.' ) ?></p></td>
        </tr>
        <tr>
        <?php $this->checked_field('users_can_register'); ?><th scope="row"><?php _e('Membership') ?></th>
        <td> <fieldset><legend class="screen-reader-text"><span><?php _e('Membership') ?></span></legend><label for="users_can_register">
        <input name="users_can_register" type="checkbox" id="users_can_register" value="1" <?php checked('1', $this->get_option('users_can_register')); ?> />
        <?php _e('Anyone can register') ?></label>
        </fieldset></td>
        </tr>
        <tr>
        <?php $this->checked_field('default_role'); ?><th scope="row"><label for="default_role"><?php _e('New User Default Role') ?></label></th>
        <td>
        <select name="default_role" id="default_role"><?php wp_dropdown_roles( $this->get_option('default_role') ); ?></select>
        </td>
        </tr>
        <?php
        $languages = get_available_languages();
        $translations = wp_get_available_translations();
        if ( ! empty( $languages ) || ! empty( $translations ) ) {
            ?>
            <tr>
                <?php $this->checked_field('WPLANG'); ?><th width="33%" scope="row"><label for="WPLANG"><?php _e( 'Site Language' ); ?></label></th>
                <td>
                    <?php
                    $locale = $this->get_option('WPLANG');   
                    
                    if ( empty( $locale ) ) {
                        $locale = 'en_US';
                    }
                    
                    if ( ! in_array( $locale, $languages ) ) {
                        $locale = '';
                    }

                    wp_dropdown_languages( array(
                        'name'         => 'WPLANG',
                        'id'           => 'WPLANG',
                        'selected'     => $locale,
                        'languages'    => $languages,
                        'translations' => $translations,
                        'show_available_translations' => true // ( ! is_multisite() || is_super_admin() ) && wp_can_install_language_pack(),
                    ) );

                    ?>
                </td>
            </tr>
            <?php
        }
        ?>
        <tr>
        <?php
        $current_offset = $this->get_option('gmt_offset');
        $tzstring = $this->get_option('timezone_string');

        $check_zone_info = true;

        // Remove old Etc mappings. Fallback to gmt_offset.
        if ( false !== strpos($tzstring,'Etc/GMT') )
            $tzstring = '';

        if ( empty($tzstring) ) { // Create a UTC+- zone if no timezone string exists
            $check_zone_info = false;
            if ( 0 == $current_offset )
                $tzstring = 'UTC+0';
            elseif ($current_offset < 0)
                $tzstring = 'UTC' . $current_offset;
            else
                $tzstring = 'UTC+' . $current_offset;
        }

        ?>
        <?php $this->checked_field('timezone_string'); ?><th scope="row"><label for="timezone_string"><?php _e('Timezone') ?></label></th>
        <td>

        <select id="timezone_string" name="timezone_string" aria-describedby="timezone-description">
        <?php echo wp_timezone_choice($tzstring); ?>
        </select>

        <p class="description" id="timezone-description"><?php _e( 'Choose a city in the same timezone as you.' ); ?></p>

        <p class="timezone-info">
            <span id="utc-time"><?php
                /* translators: 1: UTC abbreviation, 2: UTC time */
                printf( __( 'Universal time (%1$s) is %2$s.' ),
                    '<abbr>' . __( 'UTC' ) . '</abbr>',
                    '<code>' . date_i18n( $timezone_format, false, 'gmt' ) . '</code>'
                );
            ?></span>
        <?php if ( $this->get_option( 'timezone_string' ) || ! empty( $current_offset ) ) : ?>
            <span id="local-time"><?php
                /* translators: %s: local time */
                printf( __( 'Local time is %s.' ),
                    '<code>' . date_i18n( $timezone_format ) . '</code>'
                );
            ?></span>
        <?php endif; ?>
        </p>

        <?php if ( $check_zone_info && $tzstring ) : ?>
        <p class="timezone-info">
        <span>
            <?php
            // Set TZ so localtime works.
            date_default_timezone_set($tzstring);
            $now = localtime(time(), true);
            if ( $now['tm_isdst'] )
                _e('This timezone is currently in daylight saving time.');
            else
                _e('This timezone is currently in standard time.');
            ?>
            <br />
            <?php
            $allowed_zones = timezone_identifiers_list();

            if ( in_array( $tzstring, $allowed_zones) ) {
                $found = false;
                $date_time_zone_selected = new DateTimeZone($tzstring);
                $tz_offset = timezone_offset_get($date_time_zone_selected, date_create());
                $right_now = time();
                foreach ( timezone_transitions_get($date_time_zone_selected) as $tr) {
                    if ( $tr['ts'] > $right_now ) {
                        $found = true;
                        break;
                    }
                }

                if ( $found ) {
                    echo ' ';
                    $message = $tr['isdst'] ?
                        /* translators: %s: date and time  */
                        __( 'Daylight saving time begins on: %s.')  :
                        /* translators: %s: date and time  */
                        __( 'Standard time begins on: %s.' );
                    // Add the difference between the current offset and the new offset to ts to get the correct transition time from date_i18n().
                    printf( $message,
                        '<code>' . date_i18n(
                            __( 'F j, Y' ) . ' ' . __( 'g:i a' ),
                            $tr['ts'] + ( $tz_offset - $tr['offset'] )
                        ) . '</code>'
                    );
                } else {
                    _e( 'This timezone does not observe daylight saving time.' );
                }
            }
            // Set back to UTC.
            date_default_timezone_set('UTC');
            ?>
            </span>
        </p>
        <?php endif; ?>
        </td>

        </tr>
        <tr>
        <?php $this->checked_field('date_format'); ?><th scope="row"><?php _e('Date Format') ?></th>
        <td>
            <fieldset><legend class="screen-reader-text"><span><?php _e('Date Format') ?></span></legend>
        <?php
            /**
            * Filter the default date formats.
            *
            * @since 2.7.0
            * @since 4.0.0 Added ISO date standard YYYY-MM-DD format.
            *
            * @param array $default_date_formats Array of default date formats.
            */
            $date_formats = array_unique( apply_filters( 'date_formats', array( __( 'F j, Y' ), 'Y-m-d', 'm/d/Y', 'd/m/Y' ) ) );

            $custom = true;

            foreach ( $date_formats as $format ) {
                echo "\t<label><input type='radio' name='date_format' value='" . esc_attr( $format ) . "'";
                if ( $this->get_option('date_format') === $format ) { // checked() uses "==" rather than "==="
                    echo " checked='checked'";
                    $custom = false;
                }
                echo ' /> <span class="date-time-text format-i18n">' . date_i18n( $format ) . '</span><code>' . esc_html( $format ) . "</code></label><br />\n";
            }

            echo '<label><input type="radio" name="date_format" id="date_format_custom_radio" value="\c\u\s\t\o\m"';
            checked( $custom );
            echo '/> <span class="date-time-text date-time-custom-text">' . __( 'Custom:' ) . '<span class="screen-reader-text"> ' . __( 'enter a custom date format in the following field' ) . '</span></label>' .
                '<label for="date_format_custom" class="screen-reader-text">' . __( 'Custom date format:' ) . '</label>' .
                '<input type="text" name="date_format_custom" id="date_format_custom" value="' . esc_attr( $this->get_option( 'date_format' ) ) . '" class="small-text" /></span>' .
                '<span class="screen-reader-text">' . __( 'example:' ) . ' </span> <span class="example">' . date_i18n( $this->get_option( 'date_format' ) ) . '</span>' .
                "<span class='spinner'></span>\n";
        ?>
            </fieldset>
        </td>
        </tr>        
        <tr>
        <?php $this->checked_field('time_format'); ?><th scope="row"><?php _e('Time Format') ?></th>
        <td>
            <fieldset><legend class="screen-reader-text"><span><?php _e('Time Format') ?></span></legend>
        <?php
            /**
            * Filter the default time formats.
            *
            * @since 2.7.0
            *
            * @param array $default_time_formats Array of default time formats.
            */
            $time_formats = array_unique( apply_filters( 'time_formats', array( __( 'g:i a' ), 'g:i A', 'H:i' ) ) );

            $custom = true;

            foreach ( $time_formats as $format ) {
                echo "\t<label><input type='radio' name='time_format' value='" . esc_attr( $format ) . "'";
                if ( $this->get_option('time_format') === $format ) { // checked() uses "==" rather than "==="
                    echo " checked='checked'";
                    $custom = false;
                }
                echo ' /> <span class="date-time-text format-i18n">' . date_i18n( $format ) . '</span><code>' . esc_html( $format ) . "</code></label><br />\n";
            }

            echo '<label><input type="radio" name="time_format" id="time_format_custom_radio" value="\c\u\s\t\o\m"';
            checked( $custom );
            echo '/> <span class="date-time-text date-time-custom-text">' . __( 'Custom:' ) . '<span class="screen-reader-text"> ' . __( 'enter a custom time format in the following field' ) . '</span></label>' .
                '<label for="time_format_custom" class="screen-reader-text">' . __( 'Custom time format:' ) . '</label>' .
                '<input type="text" name="time_format_custom" id="time_format_custom" value="' . esc_attr( $this->get_option( 'time_format' ) ) . '" class="small-text" /></span>' .
                '<span class="screen-reader-text">' . __( 'example:' ) . ' </span> <span class="example">' . date_i18n( $this->get_option( 'time_format' ) ) . '</span>' .
                "<span class='spinner'></span>\n";

            echo "\t<p class='date-time-doc'>" . __('<a href="https://codex.wordpress.org/Formatting_Date_and_Time">Documentation on date and time formatting</a>.') . "</p>\n";
        ?>
            </fieldset>
        </td>
        </tr>
        <tr>
        <?php $this->checked_field('start_of_week'); ?><th scope="row"><label for="start_of_week"><?php _e('Week Starts On') ?></label></th>
        <td><select name="start_of_week" id="start_of_week">
        <?php
        /**
         * @global WP_Locale $wp_locale
         */
        global $wp_locale;

        for ($day_index = 0; $day_index <= 6; $day_index++) :
            $selected = ($this->get_option('start_of_week') == $day_index) ? 'selected="selected"' : '';
            echo "\n\t<option value='" . esc_attr($day_index) . "' $selected>" . $wp_locale->get_weekday($day_index) . '</option>';
        endfor;
        ?>
        </select></td>
        </tr>
        <?php do_settings_fields('general', 'default'); ?>

        </tbody>
        </table>
        <br>
        <input type="hidden" name="mainwp_wpgeneral_selected_sites" value=""/>
        <input type="hidden" name="mainwp_wpgeneral_selected_groups" value=""/>
        
        <input name="submit" id="submit" onclick="return mainwp_wpgeneral_save_settings_onclick();" class="button button-primary" value="Save Changes" type="submit">

        <script type="text/javascript">
            jQuery(document).ready(function($){
                
                $("input[name='date_format']").click(function(){
                    if ( "date_format_custom_radio" != $(this).attr("id") )
                        $( "input[name='date_format_custom']" ).val( $( this ).val() ).siblings( '.example' ).text( $( this ).parent( 'label' ).children( '.format-i18n' ).text() );
                });
                $("input[name='date_format_custom']").focus(function(){
                    $( '#date_format_custom_radio' ).prop( 'checked', true );
                });

                $("input[name='time_format']").click(function(){
                    if ( "time_format_custom_radio" != $(this).attr("id") )
                        $( "input[name='time_format_custom']" ).val( $( this ).val() ).siblings( '.example' ).text( $( this ).parent( 'label' ).children( '.format-i18n' ).text() );
                });
                $("input[name='time_format_custom']").focus(function(){
                    $( '#time_format_custom_radio' ).prop( 'checked', true );
                });
                $("input[name='date_format_custom'], input[name='time_format_custom']").change( function() {
                    var format = $(this);
                    format.siblings( '.spinner' ).addClass( 'is-active' );
                    $.post(ajaxurl, {
                            action: 'date_format_custom' == format.attr('name') ? 'date_format' : 'time_format',
                            date : format.val()
                        }, function(d) { format.siblings( '.spinner' ).removeClass( 'is-active' ); format.siblings('.example').text(d); } );
                });

                var languageSelect = $( '#WPLANG' );
                $( 'form' ).submit( function() {
                    // Don't show a spinner for English and installed languages,
                    // as there is nothing to download.
                    if ( ! languageSelect.find( 'option:selected' ).data( 'installed' ) ) {
                        $( '#submit', this ).after( '<span class="spinner language-install-spinner" />' );
                    }
                });
            });
        </script>

        <?php
    }
    
    
    public function gen_saving_settings() {                                    
                 
        $selected_sites = get_option('mainwp_wpgeneral_selected_sites', array());
        $selected_groups = get_option('mainwp_wpgeneral_selected_groups', array());
        
        if ( ( ! is_array( $selected_sites ) || count( $selected_sites ) == 0) && ( ! is_array( $selected_groups ) || count( $selected_groups ) == 0) ) {
            $error = __( 'Please select a website or group', 'mainwp' );     
            echo '<div class="mainwp_info-box-yellow">' . $error . '</div>';
            return;            
        }
        
        if ( $selected_sites ) {			
			$websites = MainWP_DB::Instance()->getWebsitesByIds( $selected_sites );
		} else if ( $selected_groups ) {				
			$websites = MainWP_DB::Instance()->getWebsitesByGroupIds( $selected_groups );
		}
                        
        if (!$websites ) {
            $error = __('No websites were found.', 'mainwp');
            echo '<div class="mainwp_info-box-yellow">' . $error . '</div>';
            return;
        }
        
        ?>
        <div id="mainwp_wpgeneral_saving_content">           
            <?php  
            foreach ( $websites as $website ) {
                echo '<div><strong>' . $website->name . '</strong>: ';
                echo '<span class="siteItemProcess" action="" site-id="' . $website->id . '" status="queue"><span class="status">Queue ...</span> <i style="display: none;" class="fa fa-spinner fa-pulse"></i></span>';
                echo '</div><br />';
            }                                                                                  
            ?>               
        </div>
        
        <script type="text/javascript">
            jQuery(document).ready(function($){
                mainwp_wpgeneral_actions_start();
            });
        </script>
        
        <?php            
	}
    
}
