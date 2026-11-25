<?php
/**
 * Plugin Name: Warranty Manager – Sales Demo
 * Description: Επίδειξη του Warranty Manager για πωλητές, στο ίδιο site με την παραγωγή αλλά εντελώς ξεχωριστή από αυτή (δικοί της πίνακες). Ανοίγει μόνο από σελίδα με συνθηματικό (shortcode [warranty_sales_demo]): 10 τυχαίοι σειριακοί, επίδειξη 10 λεπτών που διαγράφεται στη λήξη, είσοδος μόνο με το email επίδειξης, OTP μόνο οπτικά, αυτόματη συμπλήρωση στοιχείων και ανοιχτή υποβολή Service. Δεν στέλνει ποτέ email ή SMS.
 * Version: 0.0.0
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Plugin URI: https://www.dazzlink.gr
 * Author: Dazzlink
 * Author URI: https://www.dazzlink.gr
 * Text Domain: warranty-sales-demo
 */
if (!defined('ABSPATH')) exit;

define('TWD_VERSION', '0.0.0');
/** Based on Warranty Manager 1.34.0. */
define('TWD_BASE_VERSION', '1.34.0');
define('TWD_FILE', __FILE__);
define('TWD_DIR', plugin_dir_path(__FILE__));
define('TWD_URL', plugin_dir_url(__FILE__));

require_once TWD_DIR . 'includes/class-twd-db.php';
require_once TWD_DIR . 'includes/class-twd-settings.php';
require_once TWD_DIR . 'includes/class-twd-terms.php';
require_once TWD_DIR . 'includes/class-twd-claims.php';
require_once TWD_DIR . 'includes/class-twd-i18n.php';
require_once TWD_DIR . 'includes/class-twd-schedule.php';
require_once TWD_DIR . 'includes/class-twd-util.php';
require_once TWD_DIR . 'includes/class-twd-messenger.php';
require_once TWD_DIR . 'includes/class-twd-brevo.php';
require_once TWD_DIR . 'includes/class-twd-otp.php';
require_once TWD_DIR . 'includes/class-twd-guard.php';
require_once TWD_DIR . 'includes/class-twd-access.php';
require_once TWD_DIR . 'includes/class-twd-warranty.php';
require_once TWD_DIR . 'includes/class-twd-frontend.php';
require_once TWD_DIR . 'includes/class-twd-cron.php';
require_once TWD_DIR . 'includes/class-twd-importer.php';
require_once TWD_DIR . 'includes/class-twd-partners.php';
require_once TWD_DIR . 'includes/class-twd-xlsx.php';
require_once TWD_DIR . 'includes/class-twd-privacy.php';
require_once TWD_DIR . 'includes/class-twd-backup.php';
require_once TWD_DIR . 'includes/class-twd-backup-remote.php';
require_once TWD_DIR . 'includes/class-twd-admin.php';
require_once TWD_DIR . 'includes/class-twd-activity.php';
require_once TWD_DIR . 'includes/class-twd-staff-auth.php';
require_once TWD_DIR . 'includes/class-twd-app.php';
require_once TWD_DIR . 'includes/class-twd-demo.php';

register_activation_hook(__FILE__, ['TWD_DB', 'activate']);
register_deactivation_hook(__FILE__, function () {
    wp_unschedule_hook(TWD_Demo::HOOK);
});

// Only the customer side runs, on the demo page: no console, rewrites, reminders, backups, webhooks or
// exporters of its own (those belong to the production Warranty Manager on the same site).
add_action('plugins_loaded', function () {
    TWD_DB::maybe_upgrade();
    TWD_Frontend::init();
    TWD_Demo::init();
});
