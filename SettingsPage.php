<?php
namespace SimpleGAM;

if (! defined('ABSPATH')) {
    die();
}

class SimpleGAM_SettingsPage
{

    /**
     * Holds the values to be used in the fields callbacks
     */
    private $options;

    private $update;

    /**
     * Start up
     */
    public function __construct()
    {
        add_action('admin_init', array(
            $this,
            'page_init'
        ));
        
        add_action(is_multisite() ? 'network_admin_menu' : 'admin_menu', array(
            $this,
            'add_simplegam_options_page'
        ));
        
        add_action('network_admin_menu', array(
            $this,
            'update_network_options'
        ));
    }

    public function update_network_options()
    {
        //echo "update_network_options";
        if (isset($_POST['submit'])) {
            
            // verify authentication (nonce)
            // $nonce_id = "simplegam_option_group" . "-options";
            $nonce_id = "simplegam_nonce";
            if (! isset($_POST[$nonce_id]) || ! wp_verify_nonce($_POST[$nonce_id], $nonce_id)) {
                // no post val or nonce check failed
                echo "Invalid nonce check";
                return;
            } else {
                return $this->update_simplegam_network_settings();
            }
        } /*else {
            echo "No sumbit in POST";
        }*/
    }

    public function implodeWithKeys($input)
    {
        $output = implode(', ', array_map(function ($v, $k) {
            return sprintf("%s='%s'", $k, $v);
        }, $input, array_keys($input)));
        
        return $output;
    }

    public function update_simplegam_network_settings()
    {
        $settings = array();
        /*
         * echo "<p>";
         * echo "update_simplegam_network_settings";
         * echo implode(" | ", $_POST);
         * echo '<br/><br/>';
         * echo $this->implodeWithKeys( $_POST['simplegam_option']);
         */
        $options = isset($_POST['simplegam_option']) ? $_POST['simplegam_option'] : null;
        if ($options) {
            
            // self::getLogger()->trace($_POST);
            if (isset($options['network_tag'])) {
                $val = trim($options['network_tag']);
                $settings['network_tag'] = sanitize_text_field($val);
            }
            if (isset($options['gam_targeting'])) {
                $settings['gam_targeting'] = $options['gam_targeting'];
            }
            
            foreach (get_sites() as $blog) {
                // foreach ($blog_ids as $blog_id){
                $blog_id = $blog->blog_id;
                $id = 'keys_and_values_on_blog_' . $blog_id;
                /*
                 * if (isset($input[$id]))
                 * $new_input[$id] = wp_kses_post($input[$id]);
                 */
                
                if (isset($options[$id])) {
                    $val = trim($options[$id]);
                    $settings[$id] = wp_kses_post(stripslashes($val));
                }
            }
            
            // self::getLogger()->trace($settings);
            // echo '<br/><br/>';
            // echo $this->implodeWithKeys($settings);
        } else {
            echo "No options in POST";
        }
        
        if (! empty($settings)) {
            // update new settings
            echo "Update network options";
            update_site_option('simplegam_option', $settings);
        } else {
            // empty settings, revert back to default
            echo "Delete network options";
            delete_site_option('simplegam_option');
        }
        
        $this->updated = true;
        // echo "</p>";
    }

    public function getOptions()
    {
        if (is_multisite()) {
            $this->options = get_site_option('simplegam_option');
        } else {
            
            $this->options = get_option('simplegam_option');
        }
        
        return $this->options;
    }

    /**
     * Add options page
     */
    public function add_simplegam_options_page()
    {
        $pageTitle = 'Simple GAM';
        $menuTitle = $pageTitle;
        $capability = 'manage_options';
        $slug = 'simplegam-setting-admin';
        if (is_multisite()) {
            add_submenu_page('settings.php', $pageTitle, $menuTitle, $capability, $slug, array(
                $this,
                'create_network_admin_page'
            ));
            return $this;
        } else {
            add_options_page($pageTitle, $menuTitle, $capability, $slug, array(
                $this,
                'create_admin_page'
            ));
        }
    }

    public function create_network_admin_page()
    {
        return $this->create_admin_page();
    }

    /**
     * Options page callback
     */
    public function create_admin_page()
    {
        $this->getOptions();
        
        ?>
<div class="wrap">
	<h1>Simple <?php if (!is_multisite()){echo "single site";}else{echo "multisite";} ?> GAM Settings</h1>
	<form method="post"
		<?php if (!is_multisite()){echo "action=\"options.php\"";}else{echo "";} ?>>
            <?php
        // This prints out all hidden setting fields
        settings_fields('simplegam_option_group');
        // outputs : wp_nonce_field( "$option_group-options" );
        do_settings_sections('simplegam-setting-admin');
        wp_nonce_field('simplegam_nonce', 'simplegam_nonce');
        submit_button();
        ?>
            </form>
</div>
<?php
    }

    /**
     * Register and add settings
     */
    public function page_init()
    {
        register_setting('simplegam_option_group', // Option group
        'simplegam_option', // Option name
        array(
            $this,
            'sanitize'
        )); // Sanitize
        
        add_settings_section('simplegam_setting_section_id', // ID
        'Custom post types', // Title
        array(
            $this,
            'print_section_info'
        ), // Callback
        'simplegam-setting-admin'); // Page
        
        add_settings_field('network_tag', 'Network code', array(
            $this,
            'network_tag_callback'
        ), 'simplegam-setting-admin', 'simplegam_setting_section_id');
        
        add_settings_field('gam_targeting', 'Enable GAM key / value targeting', array(
            $this,
            'gam_enable_targeting'
        ), 'simplegam-setting-admin', 'simplegam_setting_section_id');
        
        if (is_multisite()) {
            foreach (get_sites() as $blog) {
                // foreach ($blog_ids as $blog_id){
                $blog_id = $blog->blog_id;
                
                // Switch to the next blog in the loop.
                // This will start at $id == 1 because of your ORDER BY statement.
                switch_to_blog($blog_id);
                
                // Get the 5 latest posts for the blog and store them in the $globalquery variable.
                // $globalquery = get_posts('numberposts=5&post_type=any');
                $blog_details = get_blog_details($blog_id);
                $blog_name = $blog_details->blogname;
                $setting_id = "gam_blog_" . $blog_id . '_keys_and_values';
                $args = array(
                    'blog_id' => $blog_id,
                    'setting_id' => $setting_id
                );
                
                add_settings_field($setting_id, $blog_name . ' - Json string of keys / values pairs displayed on the blog', array(
                    $this,
                    'gam_blog_keys_and_values_callback'
                ), 'simplegam-setting-admin', 'simplegam_setting_section_id', $blog_id);
                // Switch back to the main blog
                restore_current_blog();
            }
        } else {
            
            add_settings_field('gam_keys', 'Coma separated list of keys/values pairs defined in GAM', array(
                $this,
                'gam_keys_callback'
            ), 'simplegam-setting-admin', 'simplegam_setting_section_id');
        }
    }

    /**
     * Sanitize each setting field as needed
     *
     * @param array $input
     *            Contains all settings fields as array keys
     */
    public function sanitize($input)
    {
        $new_input = array();
        if (isset($input['id_number']))
            $new_input['id_number'] = absint($input['id_number']);
        
        if (isset($input['network_tag']))
            $new_input['network_tag'] = wp_kses_post($input['network_tag']);
        
        if (isset($input['key_value']))
            $new_input['key_value'] = $input['key_value'];
        
        if (isset($input['gam_targeting']))
            $new_input['gam_targeting'] = $input['gam_targeting'];
        
        if (isset($input['gam_keys']))
            $new_input['gam_keys'] = wp_kses_post($input['gam_keys']);
        
        if (is_multisite()) {
            foreach (get_sites() as $blog) {
                // foreach ($blog_ids as $blog_id){
                $blog_id = $blog->blog_id;
                $id = 'keys_and_values_on_blog_' . $blog_id;
                if (isset($input[$id]))
                    $new_input[$id] = wp_kses_post($input[$id]);
            }
        }
        
        if (isset($input['tags_to_keep']))
            $new_input['tags_to_keep'] = wp_kses_post($input['tags_to_keep']);
        
        if (isset($input['tag_class_to_acf']))
            $new_input['tag_class_to_acf'] = wp_kses_post($input['tag_class_to_acf']);
        
        if (isset($input['images_block_tag']))
            $new_input['images_block_tag'] = wp_kses_post($input['images_block_tag']);
        
        if (isset($input['legend_class_tag']))
            $new_input['legend_class_tag'] = wp_kses_post($input['legend_class_tag']);
        
        if (isset($input['figure_call_class_tag']))
            $new_input['figure_call_class_tag'] = wp_kses_post($input['figure_call_class_tag']);
        if (isset($input['featured_img_class_tag']))
            $new_input['featured_img_class_tag'] = wp_kses_post($input['featured_img_class_tag']);
        
        return $new_input;
    }

    /**
     * Print the Section text
     */
    public function print_section_info()
    {
        // print __('Input .zip file should be an HTML export from InDesign software, containing both .html file and a folder with all images.');
    }

    /**
     * Get the settings option array and print one of its values
     */
    public function id_number_callback()
    {
        printf('<input type="text" id="id_number" name="simplegam_option[id_number]" value="%s" />', isset($this->options['id_number']) ? esc_attr($this->options['id_number']) : '');
    }

    public function network_tag_callback()
    {
        $defaultTag = 'h1';
        printf('<input class="widefat" type="text" id="network_tag" name="simplegam_option[network_tag]" value="%s" />', isset($this->options['network_tag']) ? esc_attr($this->options['network_tag']) : $defaultTag);
    }

    public function gam_enable_targeting()
    {
        $checked = checked($this->options['gam_targeting'], 1, false);
        printf('<input type="checkbox" name="simplegam_option[gam_targeting]" value="1" %s /> <label for="gam_targeting">Enable GAM Targeting</label>', $checked);
    }

    /**
     * Get the settings option array and print one of its values
     */
    public function html_tags_callback()
    {
        $defaultTagsToKeep = '<h2><h3><h4><h5><ul><ol><li>';
        printf('<input class="widefat" type="text" id="tags_to_keep" name="simplegam_option[tags_to_keep]" value="%s" />', isset($this->options['tags_to_keep']) ? esc_attr($this->options['tags_to_keep']) : $defaultTagsToKeep);
    }

    public function html_tags_to_acf_callback()
    {
        $defaultTagsToKeep = '';
        printf('<input class="widefat" type="text" id="tag_class_to_acf" name="simplegam_option[tag_class_to_acf]" value="%s" />', isset($this->options['tag_class_to_acf']) ? esc_attr($this->options['tag_class_to_acf']) : $defaultTagsToKeep);
    }

    public function images_block_tag_callback()
    {
        $defaultTagsToKeep = 'blocimage';
        printf('<input class="widefat" type="text" id="images_block_tag" name="simplegam_option[images_block_tag]" value="%s" />', isset($this->options['images_block_tag']) ? esc_attr($this->options['images_block_tag']) : $defaultTagsToKeep);
    }

    public function legend_tag_callback()
    {
        $defaultTagsToKeep = 'legende';
        printf('<input class="widefat" type="text" id="legend_class_tag" name="simplegam_option[legend_class_tag]" value="%s" />', isset($this->options['legend_class_tag']) ? esc_attr($this->options['legend_class_tag']) : $defaultTagsToKeep);
    }

    public function figure_call_tag_callback()
    {
        $defaultTagsToKeep = 'appelfig';
        printf('<input class="widefat" type="text" id="figure_call_class_tag" name="simplegam_option[figure_call_class_tag]" value="%s" />', isset($this->options['figure_call_class_tag']) ? esc_attr($this->options['figure_call_class_tag']) : $defaultTagsToKeep);
    }

    public function featured_img_class_tag_callback()
    {
        $defaultTagsToKeep = 'imageune';
        printf('<input class="widefat" type="text" id="featured_img_class_tag" name="simplegam_option[featured_img_class_tag]" value="%s" />', isset($this->options['featured_img_class_tag']) ? esc_attr($this->options['featured_img_class_tag']) : $defaultTagsToKeep);
    }

    public function key_values_callback()
    {
        $defaultTagsToKeep = '';
        printf('<input class="widefat" type="text" id="key_value" name="simplegam_option[key_value]" value="%s" />', isset($this->options['key_value']) ? esc_attr($this->options['key_value']) : $defaultTagsToKeep);
    }

    public function gam_blog_keys_and_values_callback($blog_id)
    {
        $defaultValue = '';
        $id = 'keys_and_values_on_blog_' . $blog_id;
        $value = isset($this->options[$id]) ? esc_attr($this->options[$id]) : $defaultValue;
        // $setting =
        printf('<input class="widefat" type="text" id="' . $id . '" name="simplegam_option[' . $id . ']" value="%s" />', $value);
    }

    // CHECKBOX - Name: plugin_options[gam_keys]
    function gam_keys_callback()
    {
        $defaultTagsToReplace = '';
        $value = isset($this->options['gam_keys']) ? esc_attr($this->options['gam_keys']) : $defaultTagsToReplace;
        printf('<input class="widefat" id="gam_keys" name="simplegam_option[gam_keys]" type="text" value="%s"/>', $value);
        $valueDecoded = html_entity_decode($value);
        InDesignHTML2Post_log($valueDecoded);
        if (! empty($value)) {
            
            printf('decoded json: %s', implode("<br/>", json_decode($valueDecoded, true)));
        }
    }

    function simplegam_cpt_setting()
    {
        $post_types = get_post_types($args, $output);
        
        InDesignHTML2Post_log($this->options);
        
        $selectItem = '<select name="simplegam_option[cpt_range][]" multiple="multiple" class="widefat" size="5" style="margin-bottom:10px">';
        foreach ($post_types as $post_type) {
            $selectedState = '';
            $id = $post_type->name;
            $optionLabel = $post_type->label;
            if (isset($this->options['cpt_range']) && is_array($this->options['cpt_range'])) {
                $needSelection = in_array($id, $this->options['cpt_range']);
                $selectedState = $needSelection ? 'selected="selected"' : '';
            }
            
            $new_option = sprintf('<option value="%s" %s style="margin-bottom:3px;">%s</option>', $id, $selectedState, $optionLabel);
            $selectItem .= $new_option;
        }
        
        $selectItem .= "</select>";
        
        InDesignHTML2Post_log($selectItem);
        echo $selectItem;
    }
}