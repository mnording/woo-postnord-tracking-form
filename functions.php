<?php
/**
 * postnord Tracking
 *
 * @package     Woo Postnord Tracking Form
 * @author      Mattias Nording
 * @copyright   2018 Mnording
 * @license     MIT
 *
 * @wordpress-plugin
 * Plugin Name: Woo Postnord Tracking Form
 * Plugin URI:  https://github.com/mnording/woocommerce-woo-postnord-tracking-form
 * Description: Enabling fetching tracking info from Postnord.
 * Version:     1.0.1
 * Author:      Mnording
 * Author URI:  https://mnording.com
 * Text Domain: woo-postnord-tracking-form
 * Domain Path: /languages
 * License:     MIT
 * License URI: https://opensource.org/licenses/MIT
 */
require_once 'classes/PostnordWebService.php';
class postnordTracking {
    public function __construct()
    {

       add_shortcode('woo-postnord-tracking-form', array($this,'render_form'));
        add_action( 'wp_ajax_get_postnord_tracking', array($this,'get_postnord_tracking') );
        add_action('wp_enqueue_scripts',array($this,'register_postnord_scripts'));
        add_action('admin_menu', array($this,'postnord_tracking_plugin_create_menu'));
        add_action( 'admin_init', array($this,'postnord_tracking_plugin_settings') );
        add_action( 'plugins_loaded', array($this,'postnord_tracking_plugin_textdomain') );
    }
    function postnord_tracking_plugin_textdomain() {
        load_plugin_textdomain( 'woo-postnord-tracking-form', FALSE, basename( dirname( __FILE__ ) ) . '/languages/' );
    }
    public function postnord_tracking_plugin_settings() {

        register_setting( 'postnord_tracking_settings-group', 'api_key' );
        register_setting( 'postnord_tracking_settings-group', 'should_log' );
    }
    public function postnord_tracking_plugin_create_menu() {
        add_options_page('Woo Postnord Tracking Settings', 'Woo Postnord Tracking', 'administrator','woo-postnord-tracking-form' ,array($this,'postnord_tracking_settings_page') );

    }
    public function postnord_tracking_settings_page() {
        ?>
        <div class="wrap">
            <h1>Postnord Tracking Form</h1>

            <form method="post" action="options.php">
                <?php settings_fields( 'postnord_tracking_settings-group' ); ?>
                <?php do_settings_sections( 'postnord_tracking_settings-group' ); ?>

                <table class="form-table">
                    <tr valign="top">
                        <th scope="row"><?php _e("Api Key","woo-postnord-tracking-form"); ?></th>
                        <td><input type="text" name="api_key" value="<?php echo esc_attr( get_option('api_key') ); ?>" /></td>
                        <td><?php _e("What is your API Key?","woo-postnord-tracking-form"); ?> -> <a href="https://developer.postnord.com/"><?php _e("Click here for Developer Portal","woo-postnord-tracking-form");?></a></td>
                    </tr>
                    <tr valign="top">
                        <th scope="row"><?php _e("Create Debug log?","woo-postnord-tracking-form"); ?></th>
                        <td><input name="should_log" type="checkbox" value="1" <?php checked( '1', get_option( 'should_log' ) ); ?> /><?php _e("Yes","woo-postnord-tracking-form")?></td>
                    </tr>
                </table>

                <?php submit_button(); ?>

            </form>
        </div>
    <?php }
    public function render_form(){
        $html = '<style>';
        $html .= '#postnord-tracking-form-container {border-bottom: 1px dotted black;float: left;width: 100%; padding: 10px;}';
        $html .= '#postnord-tracking-form-container button { float:right;}';
        $html .= '#postnord-tracking-response-container{ float:left;width:100%;position:relative;}';
        $html .= '.loader {border: 16px solid #f3f3f3;border-top: 16px solid #3498db;border-radius: 50%;width: 120px;height: 120px; animation: spin 2s linear infinite; position:absolute;left:45%;}';
        $html .= '@keyframes spin {0% { transform: rotate(0deg); }100% { transform: rotate(360deg); }}';
        $html .= '</style>';
        $html .= "<div id='postnord-tracking-form-container'>";
        $html .= __("Package ID","woo-postnord-tracking-form")." <input type='text' name='trackingid' id='trackingid' placeholder='Kolli ID'>";
        $html .= "<button>".__("Track package","woo-postnord-tracking-form")."</button>";
        $html .= "</div>";
        wp_enqueue_script('woo-postnord-tracking-form');
        $html.="<div id='postnord-tracking-response-container'></div>";
        return $html;
    }
    function register_postnord_scripts()
    {
        wp_register_script( 'woo-postnord-tracking-form', plugins_url('postnord-main.js',__FILE__), array('jquery'), '1.0',true );
    }
    function postnord_tracking_scripts() {
        wp_enqueue_script( 'woo-postnord-tracking-form');
    }

    private function createHtml($html){
        $header = "<div id='postnord-tracking-container'><h2>".__("Your shipment:","woo-postnord-tracking-form")."</h2>";
        $footer = "</div>";
        return $header."".$html."".$footer;
    }

    function get_postnord_tracking() {
        $lang = get_bloginfo( $show = 'language');
        $lang = substr($lang,0,2);
        $this->postnord = new postnordWebservice(get_option('api_key'),$lang);
      //  $this->postnord = new postnordWebservice(get_option('api_key'));
        $trackingId = $_GET['trackingID'];
        $resp = "";
        if($trackingId != ""){

                $resp = $this->postnord->GetByShipmentId($trackingId);

        }
        if($trackingId == ""){
            $orderid = urlencode($_GET["orderID"]);
            $resp = $this->postnord->GetShipmentByReference($orderid);
        }
        $html = "<table>";
        $html .= "<th>".__("Date","woo-postnord-tracking-form")."</th>";
        $html .= "<th>".__("Location","woo-postnord-tracking-form")."</th>";
        $html .= "<th>".__("Event","woo-postnord-tracking-form")."</th>";
        foreach($resp as $data){
            $html .= "<tr>";
            $html .= "<td>";
            $html .=   $data["datetime"];
            $html .= "</td>";
            $html .= "<td>";
            $html .= $data["location"];
            $html .= "</td>";
            $html .= "<td>";
            $html .= $data["descr"];
            $html .= "</td>";
            $html .= "</tr>";
        }
        $html .= "</table>";
        echo $this->createHtml($html);
        wp_die(); // this is required to terminate immediately and return a proper response
    }
}
if ( in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {
    $postnord = new postnordTracking();
}

