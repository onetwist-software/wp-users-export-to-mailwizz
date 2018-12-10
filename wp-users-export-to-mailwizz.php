<?php defined( 'ABSPATH' ) or die( 'No direct script access allowed!' );
/*
Plugin Name: Users export to MailWizz
Plugin URI: http://www.mailwizz.com
Description: Adds an export users entry under Tools menu for your <a href="http://www.mailwizz.com/" target="_blank">MailWizz Email Marketing Application</a> based on the <a href="https://github.com/twisted1919/mailwizz-php-sdk" target="_blank">PHP-SDK</a>. <br />Using the widget, you can export a users list based on your mail list definition.
Version: 1.0
Author: Serban Cristian <cristian.serban@mailwizz.com>
Author URI: http://www.mailwizz.com
License: MIT http://opensource.org/licenses/MIT
*/

define( 'UETM_TEXTDOMAIN', 'uetm' );

if ( ! class_exists( 'MailWizzApi_Autoloader', false ) ) {
    require_once dirname(__FILE__) . '/vendor/autoload.php';
}

/**
 * This class is storing a notice and its CSS class to be applied when displaying
 * Class Uetm_Notice
 */
class Uetm_Notice
{
    /**
     * Message to be shown
     */
    public $message = array();

    /**
     * CSS classes to apply on the notice div
     */
    public $css_classes = array( 'notice' );

    /**
     * Uetm_Notice constructor.
     * @param $message
     * @param $css_classes
     */
    public function __construct( $message, $css_classes ) {

        if ( empty( $message ) ) {
            return;
        }

        if ( ! is_array( $message ) ) {
	        $message = array( $message );
        }
	    $this->message = $message;

        if ( ! empty( $css_classes ) ) {
            if ( ! is_array( $css_classes ) ) {
                $css_classes = array( $css_classes );
            }
            $this->css_classes = array_merge( $this->css_classes, $css_classes );
        }
    }
}

/**
 * Used to store the notices
 * Class Uetm_Notice_Store
 */
class Uetm_Notice_Store
{
    /**
     * @var array
     */
    private static $notices = array();

    /**
     * @param Uetm_Notice $notice
     */
    public static function add( Uetm_Notice $notice )
    {
        static::$notices[] = $notice;
    }

    /**
     * @return array
     */
    public static function get()
    {
        return static::$notices;
    }

    /**
     *
     */
    public static function reset()
    {
        static::$notices = [];
    }

    /**
     * Render the stored notices
     */
    public static function renderNotices()
    {
        foreach ( static::get() as $notice ) {
            ?>
            <div style="margin-left:0px" class="<?php echo esc_attr( implode( ' ', $notice->css_classes ) ); ?>">
                <?php foreach ((array)$notice->message as $message) { 
                    echo sprintf( '<p> %s </p>', esc_html( $message ) );
                } ?>
            </div>
            <?php
        }
    }
}

// Hook for adding admin menus
add_action( 'admin_menu', 'uetm_add_pages' );

// action function for above hook
function uetm_add_pages() {

    // Add a new submenu under Tools:
    add_management_page( __( 'Export users to MailWizz', UETM_TEXTDOMAIN ), __( 'Export users to MailWizz',UETM_TEXTDOMAIN ), 'manage_options', 'users-to-mailwizz', 'uetm_tools_page' );
}

// uetm_tools_page() displays the page content for the Users export to MailWizz
function uetm_tools_page() {

    //must check that the user has the required capability
    if ( ! current_user_can('manage_options' ) ) {
        wp_die( __( 'You do not have sufficient permissions to access this page.', UETM_TEXTDOMAIN ) );
    }

    echo '<h2>' . __( 'Export users to MailWizz', UETM_TEXTDOMAIN ) . '</h2>';

    Uetm_Notice_Store::reset();

    // variables for the field and option names
    $mailwizz_api_opt_name = 'uetm_api_opt';

    // Read in existing option value from database
    $option_value          = get_option( $mailwizz_api_opt_name, array() );
    $mailwizz_api_opt_val  = ! empty( $option_value ) ? (array)$option_value : array();
    
    $api_url               = isset( $mailwizz_api_opt_val['api_url'] )     ? $mailwizz_api_opt_val['api_url']     : '';
    $public_key            = isset( $mailwizz_api_opt_val['public_key'] )  ? $mailwizz_api_opt_val['public_key']  : '';
    $private_key           = isset( $mailwizz_api_opt_val['private_key'] ) ? $mailwizz_api_opt_val['private_key'] : '';
    $fresh_lists           = array();

    if ( ! empty( $api_url ) && ! empty( $public_key ) && ! empty( $private_key ) ) {

        MailWizzApi_Base::setConfig( uetm_build_sdk_config( $api_url, $public_key, $private_key ) );

        $endpoint = new MailWizzApi_Endpoint_Lists();
        $response = $endpoint->getLists(1, 50);
        $response = $response->body->toArray();

        if ( isset( $response['status'] ) && $response['status'] == 'success' && !empty( $response['data']['records'] ) ) {
            foreach ( $response['data']['records'] as $list ) {
                $fresh_lists[] = array(
                    'list_uid'  => $list['general']['list_uid'],
                    'name'      => $list['general']['name']
                );
            }
        }
    }

    // See if the user has posted us some information
    if ( isset( $_POST['save_api'] ) && ! ( isset( $_POST['export'] ) ) ) {

        $opt_val = array();

        // Read their posted value
        $nonce       = isset( $_POST['uetm_form_nonce'] ) ? sanitize_text_field( $_POST['uetm_form_nonce'] ) : '';

        $api_url     = $opt_val['api_url']     = isset( $_POST['api_url'] )      ? sanitize_text_field( $_POST['api_url'] )      : '';
        $public_key  = $opt_val['public_key']  = isset( $_POST['public_key'] )   ? sanitize_text_field( $_POST['public_key'] )   : '';
        $private_key = $opt_val['private_key'] = isset( $_POST['private_key'] )  ? sanitize_text_field( $_POST['private_key' ] ) : '';

        if ( $errors = uetm_validate_attributes( $nonce, $api_url, $public_key, $private_key ) ) {
            Uetm_Notice_Store::add( new Uetm_Notice( $errors, ['error'] ) );
        } else {
            // If no errors save the posted value in the database
            update_option( $mailwizz_api_opt_name, $opt_val );
            Uetm_Notice_Store::add( new Uetm_Notice( __( 'API credentials saved successfully.', UETM_TEXTDOMAIN ), 'notice-success' ) );
        }
        unset( $_POST['save_api'] );
        unset( $_POST['uetm_form_nonce'] );
    }

    if ( isset( $_POST['export'] ) ) {

        $nonce    = isset( $_POST['uetm_form_nonce'] ) ?  sanitize_text_field( $_POST['uetm_form_nonce'] ) : '';
        $list_uid = isset( $_POST['lists'] )           ?  sanitize_text_field( $_POST['lists'] )           : '';
        $roles    = isset( $_POST['roles'] )           ?  array_map( function( $item ) { return sanitize_text_field( $item ); }, $_POST['roles'] ) : '';

        if ( $errors = uetm_validate_attributes( $nonce, $api_url, $public_key, $private_key ) ) {
            Uetm_Notice_Store::add( new Uetm_Notice( $errors, 'notice-error' ) );
        } else {
            // If no error export to mailwizz list
            uetm_export_to_mwz( $api_url, $public_key, $private_key, $list_uid, $roles );
        }

        unset( $_POST['export'] );
        unset( $_POST['uetm_form_nonce'] );
    }

    Uetm_Notice_Store::renderNotices();
?>
    <form name="uetm-form" method="post" action="">
        <input type="hidden" name="uetm_form_nonce" value="<?php echo wp_create_nonce( basename(__FILE__ ) ); ?>">
        <table class="form-table">
            <tbody>
            <tr>
                <th scope="row">
                    <label for="api_url"><?php _e("API URL (required)", UETM_TEXTDOMAIN ); ?></label>
                </th>
                <td>
                    <input type="text" name="api_url" value="<?php echo esc_url( $api_url ); ?>" size="100" required>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="public_key"><?php _e("Public key (required)", UETM_TEXTDOMAIN ); ?></label>
                </th>
                <td>
                    <input type="text" name="public_key" value="<?php echo esc_attr( $public_key ); ?>" size="100" required>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="public_key"><?php _e("Private key (required)", UETM_TEXTDOMAIN ); ?></label>
                </th>
                <td>
                    <input type="text" name="private_key" value="<?php echo esc_attr( $private_key ); ?>" size="100" required>
                </td>
            </tr>
            </tbody>
        </table>

        <p class="submit">
            <input type="submit" name="save_api" id="save_api" class="button-primary" value="<?php esc_attr_e('Save API credentials' ) ?>" />
        </p>

        <?php
            if ( ! empty( $fresh_lists ) ) {
        ?>
         <table class="form-table">
            <tbody>
            <tr>
                <th scope="row"><label for="lists"><strong><?php _e('Select a MailWizz list:', UETM_TEXTDOMAIN ); ?></strong></label></th>
                <td>
                    <select id="lists" name="lists">
                        <?php
                        foreach ( $fresh_lists as $list ) {
                            ?>
                            <option value="<?php echo esc_attr( $list['list_uid'] );?>"><?php echo esc_html( $list['name'] );?></option>
                            <?php
                        }
                        ?>
                    </select>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="roles"><strong><?php _e('Select a user role:', UETM_TEXTDOMAIN ); ?></strong></label></th>
                <td>
                    <select multiple id="roles" name="roles[]">
                        <?php
                        foreach ( get_editable_roles() as $role ) {
                            ?>
                            <option value="<?php echo esc_attr( $role['name'] );?>"><?php echo esc_html( $role['name'] );?></option>
                            <?php
                        }
                        ?>
                    </select>
                </td>
            </tr>
            </tbody>

        </table>
        <p>
            <input type="submit" name="export" id="export" class="button-primary" value="<?php esc_attr_e('Export contacts to the selected list', UETM_TEXTDOMAIN ) ?>" />
        </p>
    <?php
            }
    ?>

    </form>
<?php
}

/**
 * @param $nonce
 * @param $api_url
 * @param $public_key
 * @param $private_key
 * @return array
 */
function uetm_validate_attributes( $nonce, $api_url, $public_key, $private_key ) {

    $errors = array();

    if ( empty( $api_url ) || ! filter_var( $api_url, FILTER_VALIDATE_URL ) ) {
        $errors['api_url'] = __( 'Please type a valid API url!', UETM_TEXTDOMAIN );
    }
    if ( empty( $public_key ) || strlen( $public_key ) != 40 ) {
        $errors['public_key'] = __( 'Please type a public API key!', UETM_TEXTDOMAIN );
    }
    if ( empty( $private_key ) || strlen( $private_key ) != 40 ) {
        $errors['private_key'] = __( 'Please type a private API key!', UETM_TEXTDOMAIN );
    }

    if ( ! wp_verify_nonce( $nonce, basename(__FILE__ ) ) ) {
        $errors['nonce'] = __( 'Invalid nonce!', UETM_TEXTDOMAIN );
    }

    return $errors;
}

/**
 * @param $api_url
 * @param $public_key
 * @param $private_key
 * @return MailWizzApi_Config
 */
function uetm_build_sdk_config( $api_url, $public_key, $private_key ) {
    return new MailWizzApi_Config( array(
        'apiUrl'        => $api_url,
        'publicKey'     => $public_key,
        'privateKey'    => $private_key,
    ) );
}

/**
 * @param $api_url
 * @param $public_key
 * @param $private_key
 * @param $list_uid
 * @param $roles
 */
function uetm_export_to_mwz( $api_url, $public_key, $private_key, $list_uid, $roles) {

    if ( ! $list_uid) {
        Uetm_Notice_Store::add( new Uetm_Notice( __( 'Please select a list where to export the data', UETM_TEXTDOMAIN ), 'notice-warning' ) );
        return;
    }

    if ( ! $roles ) {
        Uetm_Notice_Store::add( new Uetm_Notice( __( 'No role selected, we will be exporting all', UETM_TEXTDOMAIN ), 'notice-warning' ) );
        $roles = null;
    }

    MailWizzApi_Base::setConfig( uetm_build_sdk_config( $api_url, $public_key, $private_key ) );

    $endpoint = new MailWizzApi_Endpoint_ListSubscribers();

    $users = get_users( array(
        'role__in'   => $roles,
    ) );

    if ( ! $users ) {
        Uetm_Notice_Store::add( new Uetm_Notice( __( 'No users found for your selection', UETM_TEXTDOMAIN ), 'notice-error' ) );
        return;
    }

    $total_users             = count( $users );
    $subscribed_with_success = 0;
    $subscribed_with_failure = 0;

    foreach ($users as $user) {
        $response = $endpoint->create( $list_uid, array(
            'EMAIL' => $user->user_email,
            'FNAME' => $user->first_name,
            'LNAME' => $user->last_name
        ) );

        if ( isset( $response->body['status'] ) && $response->body['status'] == 'error' && isset( $response->body['error'] ) ) {
            $subscribed_with_failure ++;
        }

        if ( isset( $response->body['status'] ) && $response->body['status'] == 'success' ) {
            $subscribed_with_success ++;
        }
    }

    $message = array();
    $message[] = sprintf( __( 'Total users: %d', UETM_TEXTDOMAIN ), $total_users );
    $message[] = sprintf( __( 'Total subscribed users: %d', UETM_TEXTDOMAIN ), $subscribed_with_success );
    $message[] = sprintf( __( 'Total errors: %d', UETM_TEXTDOMAIN ), $subscribed_with_failure );

    $notice_class  = ( $subscribed_with_failure > 0 ) ? 'notice-warning' : 'notice-success';

    Uetm_Notice_Store::add( new Uetm_Notice( $message, $notice_class ) );
}
