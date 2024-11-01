<?php

/*
 * Plugin Name: Simple GAM
 * Plugin URI: https://wordpress.org/plugins/simple-dfp/
 * Description: Finally an easy plugin to add GAM blocks into WP - shortcode and template function
 * Author: Termel
 * Version: 1.3.3
 * Author URI: https://www.termel.fr/
 */
if (! defined('ABSPATH')) {
    exit(); // Exit if accessed directly
}

require_once (sprintf("%s/SettingsPage.php", dirname(__FILE__)));

use SimpleGAM\SimpleGAM_SettingsPage;

function SimpleGAM_log($message)
{
    if (WP_DEBUG === true) {
        if (is_array($message) || is_object($message)) {
            error_log(print_r($message, true));
        } else {
            error_log($message);
        }
    }
}

// Allow theme to parse shortcodes in widgets

if (! has_filter('widget_text', 'do_shortcode')) {
    add_filter('widget_text', 'do_shortcode');
}

if (! class_exists('simplegam_main')) {

    class simplegam_main
    {

        public static $instance = NULL;

        private $options;

        public static $allAdIds = array();

        protected $simplegam_settings = null;

        function setUpLogger()
        {
            $log4phpVersion = '2.3.0';
            $path_to_log4php = sprintf('%s/libs/log4php/' . $log4phpVersion . '/Logger.php', dirname(__FILE__));
            if (! class_exists('LoggerConfiguratorDefault')) {
                include_once ($path_to_log4php);
            }
            
            $dir = plugin_dir_path(__FILE__);
            $logFilesPath = $dir . '/logs/simple_dfp-%s.log';
            
            Logger::configure(array(
                'rootLogger' => array(
                    'appenders' => array(
                        'simplegam_default'
                    ),
                    'level' => 'debug'
                ),
                'appenders' => array(
                    'simplegam_default' => array(
                        'class' => 'LoggerAppenderDailyFile',
                        'layout' => array(
                            'class' => 'LoggerLayoutPattern',
                            'params' => array(
                                'conversionPattern' => "%date{Y-m-d H:i:s,u} %logger %-5level %F{10}:%L %msg%n"
                            )
                        ),
                        
                        'params' => array(
                            'file' => strval($logFilesPath),
                            'append' => true,
                            'datePattern' => "Y-m-d"
                        )
                    )
                )
            ));
            Logger::getLogger("simpledfp")->trace("Logger up!...");
        }

        public function __construct()
        {
            SimpleGam_log("Init object...");
            $this->setUpLogger();
            
            add_action('init', array(
                $this,
                'create_simplegam_ad_type'
            ));
            add_action('add_meta_boxes', array(
                $this,
                'add_ads_metaboxes'
            ));
            /*
             * add_action('admin_menu', array(
             * $this,
             * 'simplegam_create_menus'
             * ));
             */
            // if (is_admin()) {
            $this->simplegam_settings = new SimpleGAM_SettingsPage();
            SimpleGam_log("Settings created!");
            SimpleGam_log($this->simplegam_settings);
            /*
             * } else {
             * SimpleGam_log("Not an admin page created!");
             *
             * }
             */
            
            /*
             *
             * if (is_multisite()) {
             * Logger::getLogger("simpledfp")->trace("Multisite");
             * add_action('admin_init', array(
             * $this,
             * 'simplegam_network_admin_init'
             * ));
             * add_action('network_admin_menu', array(
             * $this,
             * 'simplegam_create_menus'
             * ));
             *
             *
             * } else {
             * Logger::getLogger("simpledfp")->debug("Single site");
             * if (is_admin()) { // admin actions
             *
             * add_action('admin_init', array(
             * $this,
             * 'simplegam_admin_init'
             * ));
             * add_action('admin_menu', array(
             * $this,
             * 'simplegam_create_menus'
             * ));
             * }
             * }
             */
            add_action('wp_enqueue_scripts', array(
                $this,
                'simplegam_frontend_stylesheet'
            ));
            
            // Add hook for admin <head></head>
            add_action('wp_head', array(
                $this,
                'simplegam_header_custom_js'
            ));
            // add_action('admin_head', 'your_function');
            
            add_shortcode('simplegam_block', array(
                $this,
                'simplegam_block_shortcode'
            ));
            // backward compatible:
            add_shortcode('simpledfp_block', array(
                $this,
                'simplegam_block_shortcode'
            ));
            add_action('save_post', array(
                $this,
                'simplegam_save_bloc_code_meta'
            ), 1, 2); // save the custom fields
            add_action('save_post', array(
                $this,
                'simplegam_save_size_meta'
            ), 1, 2); // save the custom fields
            
            Logger::getLogger("simpledfp")->debug("Simple GAM built");
        }

        function InDesignHTML2Post_setup_menu()
        {
            $menuTitle = __('New Post from InDesign HTML export', 'indesign2post');
            
            add_submenu_page('edit.php', $menuTitle, $menuTitle, 'delete_others_posts', 'new-post-from-id-html', array(
                $this,
                'InDesignHTML2Post_upload_page'
            ));
        }

        function getDFPNetworkCode()
        {
            $network_code = $this->getSettings('network_tag');
            
            return $network_code;
        }

        function simplegam_block_shortcode($atts)
        {
            Logger::getLogger("default")->debug("### simpledfp block shortcode ###");
            // if (! is_admin () /*|| is_admin()*/ ) {
            // show google DFP code
            
            Logger::getLogger("default")->debug("### not admin ###");
            
            extract(shortcode_atts(array(
                'ad_id' => '-1'
            ), $atts));
            
            Logger::getLogger("default")->debug($atts);
            $ad_id = str_replace(' ', '', $ad_id);
            if ($ad_id == null || empty($ad_id)) {
                $msg = "No DFP Ad Id provided in shortcode " . $ad_id;
                Logger::getLogger("default")->error($msg);
                return $msg;
            } else {
                $args = array(
                    'numberposts' => - 1,
                    'post_type' => 'simpledfp_ad',
                    'post_status' => 'publish'
                );
                
                $all_ads = get_post($ad_id, $args);
                if (empty($all_ads)) {
                    $msg = "No Simple GAM Ad with this id (provided in shortcode) " . $ad_id;
                    Logger::getLogger("default")->error($msg);
                    return $msg;
                }
            }
            Logger::getLogger("default")->debug($ad_id);
            $network_code = $this->getDFPNetworkCode();
            if ($network_code == null || empty($network_code)) {
                $msg = "No DFP network code provided : '" . $network_code . "'";
                Logger::getLogger("default")->error($msg);
                return $msg;
            }
            // zaet // $this->options['id_number']
            // $current_post = getPost($ad_id);
            Logger::getLogger("default")->debug($network_code);
            /*
             * $allMetas = get_post_meta($ad_id);
             * Logger::getLogger ( "default" )->debug ( $allMetas);
             */
            $block_code = get_post_meta($ad_id, '_block_code', true);
            Logger::getLogger("default")->debug("block: " . $block_code);
            $sizes = get_post_meta($ad_id, '_size', true); // get from WWWxHHH
            Logger::getLogger("default")->debug("sizes: " . $sizes);
            $cssSizes = $this->get_css_size_from_text($sizes);
            $generatedId = self::$allAdIds[$ad_id];
            Logger::getLogger("default")->debug($generatedId);
            
            $result = '';
            // $result .= '<span style="font-size:0.7em;">'.$ad_id.'</span>'.
            $pluginName = plugin_basename(__FILE__);
            $result .= '<!-- ' . $pluginName . ' /' . $network_code . '/' . $block_code . ' -->';
            $result .= "<div id='" . $generatedId . "' style='" . $cssSizes . "'>";
            $result .= "<script>";
            // $result .= "if(window.googletag && googletag.pubadsReady) {";
            $result .= "googletag.cmd.push(function() {";
            $result .= "googletag.display('" . $generatedId . "'); });";
            // TypeError: googletag.pubads is not a function
            $result .= "googletag.pubads().refresh();";
            // $result .= "}";
            $result .= "</script>";
            $result .= "</div>";
            
            Logger::getLogger("simpledfp")->debug($result);
            
            // "div-gpt-ad-[nombre aléatoire]-0", "div-gpt-ad-[nombre aléatoire]-1
            // 7894538425348
            
            return $result;
            // }
        }

        function add_ads_metaboxes()
        {
            add_meta_box('simplegam_bloc_code', 'DFP Block Unique Code', array(
                $this,
                'simplegam_bloc_code_callback'
            ), 'simpledfp_ad', 'side', 'default');
            
            add_meta_box('simplegam_ad_size', 'DFP Block Size (format : WWWxHHH)', array(
                $this,
                'simplegam_size_callback'
            ), 'simpledfp_ad', 'side', 'default');
            
            // add_meta_box('simplegam_bloc_name', 'Event Location', 'wpt_events_location', 'events', 'normal', 'high');
        }

        function simplegam_size_callback()
        {
            global $post;
            // Logger::getLogger("simpledfp")->debug("simplegam_bloc_code_callback");
            // Noncename needed to verify where the data originated
            echo '<input type="hidden" name="sizemeta_noncename" id="sizemeta_noncename" value="' . wp_create_nonce(plugin_basename(__FILE__)) . '" />';
            
            // Get the location data if its already been entered
            $size = get_post_meta($post->ID, '_size', true);
            
            // Echo out the field
            echo '<input type="text" name="_size" value="' . $size . '" class="widefat" />';
        }

        function simplegam_save_size_meta($post_id, $post)
        {
            
            // verify this came from the our screen and with proper authorization,
            // because save_post can be triggered at other times
            if (! wp_verify_nonce($_POST['sizemeta_noncename'], plugin_basename(__FILE__))) {
                return $post->ID;
            }
            
            // Is the user allowed to edit the post or page?
            if (! current_user_can('edit_post', $post->ID))
                return $post->ID;
            
            // OK, we're authenticated: we need to find and save the data
            // We'll put it into an array to make it easier to loop though.
            
            $size['_size'] = $_POST['_size'];
            
            // Add values of $events_meta as custom fields
            
            foreach ($size as $key => $value) { // Cycle through the $events_meta array!
                if ($post->post_type == 'revision')
                    return; // Don't store custom data twice
                $value = implode(',', (array) $value); // If $value is an array, make it a CSV (unlikely)
                if (get_post_meta($post->ID, $key, FALSE)) { // If the custom field already has a value
                    update_post_meta($post->ID, $key, $value);
                } else { // If the custom field doesn't have a value
                    add_post_meta($post->ID, $key, $value);
                }
                if (! $value)
                    delete_post_meta($post->ID, $key); // Delete if blank
            }
        }

        function simplegam_bloc_code_callback()
        {
            global $post;
            // Logger::getLogger("simpledfp")->debug("simplegam_bloc_code_callback");
            // Noncename needed to verify where the data originated
            echo '<input type="hidden" name="blockmeta_noncename" id="blockmeta_noncename" value="' . wp_create_nonce(plugin_basename(__FILE__)) . '" />';
            
            // Get the location data if its already been entered
            $block_code = get_post_meta($post->ID, '_block_code', true);
            
            // Echo out the field
            echo '<input type="text" name="_block_code" value="' . $block_code . '" class="widefat" />';
        }

        function simplegam_save_bloc_code_meta($post_id, $post)
        {
            
            // verify this came from the our screen and with proper authorization,
            // because save_post can be triggered at other times
            if (! wp_verify_nonce($_POST['blockmeta_noncename'], plugin_basename(__FILE__))) {
                return $post->ID;
            }
            
            // Is the user allowed to edit the post or page?
            if (! current_user_can('edit_post', $post->ID))
                return $post->ID;
            
            // OK, we're authenticated: we need to find and save the data
            // We'll put it into an array to make it easier to loop though.
            
            $block_code['_block_code'] = $_POST['_block_code'];
            
            // Add values of $events_meta as custom fields
            
            foreach ($block_code as $key => $value) { // Cycle through the $events_meta array!
                if ($post->post_type == 'revision')
                    return; // Don't store custom data twice
                $value = implode(',', (array) $value); // If $value is an array, make it a CSV (unlikely)
                if (get_post_meta($post->ID, $key, FALSE)) { // If the custom field already has a value
                    update_post_meta($post->ID, $key, $value);
                } else { // If the custom field doesn't have a value
                    add_post_meta($post->ID, $key, $value);
                }
                if (! $value)
                    delete_post_meta($post->ID, $key); // Delete if blank
            }
        }

        public function update_simplegam_options()
        {
            if (isset($_POST['submit'])) {
                
                // verify authentication (nonce)
                if (! isset($_POST['simplegam_nonce']))
                    return;
                
                // verify authentication (nonce)
                if (! wp_verify_nonce($_POST['simplegam_nonce'], 'simplegam_nonce'))
                    return;
                
                return $this->updateSDFPCSettings();
            }
        }

        public function updateSDFPCSettings()
        {
            $settings = array();
            
            if (isset($_POST['network_tag']) && trim($_POST['network_tag'])) {
                $settings['network_tag'] = sanitize_text_field($_POST['network_tag']);
            }
            
            if (isset($_POST['email']) && is_email($_POST['email'])) {
                $settings['email'] = esc_attr($_POST['email']);
            }
            
            if (isset($_POST['age_range']) && trim($_POST['age_range'])) {
                $settings['age_range'] = sanitize_text_field($_POST['age_range']);
            }
            
            if ($settings) {
                // update new settings
                update_site_option('simplegam_settings', $settings);
            } else {
                // empty settings, revert back to default
                delete_site_option('simplegam_settings');
            }
            
            $this->updated = true;
        }

        public function getSettings($setting = '')
        {
            $result = '';
            SimpleGAM_log("get setting " . $setting);
            if (null != $this->simplegam_settings) {
                
                $result = $this->simplegam_settings->getOptions()[$setting];
                SimpleGAM_log("setting value " . $result);
            } else {
                SimpleGAM_log("no settings!");
                SimpleGAM_log($this);
            }
            return $result;
        }

        function simplegam_network_settings_page_callback()
        {
            Logger::getLogger("simpledfp")->debug("simplegam_network_settings_page_callback");
            if (isset($_GET['updated'])) :
                ?>
<div id="message" class="updated notice is-dismissible">
	<p><?php _e('Options saved.') ?></p>
</div>
<?php endif; ?>
<div class="wrap">
	<h1><?php _e('My GAM Network Options', 'post3872'); ?></h1>
	<form method="POST"
		action="edit.php?action=simplegam_network_settings_page"><?php
            settings_fields('simplegam_network_options');
            do_settings_sections('sdfpc');
            submit_button();
            ?>
  </form>
</div>
<?php
        }

        /**
         * This function here is hooked up to a special action and necessary to process
         * the saving of the options.
         * This is the big difference with a normal options
         * page.
         */
        function simplegam_update_network_options()
        {
            Logger::getLogger("simpledfp")->debug("simplegam_update_network_options");
            // Make sure we are posting from our options page. There's a little surprise
            // here, on the options page we used the 'post3872_network_options_page'
            // slug when calling 'settings_fields' but we must add the '-options' postfix
            // when we check the referer.
            
            check_admin_referer('simplegam_network_settings_page-options');
            
            // This is the list of registered options.
            global $new_whitelist_options;
            $options = $new_whitelist_options['simplegam_network_settings_page'];
            
            // Go through the posted data and save only our options. This is a generic
            // way to do this, but you may want to address the saving of each option
            // individually.
            foreach ($options as $option) {
                if (isset($_POST[$option])) {
                    // Save our option with the site's options.
                    // If we registered a callback function to sanitizes the option's
                    // value it will be called here (see register_setting).
                    update_site_option($option, $_POST[$option]);
                } else {
                    // If the option is not here then delete it. It depends on how you
                    // want to manage your defaults however.
                    delete_site_option($option);
                }
            }
            
            // At last we redirect back to our options page.
            wp_redirect(add_query_arg(array(
                'page' => 'simplegam_network_settings_page',
                'updated' => 'true'
            ), network_admin_url('settings.php')));
            exit();
        }

        function simplegam_settings_page_callback()
        {
            Logger::getLogger("simpledfp")->debug("simplegam_settings_page_callback");
            ?>
<h1 class="">Simple GAM Connector Settings</h1>
<div>
	<h2>My custom plugin</h2>
	Options relating to the Custom Plugin.
	<form action="options.php" method="post">
<?php settings_fields('simplegam_options'); ?>
<?php do_settings_sections('sdfpc'); ?>
 
<input name="Submit" type="submit"
			value="<?php esc_attr_e('Save Changes'); ?>" />
	</form>
</div>

<?php
        }

        function simplegam_network_admin_init()
        {
            // Logger::getLogger("simpledfp")->debug("simplegam_network_admin_init");
            $slug = 'simple_gam';
            register_setting('simplegam_group', 'simplegam_options', array(
                $this,
                'simplegam_options_validate'
            ));
            add_settings_section('simplegam_main', 'Simple GAM Settings - GAM', array(
                $this,
                'simplegam_section_text'
            ), $slug);
            add_settings_field('simplegam_network_code', 'GAM Network code (like: XXXXXXXX)', array(
                $this,
                'simplegam_setting_string'
            ), $slug, 'simplegam_main');
            add_settings_field('simplegam_gam_keys', 'GAM Keys', array(
                $this,
                'simplegam_gam_keys'
            ), $slug, 'simplegam_main');
        }

        public function simplegam_sanitize($input)
        {
            $new_input = array();
            
            if (isset($input['network_tag']))
                $new_input['network_tag'] = sanitize_text_field($input['network_tag']);
            
            if (isset($input['dfp_gam_keys']))
                $new_input['dfp_gam_keys'] = sanitize_text_field($input['dfp_gam_keys']);
            
            return $new_input;
        }

        function simplegam_admin_init()
        {
            $slug = 'simple_dfp';
            // Logger::getLogger("simpledfp")->debug("simplegam_admin_init");
            register_setting('simplegam_group', 'simplegam_options', array(
                $this,
                'simplegam_sanitize'
            ));
            add_settings_section('simplegam_main', 'Main Settings', null, $slug);
            
            // network_tag
            add_settings_field('network_tag', 'DFP Network code (like: XXXXXXXX)', array(
                $this,
                'simplegam_setting_string'
            ), $slug, 'simplegam_main');
        }

        function simplegam_section_text()
        {
            echo '<p>Main description of this section here.</p>';
        }

        function simplegam_setting_string()
        {
            $options = get_option('simplegam_options');
            echo "<input id='network_tag' name='simplegam_options[network_tag]' size='10' type='text' value='{$options['network_tag']}' />";
        }

        function simplegam_gam_keys()
        {
            $options = get_option('simplegam_options');
            
            $default = 'key1,key2,key3';
            $value = isset($options['dfp_gam_keys']) ? $options['dfp_gam_keys'] : $default;
            echo $value;
            echo "<input id='dfp_gam_keys' name='simplegam_options[dfp_gam_keys]' size='30' type='text' value='" . $value . "' />";
        }

        function simplegam_options_validate($input)
        {
            Logger::getLogger("simpledfp")->debug("simplegam_options_validate");
            $options = get_option('simplegam_options');
            $options['network_tag'] = trim($input['network_tag']);
            
            return $options;
        }

        function get_js_size_from_text($size)
        {
            $sizeArray = explode('x', $size);
            $sizeForDFPJs = '[' . implode(',', $sizeArray) . ']';
            return $sizeForDFPJs;
        }

        function get_css_size_from_text($size)
        {
            $sizeArray = explode('x', $size);
            $cssSize = 'width:' . array_shift($sizeArray) . 'px;';
            // next($sizeArray);
            $cssSize .= 'height:' . array_shift($sizeArray) . 'px;';
            return $cssSize;
        }

        function simplegam_header_custom_js()
        {
            // Logger::getLogger("simpledfp")->debug("injecting header simplegam_header_custom_js...");
            Logger::getLogger("default")->debug("### simplegam_header_custom_js ###");
            $fromGoogleDFP = "<script async='async' src='https://www.googletagservices.com/tag/js/gpt.js'></script>";
            
            $initScript = "<script>var googletag = googletag || {};googletag.cmd = googletag.cmd || [];</script>";
            // echo "<script async='async' src='https://www.googletagservices.com/tag/js/gpt.js'></script>";
            // echo "<script>var googletag = googletag || {};googletag.cmd = googletag.cmd || [];</script>";
            $loopStart = "<script>";
            // $loopStart .= "if(window.googletag && googletag.pubadsReady) {";
            $loopStart .= "googletag.cmd.push(function() {";
            $loopContent = "";
            $loopEnd = "";
            $args = array(
                'numberposts' => - 1,
                'post_type' => 'simpledfp_ad',
                'post_status' => 'publish'
            );
            
            $all_ads = get_posts($args);
            Logger::getLogger("simpledfp")->debug("Nb of ads : " . count($all_ads));
            // $network_code = $this->getSettings('network_tag');
            $network_code = $this->getDFPNetworkCode();
            if ($network_code == null || empty($network_code)) {
                $msg = "No DFP network code provided " . $network_code;
                Logger::getLogger("default")->error($msg);
                return $msg;
            }
            $idx = 0;
            foreach ($all_ads as $ad) {
                $block_code = get_post_meta($ad->ID, '_block_code', true);
                $size = get_post_meta($ad->ID, '_size', true); // get from WWWxHHH
                $sizeForDFPJs = $this->get_js_size_from_text($size); // '['.implode(',',explode('x',$size))).']';
                $randnum = rand(1111111111111, 9999999999999);
                $generatedDivId = 'div-gpt-ad-' . $randnum . '-' . $idx;
                $loopContent .= "googletag.defineSlot('/" . $network_code . "/" . $block_code . "', " . $sizeForDFPJs . ", '" . $generatedDivId . "').addService(googletag.pubads());";
                self::$allAdIds[$ad->ID] = $generatedDivId;
                $idx ++;
            }
            
            //$loopEnd .= "googletag.pubads().collapseEmptyDivs();";
            $loopEnd .= "googletag.pubads().enableSingleRequest();";
            
            // if key / value target set for this blog, specify it here to display only ads matching target
            // googletag.pubads().setTargeting('pageType', ['news']);
            $targeting_enabled = $this->getSettings('gam_targeting');
            // https://support.google.com/admanager/answer/3072674?hl=fr
            if ($targeting_enabled) {
                if (is_multisite()) {
                    $blog_id = get_current_blog_id();
                    $id = 'keys_and_values_on_blog_' . $blog_id;
                    
                    $json_key_values = $this->getSettings($id);
                    $key_value_array = json_decode($json_key_values, true);
                    SimpleGAM_log($id . ' ' . $json_key_values);
                    Logger::getLogger("simpledfp")->debug($key_value_array);
                    SimpleGAM_log($key_value_array);
                    // googletag.pubads().setTargeting('pageType', ['news']);
                    foreach ($key_value_array as $key => $values) {
                        $loopEnd .= "googletag.pubads().setTargeting('" . $key . "', ['" . $values . "']);";
                        // .setCollapseEmptyDiv(true);
                    }
                }
            }
            
            $loopEnd .= "googletag.pubads().collapseEmptyDivs();";
            $loopEnd .= "googletag.enableServices();";
            $loopEnd .= "});";
            
            $loopEnd .= "</script>";
            
            $result = $fromGoogleDFP . $initScript . $loopStart . $loopContent . $loopEnd; // .$collapseEmpty;
            Logger::getLogger("simpledfp")->debug($result);
            Logger::getLogger("simpledfp")->debug(self::$allAdIds);
            
            echo $result;
        }

        function create_simplegam_ad_type()
        {
            $labels = array(
                'name' => 'Simple GAM Ads',
                'singular_name' => 'Simple GAM Ad',
                'menu_name' => 'Simple GAM Ads',
                'name_admin_bar' => 'Simple GAM Ad',
                'add_new' => 'Add New',
                'add_new_item' => 'Add New Simple GAM Ad',
                'new_item' => 'New Simple GAM Ad',
                'edit_item' => 'Edit Simple GAM Ad',
                'view_item' => 'View Simple GAM Ad',
                'all_items' => 'All Simple GAM Ads',
                'search_items' => 'Search Simple GAM Ads',
                'parent_item_colon' => 'Parent Simple GAM Ad',
                'not_found' => 'No Simple GAM Ads Found',
                'not_found_in_trash' => 'No Simple GAM Ads Found in Trash'
            );
            
            $args = array(
                'labels' => $labels,
                'public' => true,
                'exclude_from_search' => false,
                'publicly_queryable' => true,
                'show_ui' => true,
                'show_in_nav_menus' => true,
                'show_in_menu' => true,
                'show_in_admin_bar' => true,
                'menu_position' => 5,
                'menu_icon' => 'dashicons-media-document',
                'capability_type' => 'post',
                'hierarchical' => false,
                'supports' => array(
                    'title',
                    'editor',
                    'author',
                    'thumbnail',
                    'excerpt',
                    'comments'
                ),
                'has_archive' => true,
                'rewrite' => array(
                    'slug' => 'sdfp_ads'
                ),
                'query_var' => true
            );
            
            register_post_type('simpledfp_ad', $args);
        }

        function simplegam_frontend_stylesheet()
        {
            $simplegam_script_js = plugins_url('/js/simpledfp.js', __FILE__);
            // simplegam_log ( 'loading script ' . $simplegam_script_js );
            
            wp_enqueue_script('simpledfp-frontend-js', $simplegam_script_js, array(
                'jquery'
            ));
            // wp_localize_script( 'dental-office-frontend-js', 'doff_ajax_object', array( 'ajax_url' => admin_url( 'admin-ajax.php' ) ) );
            wp_enqueue_style('dfpc-css', plugins_url('/css/simpledfp.css', __FILE__));
        }
    }
}

$simpledfp = new simplegam_main();
if (class_exists("simplegam_main")) {

    function simpledfpBlock($args = null)
    {
        global $simpledfp;
        Logger::getLogger("simpledfp")->debug("#### simpledfpBlock function ####");
        if (is_null($args))
            echo '';
        if (is_object($simpledfp))
            echo $simpledfp->simplegam_block_shortcode($args);
        else
            echo '';
    }
}