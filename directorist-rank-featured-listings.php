<?php

/**
 * Plugin Name: Directorist - Custom Rank Featured Listings
 * Plugin URI: http://directorist.com/
 * Description: This is an extension that allows administrator to sort/rank featured listings.
 * Version: 1.3.3
 * Author: wpWax
 * Author URI: http://directorist.com/
 * License: GPLv2 or later
 * Text Domain: directorist-rank-featured-listings
 * Domain Path: /languages
 */
// prevent direct access to the file
defined('ABSPATH') || die('No direct script access allowed!');
if (!class_exists('Directorist_RFL')) {
    final class Directorist_RFL
    {

        /** Singleton *************************************************************/

        /**
         * @var Directorist_RFL The one true Directorist_RFL
         * @since 1.0
         */
        private static $instance;

        /**
         * Main Directorist_RFL Instance.
         *
         * Insures that only one instance of Directorist_RFL exists in memory at any one
         * time. Also prevents needing to define globals all over the place.
         *
         * @return object|Directorist_RFL The one true Directorist_RFL
         * @uses Directorist_RFL::setup_constants() Setup the constants needed.
         * @uses Directorist_RFL::includes() Include the required files.
         * @uses Directorist_RFL::load_textdomain() load the language files.
         * @see  Directorist_RFL()
         * @since 1.0
         * @static
         * @static_var array $instance
         */
        public static function instance()
        {
            if (!isset(self::$instance) && !(self::$instance instanceof Directorist_RFL)) {
                self::$instance = new Directorist_RFL;
                self::$instance->setup_constants();
                self::$instance->includes();
                add_action('plugins_loaded', array(self::$instance, 'load_textdomain'));
                add_action('admin_enqueue_scripts', array(self::$instance, 'admin_enqueue_assects'));
                add_filter('atbdp_license_settings_controls', array(self::$instance, 'featured_labels_license_settings_controls'));

                add_action('atbdp_plan_assigned', array(self::$instance, 'process_the_selected_rank_plans'));
                /**
                 * @todo bulk update task passes to directorist schedule hook only instate of init.
                 */
                // add_action('init', array(self::$instance, 'process_bulk_rank_plans'));
                add_action('atbdp_schedule_check', array(self::$instance, 'process_bulk_rank_plans'));

                add_filter('atbdp_all_listings_query_arguments', array(self::$instance, 'featured_labels_custom_query'));
                add_filter('atbdp_listing_search_query_argument', array(self::$instance, 'featured_labels_custom_query'));
                add_filter('atbdp_single_location_query_arguments', array(self::$instance, 'featured_labels_custom_query'));
                add_filter('atbdp_single_tag_query_arguments', array(self::$instance, 'featured_labels_custom_query'));
                add_filter('atbdp_single_category_query_arguments', array(self::$instance, 'featured_labels_custom_query'));

                // license and auto update handler
                add_action('wp_ajax_atbdp_featured_labels_license_activation', array(self::$instance, 'atbdp_featured_labels_license_activation'));
                // license deactivation
                add_action('wp_ajax_atbdp_featured_labels_license_deactivation', array(self::$instance, 'atbdp_featured_labels_license_deactivation'));
                // settings
                add_filter( 'atbdp_listing_type_settings_field_list', array( self::$instance, 'atbdp_listing_type_settings_field_list' ) );
                add_filter( 'atbdp_extension_settings_submenu', array( self::$instance, 'atbdp_extension_settings_submenus' ) );

                add_filter( 'directorist_featured_badge_field_data', array( self::$instance, 'directorist_featured_badge_field_data' ) );
                add_action('wp_head', array(self::$instance, 'dynamic_style'));

            }

            return self::$instance;
        }

        public function dynamic_style(){
            $top_color = get_directorist_option('top_plan_badge_color', '#EAC448');
            $second_color = get_directorist_option('second_top_plan_badge_color', '#8C92AC');
            $third_color = get_directorist_option('third_top_plan_badge_color', '#F76C6F');
			$fourth_color = get_directorist_option('fourth_top_plan_badge_color', '#6e97f7');
            ?>
            <style>
                .directorist-badge-fourth_top_ranked, .atbd_badge_third_top_ranked{
                    color: <?php echo $fourth_color; ?> !important;
                }
                .directorist-badge-third_top_ranked, .atbd_badge_third_top_ranked{
                    color: <?php echo $third_color; ?> !important;
                }
                .directorist-badge-second_top_ranked, .atbd_badge_second_top_ranked{
                    color: <?php echo $second_color; ?> !important;
                }
                .directorist-badge-top_ranked, .atbd_badge_top_ranked{
                    color: <?php echo $top_color; ?> !important;
                }
            </style>
            <?php
        }

        public function directorist_featured_badge_field_data( $field ){
            $top_badge = get_directorist_option('top_plan_badge', __('Premium', 'directorist-rank-featured-listings'));
            $second_badge = get_directorist_option('second_top_plan_badge', __('Pro', 'directorist-rank-featured-listings'));
            $third_badge = get_directorist_option('third_top_plan_badge', __('Basic', 'directorist-rank-featured-listings'));
			$third_badge = get_directorist_option('fourth_top_plan_badge', __('Free', 'directorist-rank-featured-listings'));
            $plan_id = get_post_meta(get_the_id(), '_fm_plans', true);

            $rank_meta = get_post_meta( get_the_ID(), '_atbdp_feature_rank', true );

            switch ( $plan_id ) {
                case get_directorist_option('top_plan'):
                    $field['label'] = $top_badge;
                    $field['class'] = 'top_ranked';
                    break;
                case get_directorist_option('second_top_plan'):
                    $field['label'] = $second_badge;
                    $field['class'] = 'second_top_ranked';
                    break;
                case get_directorist_option('third_top_plan'):
                    $field['label'] = $third_badge;
                    $field['class'] = 'third_top_ranked';
                    break;
                case get_directorist_option('fourth_top_plan'):
                    $field['label'] = $third_badge;
                    $field['class'] = 'fourth_top_ranked';
                    break;
            }

            return $field;
        }

        public function atbdp_listing_type_settings_field_list( $rank_featured_fields ) {
            $rank_featured_fields['top_plan'] = [
                'label'     => __('Select Plan', 'directorist-rank-featured-listings'),
                'type'      => 'select',
                'show-default-option' => true,
                'options'   => $this->get_plans(),
            ];
            $rank_featured_fields['top_plan_badge'] = [
                'type'              => 'text',
                'label'             => __('Badge Text', 'directorist-rank-featured-listings'),
                'value'             => __('Premium', 'directorist-rank-featured-listings'),
            ];
            $rank_featured_fields['top_plan_badge_color'] = [
                'label' => __('Badge Color', 'directorist-rank-featured-listings'),
                'type' => 'color',
                'value' => '#EAC448',
            ];
            $rank_featured_fields['second_top_plan'] = [
                'label'     => __('Select Plan', 'directorist-rank-featured-listings'),
                'type'      => 'select',
                'show-default-option' => true,
                'options'   => $this->get_plans(),
            ];
            $rank_featured_fields['second_top_plan_badge'] = [
                'type'              => 'text',
                'label'             => __('Badge Text', 'directorist-rank-featured-listings'),
                'value'             => __('Pro', 'directorist-rank-featured-listings'),
            ];
            $rank_featured_fields['second_top_plan_badge_color'] = [
                'label' => __('Badge Color', 'directorist-rank-featured-listings'),
                'type' => 'color',
                'value' => '#8C92AC',
            ];
            $rank_featured_fields['third_top_plan'] = [
                'label'     => __('Select Plan', 'directorist-rank-featured-listings'),
                'type'      => 'select',
                'show-default-option' => true,
                'options'   => $this->get_plans(),
            ];
            $rank_featured_fields['third_top_plan_badge'] = [
                'type'              => 'text',
                'label'             => __('Badge Text', 'directorist-rank-featured-listings'),
                'value'             => __('Basic', 'directorist-rank-featured-listings'),
            ];
            $rank_featured_fields['third_top_plan_badge_color'] = [
                'label' => __('Badge Color', 'directorist-rank-featured-listings'),
                'type' => 'color',
                'value' => '#F76C6F',
            ];
            $rank_featured_fields['fourth_top_plan'] = [
                'label'     => __('Select Plan', 'directorist-rank-featured-listings'),
                'type'      => 'select',
                'show-default-option' => true,
                'options'   => $this->get_plans(),
            ];
            $rank_featured_fields['fourth_top_plan_badge'] = [
                'type'              => 'text',
                'label'             => __('Badge Text', 'directorist-rank-featured-listings'),
                'value'             => __('Free', 'directorist-rank-featured-listings'),
            ];
            $rank_featured_fields['fourth_top_plan_badge_color'] = [
                'label' => __('Badge Color', 'directorist-rank-featured-listings'),
                'type' => 'color',
                'value' => '#6e97f7',
            ];


            return $rank_featured_fields;
        }

        public function atbdp_extension_settings_submenus( $submenu ) {
            $submenu['rank_featured'] = [
                'label' => __('Rank Featured Listings', 'directorist-rank-featured-listings'),
                'icon'       => '<i class="fas fa-level-up-alt"></i>',
                'sections'   => apply_filters( 'atbdp_ranke_featured_settings_controls', [
                'top' => [
                    'title'       => __('Rank One', 'directorist-rank-featured-listings'),
                    'fields'      =>  [
                        'top_plan', 'top_plan_badge', 'top_plan_badge_color'
                     ],
                ],
                'second_top' => [
                    'title'       => __('Rank Two', 'directorist-rank-featured-listings'),
                    'fields'      =>  [
                        'second_top_plan', 'second_top_plan_badge', 'second_top_plan_badge_color'
                     ],
                ],
                'third_top' => [
                    'title'       => __('Rank Three', 'directorist-rank-featured-listings'),
                    'fields'      =>  [
                        'third_top_plan', 'third_top_plan_badge', 'third_top_plan_badge_color'
                     ],
                ],
                'fourth_top' => [
                    'title'       => __('Rank Four', 'directorist-rank-featured-listings'),
                    'fields'      =>  [
                        'fourth_top_plan', 'fourth_top_plan_badge', 'fourth_top_plan_badge_color'
                     ],
                ],
                ] ),
            ];

            return $submenu;
        }

        public function process_bulk_rank_plans()
        {
            $listings_ids = ATBDP_Listings_Data_Store::get_listings_ids();
            foreach ($listings_ids as $listing_id) {
                $this->update_bulk_ranks($listing_id);
            }

        }

        public function process_the_selected_rank_plans($listing_id)
        {
            $this->update_bulk_ranks($listing_id);
        }

        private function update_bulk_ranks($listing_id)
        {
            $level_one   = get_directorist_option('top_plan');
            $level_two   = get_directorist_option('second_top_plan');
            $level_three = get_directorist_option('third_top_plan');
			$level_four = get_directorist_option('fourth_top_plan');
            $plan_id     = get_post_meta($listing_id, '_fm_plans', true);
            $featured    = get_post_meta($listing_id, '_featured', true);

            if ( $featured ) {
                if ($level_one == $plan_id) {
                    update_post_meta($listing_id, '_atbdp_feature_rank', 4);
                }
                if ($level_two == $plan_id) {
                    update_post_meta($listing_id, '_atbdp_feature_rank', 3);
                }
                if ($level_three == $plan_id) {
                    update_post_meta($listing_id, '_atbdp_feature_rank', 2);
                }
                if ($level_four == $plan_id) {
                    update_post_meta($listing_id, '_atbdp_feature_rank', 1);
                }
            } else {
                update_post_meta($listing_id, '_atbdp_feature_rank', 0);
            }
        }

        public function atbdp_featured_labels_license_deactivation()
        {
            $license = !empty($_POST['featured_labels_license']) ? trim($_POST['featured_labels_license']) : '';
            $options = get_option('atbdp_option');
            $options['featured_labels_license'] = $license;
            update_option('atbdp_option', $options);
            update_option('directorist_featured_labels_license', $license);
            $data = array();
            if (!empty($license)) {
                // data to send in our API request
                $api_params = array(
                    'edd_action' => 'deactivate_license',
                    'license' => $license,
                    'item_id' => ATBDP_FL_POST_ID, // The ID of the item in EDD
                    'url' => home_url(),
                );
                // Call the custom API.
                $response = wp_remote_post(ATBDP_AUTHOR_URL, array('timeout' => 15, 'sslverify' => false, 'body' => $api_params));
                // make sure the response came back okay
                if (is_wp_error($response) || 200 !== wp_remote_retrieve_response_code($response)) {

                    $data['msg'] = (is_wp_error($response) && !empty($response->get_error_message())) ? $response->get_error_message() : __('An error occurred, please try again.', 'directorist-rank-featured-listings');
                    $data['status'] = false;
                } else {

                    $license_data = json_decode(wp_remote_retrieve_body($response));
                    if (!$license_data) {
                        $data['status'] = false;
                        $data['msg'] = __('Response not found!', 'directorist-rank-featured-listings');
                        wp_send_json($data);
                        die();
                    }
                    update_option('directorist_featured_labels_license_status', $license_data->license);
                    if (false === $license_data->success) {
                        switch ($license_data->error) {
                            case 'expired':
                                $data['msg'] = sprintf(
                                    __('Your license key expired on %s.', 'directorist-rank-featured-listings'),
                                    date_i18n(get_option('date_format'), strtotime($license_data->expires, current_time('timestamp')))
                                );
                                $data['status'] = false;
                                break;

                            case 'revoked':
                                $data['status'] = false;
                                $data['msg'] = __('Your license key has been disabled.', 'directorist-rank-featured-listings');
                                break;

                            case 'missing':

                                $data['msg'] = __('Invalid license.', 'directorist-rank-featured-listings');
                                $data['status'] = false;
                                break;

                            case 'invalid':
                            case 'site_inactive':

                                $data['msg'] = __('Your license is not active for this URL.', 'directorist-rank-featured-listings');
                                $data['status'] = false;
                                break;

                            case 'item_name_mismatch':

                                $data['msg'] = sprintf(__('This appears to be an invalid license key for %s.', 'directorist-rank-featured-listings'), 'Directorist - Listings with Map');
                                $data['status'] = false;
                                break;

                            case 'no_activations_left':

                                $data['msg'] = __('Your license key has reached its activation limit.', 'directorist-rank-featured-listings');
                                $data['status'] = false;
                                break;

                            default:
                                $data['msg'] = __('An error occurred, please try again.', 'directorist-rank-featured-listings');
                                $data['status'] = false;
                                break;
                        }
                    } else {
                        $data['status'] = true;
                        $data['msg'] = __('License deactivated successfully!', 'directorist-rank-featured-listings');
                    }
                }
            } else {
                $data['status'] = false;
                $data['msg'] = __('License not found!', 'directorist-rank-featured-listings');
            }
            wp_send_json($data);
            die();
        }

        public function atbdp_featured_labels_license_activation()
        {
            $license = !empty($_POST['featured_labels_license']) ? trim($_POST['featured_labels_license']) : '';
            $options = get_option('atbdp_option');
            $options['featured_labels_license'] = $license;
            update_option('atbdp_option', $options);
            update_option('directorist_featured_labels_license', $license);
            $data = array();
            if (!empty($license)) {
                // data to send in our API request
                $api_params = array(
                    'edd_action' => 'activate_license',
                    'license' => $license,
                    'item_id' => ATBDP_FL_POST_ID, // The ID of the item in EDD
                    'url' => home_url(),
                );
                // Call the custom API.
                $response = wp_remote_post(ATBDP_AUTHOR_URL, array('timeout' => 15, 'sslverify' => false, 'body' => $api_params));
                // make sure the response came back okay
                if (is_wp_error($response) || 200 !== wp_remote_retrieve_response_code($response)) {

                    $data['msg'] = (is_wp_error($response) && !empty($response->get_error_message())) ? $response->get_error_message() : __('An error occurred, please try again.', 'directorist-rank-featured-listings');
                    $data['status'] = false;
                } else {

                    $license_data = json_decode(wp_remote_retrieve_body($response));
                    if (!$license_data) {
                        $data['status'] = false;
                        $data['msg'] = __('Response not found!', 'directorist-rank-featured-listings');
                        wp_send_json($data);
                        die();
                    }
                    update_option('directorist_featured_labels_license_status', $license_data->license);
                    if (false === $license_data->success) {
                        switch ($license_data->error) {
                            case 'expired':
                                $data['msg'] = sprintf(
                                    __('Your license key expired on %s.', 'directorist-rank-featured-listings'),
                                    date_i18n(get_option('date_format'), strtotime($license_data->expires, current_time('timestamp')))
                                );
                                $data['status'] = false;
                                break;

                            case 'revoked':
                                $data['status'] = false;
                                $data['msg'] = __('Your license key has been disabled.', 'directorist-rank-featured-listings');
                                break;

                            case 'missing':

                                $data['msg'] = __('Invalid license.', 'directorist-rank-featured-listings');
                                $data['status'] = false;
                                break;

                            case 'invalid':
                            case 'site_inactive':

                                $data['msg'] = __('Your license is not active for this URL.', 'directorist-rank-featured-listings');
                                $data['status'] = false;
                                break;

                            case 'item_name_mismatch':

                                $data['msg'] = sprintf(__('This appears to be an invalid license key for %s.', 'directorist-rank-featured-listings'), 'Directorist - Listings with Map');
                                $data['status'] = false;
                                break;

                            case 'no_activations_left':

                                $data['msg'] = __('Your license key has reached its activation limit.', 'directorist-rank-featured-listings');
                                $data['status'] = false;
                                break;

                            default:
                                $data['msg'] = __('An error occurred, please try again.', 'directorist-rank-featured-listings');
                                $data['status'] = false;
                                break;
                        }
                    } else {
                        $data['status'] = true;
                        $data['msg'] = __('License activated successfully!', 'directorist-rank-featured-listings');
                    }
                }
            } else {
                $data['status'] = false;
                $data['msg'] = __('License not found!', 'directorist-rank-featured-listings');
            }
            wp_send_json($data);
            die();
        }

        /**
         * @package Directorist
         * @since 1.0
         * @return void $args   array of the wp query
         */
        public function featured_labels_custom_query($args)
        {
            $level_one   = get_directorist_option('top_plan');
            $level_two   = get_directorist_option('second_top_plan');
            $level_three = get_directorist_option('third_top_plan');
			$level_four = get_directorist_option('fourth_top_plan');

            if ( $level_one || $level_two || $level_three ) {
                $args['meta_query']['rank_featured_clause'] = [
                    'key'     => '_atbdp_feature_rank',
                    'type'    => 'NUMERIC',
                    'compare' => 'EXISTS',
                ];

                $is_rand = ( isset( $args['orderby'] ) && ( in_array( $args['orderby'], [ 'rand', 'meta_value_num rand' ] ) ) ) ? true : false;
                if ( $is_rand ) { return $args; }

                $old_order_by = ( isset( $args['orderby'] ) && is_array( $args['orderby'] ) ) ? $args['orderby'] : [];
                $rank_featured_clause_order = 'DESC';

                if ( isset( $old_order_by['_featured'] ) ) {
                    unset( $old_order_by['_featured'] );
                }

                // if ( isset( $old_order_by['views'] ) && $old_order_by['views'] == 'DESC' ) {
                //     $rank_featured_clause_order = 'DESC';
                // }

                if ( isset( $old_order_by['date'] ) && $old_order_by['date'] == 'DESC' ) {
                    $rank_featured_clause_order = 'ASCS';
                }

                $order_by['rank_featured_clause'] = $rank_featured_clause_order;
                $order_by = array_merge( $order_by, $old_order_by );
                $args['orderby'] = $order_by;

                return $args;
            } else {
                return $args;
            }

        }

        /**
         * @since 1.0
         */
        public function featured_labels_license_settings_controls($default)
        {
            $status = get_option('directorist_featured_labels_license_status');
            if (!empty($status) && (false !== $status && 'valid' == $status)) {
                $action = array(
                    'type' => 'toggle',
                    'name' => 'featured_labels_deactivated',
                    'label' => __('Action', 'directorist-rank-featured-listings'),
                    'validation' => 'numeric',
                );
            } else {
                $action = array(
                    'type' => 'toggle',
                    'name' => 'featured_labels_activated',
                    'label' => __('Action', 'directorist-rank-featured-listings'),
                    'validation' => 'numeric',
                );
            }
            $new = apply_filters('atbdp_featured_labels_license_controls', array(
                'type' => 'section',
                'title' => __('Rank Featured Listings', 'directorist-business-hours'),
                'description' => __('You can active your Rank Featured Listings extension here.', 'directorist-business-hours'),
                'fields' => apply_filters('atbdp_featured_labels_license_settings_field', array(
                    array(
                        'type' => 'textbox',
                        'name' => 'featured_labels_license',
                        'label' => __('License', 'directorist-rank-featured-listings'),
                        'description' => __('Enter your Rank Featured Listings extension license', 'directorist-rank-featured-listings'),
                        'default' => '',
                    ),
                    $action,
                )),
            ));
            $settings = apply_filters('atbdp_licence_menu_for_rank_featured', true);
            if ($settings) {
                array_push($default, $new);
            }

            return $default;
        }

        private function get_plans()
        {
            $args = array();
            if (class_exists('ATBDP_Pricing_Plans')) {
                $args = array(
                    'post_type' => 'atbdp_pricing_plans',
                    'posts_per_page' => -1,
                    'post_status' => 'publish',
                );
            }
            if (class_exists('DWPP_Pricing_Plans')) {
                $args = array(
                    'post_type' => 'product',
                    'posts_per_page' => -1,
                    'post_status' => 'publish',
                );
            }

            $atbdp_query = new WP_Query($args);
            $plans = $atbdp_query->posts;
            $all_plans = array();
            if ($plans) {
                foreach ($plans as $plan) {
                    $all_plans[] = array('value' => $plan->ID, 'label' => $plan->post_title);
                }
            }

            return $all_plans;
        }

        /**
         * Enqueue all frontend scripts & styles
         *
         * @return void
         */
        public function admin_enqueue_assects()
        {
            // Enqueue frontend style
            wp_enqueue_style('featured_listing_css', DFL_ADMIN_CSS . 'main.css', time());
            // Enqueue frontend script
            wp_enqueue_script('featured_listing_js', DFL_ADMIN_JS . 'main.js', true);
            // Make a js variable
            wp_localize_script(
                // Which file we wanna send the value or variable thats file handle
                'featured_listing_js', // File handle
                // This object name is similar to, Which file we wanna send the value
                'fl_js_object', // Object name
                array(
                    'ajaxurl' => admin_url('admin-ajax.php'), // It's a variable
                )
            );
        }

        /**
         * It Includes and requires necessary files.
         *
         * @access private
         * @return void
         * @since 1.0
         */
        private function setup_constants()
        {
            $plugin_version = '1.3.2';
            if( preg_match('/\*[\s\t]+?version:[\s\t]+?([0-9.]+)/i',file_get_contents( __FILE__ ), $v) ){
                $plugin_version = $v[1];
            }
            define( 'DFL_PLUGIN_VERSION', $plugin_version );
            define('DFL_PLUGIN_DIRNAME', dirname(plugin_basename(__FILE__)));
            define('DFL_PLUGIN_URL', plugin_dir_url(__FILE__));
            define('DFL_BASE_FILE', __FILE__);
            define('DFL_ADMIN_CSS', DFL_PLUGIN_URL . 'assets/admin/css/');
            define('DFL_ADMIN_JS', DFL_PLUGIN_URL . 'assets/admin/js/');

            // plugin author url
            if (!defined('ATBDP_AUTHOR_URL')) {
                define('ATBDP_AUTHOR_URL', 'https://directorist.com');
            }
            // post id from download post type (edd)
            if (!defined('ATBDP_FL_POST_ID')) {
                define('ATBDP_FL_POST_ID', 22525);
            }
        }

        /**
         * It Includes and requires necessary files.
         *
         * @access private
         * @return void
         * @since 1.0
         */
        private function includes()
        {
            // setup the updater
            if (!class_exists('EDD_SL_Plugin_Updater')) {
                // load our custom updater if it doesn't already exist
                include dirname(__FILE__) . '/includes/EDD_SL_Plugin_Updater.php';
            }

            $license_key = trim(get_option('directorist_featured_labels_license'));
            new EDD_SL_Plugin_Updater(ATBDP_AUTHOR_URL, __FILE__, array(
                'version' => DFL_PLUGIN_VERSION, // current version number
                'license' => $license_key, // license key (used get_option above to retrieve from DB)
                'item_id' => ATBDP_FL_POST_ID, // id of this plugin
                'author' => 'AazzTech', // author of this plugin
                'url' => home_url(),
                'beta' => false, // set to true if you wish customers to receive update notifications of beta releases
            ));
        }

        /**
         * It register the text domain to the WordPress
         */
        public function load_textdomain()
        {
            load_plugin_textdomain('directorist-rank-featured-listings', false, dirname(plugin_basename(__FILE__)) . '/languages');
        }

        private function __construct()
        {
            /*making it private prevents constructing the object*/
        }

        public function __clone()
        {
            // Cloning instances of the class is forbidden.
            _doing_it_wrong(__FUNCTION__, __('Cheatin&#8217; huh?'), '1.0');
        }

        public function __wakeup()
        {
            // Unserializing instances of the class is forbidden.
            _doing_it_wrong(__FUNCTION__, __('Cheatin&#8217; huh?'), '1.0');
        }
    }

    if ( ! function_exists( 'directorist_is_plugin_active' ) ) {
        function directorist_is_plugin_active( $plugin ) {
            return in_array( $plugin, (array) get_option( 'active_plugins', array() ), true ) || directorist_is_plugin_active_for_network( $plugin );
        }
    }
    
    if ( ! function_exists( 'directorist_is_plugin_active_for_network' ) ) {
        function directorist_is_plugin_active_for_network( $plugin ) {
            if ( ! is_multisite() ) {
                return false;
            }
                    
            $plugins = get_site_option( 'active_sitewide_plugins' );
            if ( isset( $plugins[ $plugin ] ) ) {
                    return true;
            }
    
            return false;
        }
    }

    /**
     * The main function for that returns Directorist_RFL
     *
     * The main function responsible for returning the one true Directorist_RFL
     * Instance to functions everywhere.
     *
     * Use this function like you would a global variable, except without needing
     * to declare the global.
     *
     *
     * @return object|Directorist_RFL The one true Directorist_RFL Instance.
     * @since 1.0
     */
    function Directorist_RFL()
    {
        return Directorist_RFL::instance();
    }

    if ( directorist_is_plugin_active( 'directorist/directorist-base.php' ) ) {
        Directorist_RFL(); // get the plugin running
    }
}
