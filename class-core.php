<?php

namespace KymaProject\WordPressConnector;

class Core
{
    const KYMA_USER_NAME= 'kyma';
    const KYMA_USER_EMAIL='admin@kyma.cx';

    public static $scriptUrl;
    public static $styleUrl;

    private $connector;
    private $event_settings;

    public function __construct($basefile)
    {
        $pluginUrl = plugin_dir_url($basefile);
        self::$scriptUrl = $pluginUrl . 'js/';
        self::$styleUrl = $pluginUrl . 'css/';
    }

    public static function onActivation()
    {
        // TODO: Validate if Basic Auth is enabled
        // TODO: Add Error handling during activation

        add_option('kymaconnector_application_id', '');
        add_option('kymaconnector_name', 'Wordpress');

        // TODO: Update due to registration data
        add_option('kymaconnector_event_url', '');
        add_option('kymaconnector_metadata_url', '');
        
        

        $user_name = self::KYMA_USER_NAME;
        $user_email = self::KYMA_USER_EMAIL;

        if ( !username_exists( $user_name ) and email_exists($user_email) == false ) {
            $random_password = wp_generate_password( $length=12, $include_standard_special_chars=false );
            if ( !is_wp_error(wp_create_user( $user_name, $random_password, $user_email ))){
                update_option('kymaconnector_user', $user_name);
                update_option('kymaconnector_password', $random_password);
            }
        } else {
            
        }

        EventSettings::install('kymaconnector');
    }

    public function onInit()
    {
        $this->connector = new Connector();
        $this->event_settings = new EventSettings('kymaconnector', 'kymaconnector-settings');
        add_action('activated_plugin', array($this, 'updateRegistration'));
        add_action('deactivated_plugin', array($this, 'updateRegistration'));

        $settings = new Settings('kymaconnector-settings', 'kymaconnector', $this->connector, $this->event_settings);
        add_action('admin_menu', array($settings, 'addSettingsPage'));
        add_action('admin_init', array($settings, 'registerSettings'));
        add_action('admin_enqueue_scripts', array($settings, 'enqueueScripts'));
        
        add_action('wp_ajax_connect_to_kyma', array($this, 'onAjaxKymaConnect'));
        add_action('wp_ajax_disconnect_from_kyma', array($this, 'onAjaxKymaDisconnect'));
    }

    public function updateRegistration()
    {
        return Connector::register_application($this->event_settings->get_event_spec());
    }

    public function onAjaxKymaConnect()
    {
        // TODO check access rights of the user
        
        check_ajax_referer( 'kymaconnection' );
        $url = filter_input(INPUT_POST, 'url', FILTER_VALIDATE_URL);

        $result = $this->connector->connect($url);
        if (is_wp_error($result)) {
            wp_send_json_error($result);
            return;
        }

        $registrationResult = $this->updateRegistration();
        if (is_wp_error($registrationResult)) {
            wp_send_json_error($registrationResult);
            return;
        }

        wp_send_json(array('connected' => true));
    }

    public function onAjaxKymaDisconnect()
    {
        // TODO check access rights of the user
        
        check_ajax_referer( 'kymadisconnection' );

        $result = $this->connector->disconnect();
        if (is_wp_error($result)) {
            wp_send_json_error($result);
            return;
        }

        wp_send_json(array('connected' => false));
    }
}
