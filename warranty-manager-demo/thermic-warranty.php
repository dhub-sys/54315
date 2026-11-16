<?php
/**
 * Plugin Name: Warranty Manager – Demo
 * Description: ΕΚΔΟΣΗ ΕΠΙΔΕΙΞΗΣ για πωλητές: ο σειριακός συμπληρώνεται αυτόματα από λίστα 10 δοκιμαστικών σειριακών και κάθε καταχώρηση διαγράφεται 5 λεπτά μετά την ολοκλήρωση, ώστε ο σειριακός να είναι ξανά διαθέσιμος. Μόνο για ιδιωτική εγκατάσταση επίδειξης. Warranty Manager – αυτόνομη πλατφόρμα (standalone app) ηλεκτρονικής ενεργοποίησης και διαχείριση εγγυήσεων με σειριακούς αριθμούς: εγγραφή με επαλήθευση email, πύλη πελάτη, ειδοποιήσεις Service/λήξης μέσω email και SMS, διαχειριστικό με μαζική εισαγωγή από Excel/CSV και καταγραφή συγκαταθέσεων (GDPR).
 * Version: 0.0.0
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Plugin URI: https://www.dazzlink.gr
 * Author: Dazzlink
 * Author URI: https://www.dazzlink.gr
 * Text Domain: thermic-warranty
 */
if (!defined('ABSPATH')) exit;

// The demo edition and the full plugin use the same classes: never both on one site.
if (defined('TW_VERSION') || class_exists('TW_DB')) {
    add_action('admin_notices', function () {
        echo '<div class="notice notice-error"><p><strong>Warranty Manager – Demo:</strong> υπάρχει ήδη ενεργό το κανονικό Warranty Manager σε αυτό το site. Η έκδοση επίδειξης εγκαθίσταται μόνο σε ξεχωριστή, ιδιωτική εγκατάσταση.</p></div>';
    });
    return;
}

define('TW_VERSION', '0.0.0');
/** Demo edition: auto-assigned serials, every activation deleted after TW_Demo::RESET_MINUTES. */
define('TW_DEMO', true);
define('TW_FILE', __FILE__);
define('TW_DIR', plugin_dir_path(__FILE__));
define('TW_URL', plugin_dir_url(__FILE__));

require_once TW_DIR . 'includes/class-tw-db.php';
require_once TW_DIR . 'includes/class-tw-settings.php';
require_once TW_DIR . 'includes/class-tw-terms.php';
require_once TW_DIR . 'includes/class-tw-claims.php';
require_once TW_DIR . 'includes/class-tw-i18n.php';
require_once TW_DIR . 'includes/class-tw-schedule.php';
require_once TW_DIR . 'includes/class-tw-util.php';
require_once TW_DIR . 'includes/class-tw-messenger.php';
require_once TW_DIR . 'includes/class-tw-brevo.php';
require_once TW_DIR . 'includes/class-tw-otp.php';
require_once TW_DIR . 'includes/class-tw-guard.php';
require_once TW_DIR . 'includes/class-tw-access.php';
require_once TW_DIR . 'includes/class-tw-warranty.php';
require_once TW_DIR . 'includes/class-tw-frontend.php';
require_once TW_DIR . 'includes/class-tw-cron.php';
require_once TW_DIR . 'includes/class-tw-importer.php';
require_once TW_DIR . 'includes/class-tw-partners.php';
require_once TW_DIR . 'includes/class-tw-xlsx.php';
require_once TW_DIR . 'includes/class-tw-privacy.php';
require_once TW_DIR . 'includes/class-tw-backup.php';
require_once TW_DIR . 'includes/class-tw-backup-remote.php';
require_once TW_DIR . 'includes/class-tw-admin.php';
require_once TW_DIR . 'includes/class-tw-activity.php';
require_once TW_DIR . 'includes/class-tw-staff-auth.php';
require_once TW_DIR . 'includes/class-tw-app.php';
require_once TW_DIR . 'includes/class-tw-demo.php';

register_activation_hook(__FILE__, ['TW_DB', 'activate']);
register_deactivation_hook(__FILE__, function () {
    TW_Cron::unschedule();
    TW_Backup::unschedule();
    wp_unschedule_hook(TW_Demo::HOOK);
});

add_action('plugins_loaded', function () {
    TW_DB::maybe_upgrade();
    TW_Frontend::init();
    TW_Partners::init();
    TW_Terms_Public::init();
    TW_Cron::init();
    TW_Privacy::init();
    TW_Privacy_Text::init();
    TW_App::init();
    TW_Brevo::init();
    TW_Backup::init();
    TW_Backup_Remote::init();
    TW_Demo::init();
    if (is_admin()) {
        TW_Admin::init();
        TW_Access::init();
    }
});
