<?php
if (!defined('ABSPATH')) exit;

/**
 * Warranty Manager – Sales Demo.
 *
 * Runs on the SAME site as the production Warranty Manager, fully separated from it: own classes (TWD_*),
 * own tables ({prefix}twd_*), own ajax actions, cookies and upload folder. It only READS the production
 * branding, company details and warranty terms, so the demo looks exactly like the real platform.
 *
 * How a demo works
 *  - It opens only through the shortcode [warranty_sales_demo] on a password-protected WordPress page.
 *  - «Έναρξη επίδειξης» starts a session of N minutes (default 10) and gives it one of the 10 pre-entered
 *    random serials. The serial belongs to that session only (unique key), so parallel demos never collide.
 *  - The only email accepted is the demo email set in Settings → Warranty Sales Demo. No email or SMS is
 *    ever sent: the email code (OTP) is shown as a guided, explained step and filled in automatically.
 *  - «Αυτόματη συμπλήρωση» fills the owner/purchase details (and the Service form) with sample data.
 *  - In «Οι εγγυήσεις μου» the Service submission of the next milestone is open at once.
 *  - When the session ends (time up or «Τερματισμός»), everything the demo created is deleted and the serial
 *    is free again: WP-Cron at the expiry time, plus a sweep on every demo request (safety net).
 */
final class TWD_Demo {
    const OPTION = 'twd_demo_settings';
    const COOKIE = 'twd_demo';
    const HOOK = 'twd_demo_expire';
    const SHORTCODE = 'warranty_sales_demo';
    const POOL_SIZE = 10;
    const BATCH = 'sales-demo';
    const SWEEP_SECONDS = 15;

    /** @var object|null|false cached session of this request (false = not looked up yet) */
    private static $session = false;

    public static function init() {
        add_shortcode(self::SHORTCODE, [__CLASS__, 'shortcode']);
        add_action(self::HOOK, [__CLASS__, 'sweep_now']);
        add_action('template_redirect', [__CLASS__, 'template_redirect'], 1);
        add_action('admin_init', [__CLASS__, 'ajax_gate'], 0);
        foreach (['demo_start', 'demo_end'] as $a) {
            add_action('wp_ajax_twd_' . $a, [__CLASS__, 'ajax_' . $a]);
            add_action('wp_ajax_nopriv_twd_' . $a, [__CLASS__, 'ajax_' . $a]);
        }
        if (is_admin()) {
            add_action('admin_menu', [__CLASS__, 'admin_menu']);
            add_action('admin_post_twd_demo_save', [__CLASS__, 'handle_save']);
            add_action('admin_post_twd_demo_end_all', [__CLASS__, 'handle_end_all']);
            add_action('admin_post_twd_demo_new_serials', [__CLASS__, 'handle_new_serials']);
            add_filter('plugin_action_links_' . plugin_basename(TWD_FILE), function ($links) {
                array_unshift($links, '<a href="' . esc_url(self::settings_url()) . '">Ρυθμίσεις</a>');
                return $links;
            });
        }
    }

    // ------------------------------------------------------------------ settings

    public static function defaults() {
        return ['email' => '', 'minutes' => 10, 'product_name' => 'Προϊόν επίδειξης', 'product_code' => 'DEMO', 'page_id' => 0];
    }

    public static function settings() {
        $s = get_option(self::OPTION, []);
        return array_merge(self::defaults(), is_array($s) ? $s : []);
    }

    public static function get($k) {
        return self::settings()[$k] ?? null;
    }

    private static function save(array $values) {
        update_option(self::OPTION, array_merge(self::settings(), $values), false);
    }

    public static function demo_email() {
        return strtolower(trim((string) self::get('email')));
    }

    public static function minutes() {
        return max(1, min(60, (int) self::get('minutes')));
    }

    /** URL of the demo page («my» = the «Οι εγγυήσεις μου» tab). */
    public static function page_url($screen = '') {
        $id = (int) self::get('page_id');
        $url = $id ? get_permalink($id) : '';
        if (!$url) $url = home_url('/');
        return $screen === 'my' ? $url . '#twd-my' : $url;
    }

    public static function settings_url() {
        return admin_url('options-general.php?page=twd-sales-demo');
    }

    // ------------------------------------------------------------------ install

    public static function table() {
        return TWD_DB::table('demo_sessions');
    }

    public static function install() {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $c = $wpdb->get_charset_collate();
        dbDelta("CREATE TABLE " . self::table() . " (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  token_hash char(64) NOT NULL,
  serial_id bigint(20) unsigned NOT NULL,
  page_id bigint(20) unsigned NOT NULL DEFAULT 0,
  ip varchar(64) NOT NULL DEFAULT '',
  started_at datetime NOT NULL,
  expires_at datetime NOT NULL,
  PRIMARY KEY  (id),
  UNIQUE KEY serial_id (serial_id),
  KEY expires_at (expires_at)
) $c;");
        if (!(int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM " . TWD_DB::table('serials') . " WHERE import_batch = %s", self::BATCH))) {
            self::create_serials(self::POOL_SIZE);
        }
    }

    /** N new random serials (e.g. 7KQ2-M9XA-4PTD). */
    public static function create_serials($n) {
        global $wpdb;
        $t = TWD_DB::table('serials');
        $made = 0;
        for ($guard = 0; $made < $n && $guard < $n * 20; $guard++) {
            $sn = TWD_Util::random_code(4) . '-' . TWD_Util::random_code(4) . '-' . TWD_Util::random_code(4);
            $ok = $wpdb->query($wpdb->prepare(
                "INSERT IGNORE INTO $t (serial_no, product_code, product_name, status, import_batch, created_at) VALUES (%s, %s, %s, 'available', %s, %s)",
                $sn, (string) self::get('product_code'), (string) self::get('product_name'), self::BATCH, TWD_DB::now()
            ));
            if ($ok) $made++;
        }
        return $made;
    }

    private static function mysql_time($offset = 0) {
        return date('Y-m-d H:i:s', current_time('timestamp') + (int) $offset);
    }

    // ------------------------------------------------------------------ session

    private static function cookie_parts() {
        $raw = isset($_COOKIE[self::COOKIE]) ? (string) $_COOKIE[self::COOKIE] : '';
        return preg_match('/^(\d+)\.([a-f0-9]{40})$/', $raw, $m) ? [(int) $m[1], $m[2]] : null;
    }

    private static function set_cookie($value, $expires) {
        setcookie(self::COOKIE, $value, ['expires' => $expires, 'path' => COOKIEPATH ?: '/', 'domain' => COOKIE_DOMAIN ?: '', 'secure' => is_ssl(), 'httponly' => true, 'samesite' => 'Lax']);
        if ($value === '') unset($_COOKIE[self::COOKIE]); else $_COOKIE[self::COOKIE] = $value;
        self::$session = false;
    }

    /** This browser's running demo (with its serial), or null. */
    public static function current() {
        if (self::$session !== false) return self::$session;
        global $wpdb;
        $c = self::cookie_parts();
        $row = null;
        if ($c) {
            $row = $wpdb->get_row($wpdb->prepare(
                "SELECT d.*, s.serial_no AS serial, s.product_name, s.product_code FROM " . self::table() . " d JOIN " . TWD_DB::table('serials') . " s ON s.id = d.serial_id
                 WHERE d.id = %d AND d.token_hash = %s AND d.expires_at > %s",
                $c[0], TWD_OTP::hash($c[1]), TWD_DB::now()
            )) ?: null;
        }
        return self::$session = $row;
    }

    public static function session_serial_id() {
        $s = self::current();
        return $s ? (int) $s->serial_id : 0;
    }

    public static function seconds_left($s = null) {
        $s = $s ?: self::current();
        return $s ? max(0, strtotime($s->expires_at) - current_time('timestamp')) : 0;
    }

    /** The demo page is a published, password-protected page with the shortcode, and this visitor has unlocked it. */
    public static function page_ok($post) {
        return $post && $post->post_password !== '' && !post_password_required($post) && has_shortcode((string) $post->post_content, self::SHORTCODE);
    }

    /** Starts a demo: a free serial is given to this browser only (UNIQUE serial_id), for minutes() minutes. */
    public static function start($page_id) {
        global $wpdb;
        self::sweep_now();
        if ($cur = self::current()) return $cur;
        $free = $wpdb->get_results(
            "SELECT s.id FROM " . TWD_DB::table('serials') . " s LEFT JOIN " . self::table() . " d ON d.serial_id = s.id
             WHERE s.status = 'available' AND d.id IS NULL ORDER BY RAND() LIMIT 20"
        );
        $ttl = self::minutes() * MINUTE_IN_SECONDS;
        foreach ($free as $f) {
            $token = bin2hex(random_bytes(20));
            $suppress = $wpdb->suppress_errors(true);
            $ok = $wpdb->insert(self::table(), [
                'token_hash' => TWD_OTP::hash($token), 'serial_id' => (int) $f->id, 'page_id' => (int) $page_id,
                'ip' => TWD_Util::client_ip(), 'started_at' => TWD_DB::now(), 'expires_at' => self::mysql_time($ttl),
            ]);
            $wpdb->suppress_errors($suppress);
            if ($ok) {
                $id = (int) $wpdb->insert_id;
                self::set_cookie($id . '.' . $token, time() + $ttl + 60);
                wp_schedule_single_event(time() + $ttl + 5, self::HOOK);
                TWD_DB::log_event('demo_started', ['minutes' => self::minutes()], 0, (int) $f->id, 'demo');
                return self::current();
            }
        }
        return null;
    }

    public static function ajax_demo_start() {
        check_ajax_referer('twd_demo_start');
        if (!self::page_ok(get_post((int) ($_POST['page'] ?? 0)))) {
            wp_send_json_error(['message' => twd__('Η επίδειξη ανοίγει μόνο από την προστατευμένη σελίδα επίδειξης. Ανανεώστε τη σελίδα.')], 403);
        }
        if (self::demo_email() === '') wp_send_json_error(['message' => twd__('Δεν έχει οριστεί email επίδειξης στις ρυθμίσεις του plugin.')], 409);
        if (!TWD_Guard::hit('demo_start', 60, HOUR_IN_SECONDS)) wp_send_json_error(['message' => twd__('Πάρα πολλά αιτήματα. Δοκιμάστε ξανά σε λίγο.')], 429);
        $s = self::start((int) $_POST['page']);
        if (!$s) {
            $wait = self::seconds_to_next_free();
            wp_send_json_error(['message' => $wait !== null
                ? sprintf(twd__('Όλοι οι σειριακοί επίδειξης χρησιμοποιούνται αυτή τη στιγμή. Ο επόμενος ελευθερώνεται σε περίπου %s.'), self::mmss($wait))
                : twd__('Δεν υπάρχουν διαθέσιμοι σειριακοί επίδειξης.')], 409);
        }
        wp_send_json_success(['serial' => $s->serial, 'left' => self::seconds_left($s)]);
    }

    public static function ajax_demo_end() {
        $s = self::current();
        if ($s) self::purge_session($s);
        self::set_cookie('', time() - 3600);
        wp_send_json_success(['message' => twd__('Η επίδειξη τερματίστηκε και τα στοιχεία της διαγράφηκαν.')]);
    }

    /**
     * Every other ajax call of the demo needs a running demo session (started from the protected page).
     * admin_init runs for admin-ajax.php before the action handlers.
     */
    public static function ajax_gate() {
        if (!wp_doing_ajax()) return;
        $action = isset($_REQUEST['action']) ? (string) $_REQUEST['action'] : '';
        if (strpos($action, 'twd_') !== 0) return;
        self::sweep();
        if (in_array($action, ['twd_demo_start', 'twd_demo_end'], true)) return;
        if (!self::current()) {
            wp_send_json_error(['message' => twd__('Ο χρόνος της επίδειξης έληξε. Τα στοιχεία της διαγράφηκαν· ξεκινήστε νέα επίδειξη.'), 'field' => 'demo_expired'], 401);
        }
    }

    public static function mmss($sec) {
        $sec = max(0, (int) $sec);
        return sprintf('%d:%02d', intdiv($sec, 60), $sec % 60);
    }

    public static function seconds_to_next_free() {
        global $wpdb;
        $next = $wpdb->get_var("SELECT MIN(expires_at) FROM " . self::table());
        return $next ? max(0, strtotime($next) - current_time('timestamp')) : null;
    }

    public static function pool_stats() {
        global $wpdb;
        $s = TWD_DB::table('serials');
        return [
            'total' => (int) $wpdb->get_var("SELECT COUNT(*) FROM $s WHERE status <> 'void'"),
            'free' => (int) $wpdb->get_var("SELECT COUNT(*) FROM $s s LEFT JOIN " . self::table() . " d ON d.serial_id = s.id WHERE s.status = 'available' AND d.id IS NULL"),
        ];
    }

    // ------------------------------------------------------------------ cleanup

    /** Throttled sweep for page loads and ajax calls. */
    public static function sweep() {
        if (get_transient('twd_demo_swept')) return;
        set_transient('twd_demo_swept', 1, self::SWEEP_SECONDS);
        self::sweep_now();
    }

    /** Ends every session whose time is up. */
    public static function sweep_now() {
        global $wpdb;
        foreach ($wpdb->get_results($wpdb->prepare("SELECT * FROM " . self::table() . " WHERE expires_at <= %s LIMIT 100", TWD_DB::now())) as $s) {
            self::purge_session($s);
        }
        // A warranty whose serial has no session any more (e.g. a session row removed by hand) is never left behind.
        foreach ($wpdb->get_col("SELECT w.id FROM " . TWD_DB::table('warranties') . " w LEFT JOIN " . self::table() . " d ON d.serial_id = w.serial_id WHERE d.id IS NULL LIMIT 100") as $wid) {
            self::purge_warranty((int) $wid);
        }
    }

    /** Ends all demos now (settings page). */
    public static function end_all() {
        global $wpdb;
        $rows = $wpdb->get_results("SELECT * FROM " . self::table());
        foreach ($rows as $s) self::purge_session($s);
        self::sweep_now();
        return count($rows);
    }

    private static function unlink_rel($rel) {
        if ($rel && ($p = TWD_Util::invoice_path($rel))) @unlink($p);
    }

    /** Deletes everything a demo created and frees its serial. */
    public static function purge_session($s) {
        global $wpdb;
        $sid = (int) $s->serial_id;
        foreach ($wpdb->get_col($wpdb->prepare("SELECT id FROM " . TWD_DB::table('warranties') . " WHERE serial_id = %d", $sid)) as $wid) {
            self::purge_warranty((int) $wid);
        }
        $wpdb->delete(TWD_DB::table('otps'), ['serial_id' => $sid]); // registration, portal sign-in and erase codes
        foreach (['events', 'events_archive'] as $n) {
            $wpdb->query($wpdb->prepare("DELETE FROM " . TWD_DB::table($n) . " WHERE serial_id = %d", $sid));
        }
        $wpdb->query($wpdb->prepare("UPDATE " . TWD_DB::table('serials') . " SET status = 'available' WHERE id = %d AND status = 'registered'", $sid));
        $wpdb->delete(self::table(), ['id' => (int) $s->id]);
        if (self::$session && (int) self::$session->id === (int) $s->id) self::$session = null;
    }

    public static function purge_warranty($wid) {
        global $wpdb;
        $t = function ($n) { return TWD_DB::table($n); };
        $w = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$t('warranties')} WHERE id = %d", $wid));
        if (!$w) return;
        self::unlink_rel($w->invoice_file);
        foreach ($wpdb->get_col($wpdb->prepare("SELECT receipt_file FROM {$t('services')} WHERE warranty_id = %d", $wid)) as $f) self::unlink_rel($f);
        foreach ($wpdb->get_col($wpdb->prepare("SELECT files FROM {$t('claims')} WHERE warranty_id = %d", $wid)) as $json) {
            foreach ((array) json_decode((string) $json, true) as $f) self::unlink_rel(is_array($f) ? ($f['file'] ?? '') : '');
        }
        foreach (['consents', 'services', 'notifications', 'claims'] as $n) $wpdb->delete($t($n), ['warranty_id' => $wid]);
        foreach (['events', 'events_archive'] as $n) $wpdb->delete($t($n), ['warranty_id' => $wid]);
        $wpdb->delete($t('warranties'), ['id' => $wid]);
        $wpdb->query($wpdb->prepare("UPDATE {$t('serials')} SET status = 'available' WHERE id = %d AND status = 'registered'", (int) $w->serial_id));
        TWD_Warranty::forget_services();
    }

    // ------------------------------------------------------------------ front end

    public static function template_redirect() {
        if (!is_singular()) return;
        $post = get_post();
        if (!$post || !has_shortcode((string) $post->post_content, self::SHORTCODE)) return;
        if (!defined('DONOTCACHEPAGE')) define('DONOTCACHEPAGE', true);
        nocache_headers();
        header('X-Robots-Tag: noindex, nofollow', true);
        self::sweep();
        // Printable «Αντίγραφο των στοιχείων μου» of the demo customer.
        if (isset($_GET['copy']) && self::page_ok($post) && self::current() && ($portal = TWD_Frontend::session())) {
            $s = TWD_Settings::all();
            $email = $portal->email;
            $warranties = TWD_Warranty::by_email($email);
            include TWD_DIR . 'templates/app-copy.php';
            exit;
        }
    }

    public static function shortcode() {
        $post = get_post();
        if (!$post || $post->post_password === '') {
            return '<div class="tw-alert tw-alert--error">' . esc_html('Warranty Sales Demo: η επίδειξη ανοίγει μόνο σε σελίδα με «Προστασία με συνθηματικό» (Ορατότητα → Προστασία με συνθηματικό).') . '</div>';
        }
        if (self::demo_email() === '') {
            return '<div class="tw-alert tw-alert--error">' . esc_html('Warranty Sales Demo: ορίστε πρώτα το email επίδειξης στις Ρυθμίσεις → Warranty Sales Demo.') . '</div>';
        }
        if ((int) self::get('page_id') !== (int) $post->ID) self::save(['page_id' => (int) $post->ID]);

        TWD_Frontend::enqueue();
        wp_enqueue_style('twd-demo', TWD_URL . 'assets/css/demo.css', ['twd-front'], TWD_VERSION);
        wp_enqueue_script('twd-demo', TWD_URL . 'assets/js/demo.js', ['twd-front'], TWD_VERSION, true);

        $session = self::current();
        $stats = self::pool_stats();
        $minutes = self::minutes();
        $page_id = (int) $post->ID;
        $nonce = wp_create_nonce('twd_demo_start');
        ob_start();
        include TWD_DIR . 'templates/demo.php';
        return ob_get_clean();
    }

    /** Sample owner/purchase details for «Αυτόματη συμπλήρωση» (Greek ΑΦΜ with a valid check digit). */
    public static function sample_afm() {
        do {
            $d = [];
            for ($i = 0; $i < 8; $i++) $d[] = random_int(0, 9);
            $sum = 0;
            for ($i = 0; $i < 8; $i++) $sum += $d[$i] * (2 ** (8 - $i));
            $afm = implode('', $d) . (($sum % 11) % 10);
        } while (!TWD_Util::valid_afm($afm));
        return $afm;
    }

    public static function js_config() {
        return [
            'email' => self::demo_email(),
            'afm' => [self::sample_afm(), self::sample_afm()],
            'left' => self::seconds_left(),
            'today' => TWD_Schedule::today(),
        ];
    }

    // ------------------------------------------------------------------ settings page (wp-admin)

    public static function admin_menu() {
        add_options_page('Warranty Sales Demo', 'Warranty Sales Demo', 'manage_options', 'twd-sales-demo', [__CLASS__, 'admin_page']);
    }

    private static function admin_guard($action) {
        if (!current_user_can('manage_options')) wp_die('Δεν έχετε δικαίωμα πρόσβασης.', 403);
        check_admin_referer($action);
    }

    private static function back($msg) {
        set_transient('twd_demo_notice_' . get_current_user_id(), $msg, 60);
        wp_safe_redirect(self::settings_url());
        exit;
    }

    public static function handle_save() {
        self::admin_guard('twd_demo_save');
        $email = strtolower(sanitize_email(wp_unslash($_POST['email'] ?? '')));
        if ($email !== '' && !is_email($email)) self::back('Το email δεν είναι έγκυρο.');
        $name = sanitize_text_field(wp_unslash($_POST['product_name'] ?? ''));
        $code = sanitize_text_field(wp_unslash($_POST['product_code'] ?? ''));
        self::save([
            'email' => $email,
            'minutes' => max(1, min(60, (int) ($_POST['minutes'] ?? 10))),
            'product_name' => $name !== '' ? $name : self::defaults()['product_name'],
            'product_code' => $code,
        ]);
        global $wpdb;
        $wpdb->update(TWD_DB::table('serials'), ['product_name' => self::get('product_name'), 'product_code' => self::get('product_code')], ['import_batch' => self::BATCH]);
        self::back('Οι ρυθμίσεις αποθηκεύτηκαν.');
    }

    public static function handle_end_all() {
        self::admin_guard('twd_demo_end_all');
        $n = self::end_all();
        self::back($n ? sprintf('Τερματίστηκαν %d επιδείξεις και διαγράφηκαν τα στοιχεία τους.', $n) : 'Δεν υπήρχαν επιδείξεις σε εξέλιξη.');
    }

    public static function handle_new_serials() {
        self::admin_guard('twd_demo_new_serials');
        global $wpdb;
        if ((int) $wpdb->get_var("SELECT COUNT(*) FROM " . self::table())) self::back('Υπάρχουν επιδείξεις σε εξέλιξη· τερματίστε τες πρώτα.');
        self::sweep_now();
        $wpdb->delete(TWD_DB::table('serials'), ['import_batch' => self::BATCH]);
        $n = self::create_serials(self::POOL_SIZE);
        self::back(sprintf('Δημιουργήθηκαν %d νέοι τυχαίοι σειριακοί.', $n));
    }

    public static function admin_page() {
        global $wpdb;
        $s = self::settings();
        $msg = get_transient('twd_demo_notice_' . get_current_user_id());
        if ($msg) delete_transient('twd_demo_notice_' . get_current_user_id());
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT s.serial_no, s.status, d.started_at, d.expires_at FROM " . TWD_DB::table('serials') . " s
             LEFT JOIN " . self::table() . " d ON d.serial_id = s.id AND d.expires_at > %s ORDER BY s.serial_no LIMIT 200", TWD_DB::now()
        ));
        $page = (int) $s['page_id'] ? get_post((int) $s['page_id']) : null;
        $form = function ($action, $label, $confirm = '', $class = 'button') {
            echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" style="display:inline-block;margin-right:8px"' . ($confirm ? ' onsubmit="return confirm(' . esc_attr(wp_json_encode($confirm)) . ')"' : '') . '>';
            wp_nonce_field($action);
            echo '<input type="hidden" name="action" value="' . esc_attr($action) . '"><button class="' . esc_attr($class) . '">' . esc_html($label) . '</button></form>';
        };
        ?>
        <div class="wrap">
            <h1>Warranty Sales Demo <small style="font-size:13px;color:#646970">έκδοση <?php echo esc_html(TWD_VERSION); ?></small></h1>
            <?php if ($msg) : ?><div class="notice notice-success is-dismissible"><p><?php echo esc_html($msg); ?></p></div><?php endif; ?>
            <?php if (!class_exists('TW_Settings')) : ?><div class="notice notice-warning"><p>Το κανονικό Warranty Manager δεν είναι ενεργό: η επίδειξη λειτουργεί, αλλά χωρίς το λογότυπο, τα χρώματα, τα στοιχεία εταιρείας και τους όρους εγγύησης της παραγωγής.</p></div><?php endif; ?>

            <h2>Πώς ανοίγει</h2>
            <ol>
                <li>Σελίδες → Προσθήκη νέας, με περιεχόμενο το shortcode <code>[<?php echo esc_html(self::SHORTCODE); ?>]</code>.</li>
                <li>Στη σελίδα: Ορατότητα → <strong>Προστασία με συνθηματικό</strong>, και δώστε το συνθηματικό μόνο στους πωλητές.</li>
                <li>Ο πωλητής ανοίγει τη σελίδα, βάζει το συνθηματικό και πατά «Έναρξη επίδειξης».</li>
            </ol>
            <p>Σελίδα επίδειξης: <?php echo $page ? '<a href="' . esc_url(get_permalink($page)) . '" target="_blank">' . esc_html(get_the_title($page)) . '</a>' . ($page->post_password === '' ? ' <strong style="color:#b32d2e">– δεν έχει συνθηματικό!</strong>' : ' (με συνθηματικό)') : '<em>καμία ακόμη (ορίζεται αυτόματα όταν ανοίξει η σελίδα με το shortcode)</em>'; // phpcs:ignore ?></p>

            <h2>Ρυθμίσεις</h2>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <?php wp_nonce_field('twd_demo_save'); ?><input type="hidden" name="action" value="twd_demo_save">
                <table class="form-table">
                    <tr><th><label for="twd-email">Email επίδειξης</label></th><td><input type="email" id="twd-email" name="email" class="regular-text" value="<?php echo esc_attr($s['email']); ?>" required>
                        <p class="description">Το μόνο email με το οποίο γίνεται ενεργοποίηση και είσοδος στις «Οι εγγυήσεις μου» στην επίδειξη. Δεν στέλνεται ποτέ email ή SMS: ο κωδικός επαλήθευσης εμφανίζεται και συμπληρώνεται αυτόματα.</p></td></tr>
                    <tr><th><label for="twd-min">Διάρκεια επίδειξης</label></th><td><input type="number" id="twd-min" name="minutes" min="1" max="60" value="<?php echo (int) $s['minutes']; ?>" class="small-text"> λεπτά
                        <p class="description">Από το «Έναρξη επίδειξης». Στη λήξη διαγράφονται όλα όσα καταχωρήθηκαν και ο σειριακός ελευθερώνεται.</p></td></tr>
                    <tr><th><label for="twd-pn">Προϊόν σειριακών</label></th><td><input type="text" id="twd-pn" name="product_name" class="regular-text" value="<?php echo esc_attr($s['product_name']); ?>"> κωδικός <input type="text" name="product_code" class="small-text" value="<?php echo esc_attr($s['product_code']); ?>" style="width:110px"></td></tr>
                </table>
                <?php submit_button('Αποθήκευση'); ?>
            </form>

            <h2>Σειριακοί επίδειξης</h2>
            <table class="widefat striped" style="max-width:720px"><thead><tr><th>S/N</th><th>Κατάσταση</th><th>Λήξη επίδειξης</th></tr></thead><tbody>
            <?php foreach ($rows as $r) : ?>
                <tr><td><code><?php echo esc_html($r->serial_no); ?></code></td>
                    <td><?php echo esc_html($r->status === 'void' ? 'Ακυρωμένος' : ($r->expires_at ? 'Σε επίδειξη από ' . substr($r->started_at, 11, 5) : 'Διαθέσιμος')); ?></td>
                    <td><?php echo esc_html($r->expires_at ? substr($r->expires_at, 11, 5) : '—'); ?></td></tr>
            <?php endforeach; ?>
            <?php if (!$rows) echo '<tr><td colspan="3">Δεν υπάρχουν σειριακοί.</td></tr>'; ?>
            </tbody></table>
            <p style="margin-top:14px">
                <?php $form('twd_demo_end_all', 'Τερματισμός όλων των επιδείξεων τώρα', 'Να τερματιστούν όλες οι επιδείξεις σε εξέλιξη και να διαγραφούν τα στοιχεία τους;'); ?>
                <?php $form('twd_demo_new_serials', 'Νέοι 10 τυχαίοι σειριακοί', 'Οι σημερινοί σειριακοί επίδειξης θα αντικατασταθούν από 10 νέους τυχαίους. Συνέχεια;'); ?>
            </p>
            <p class="description">Τα δεδομένα της επίδειξης είναι σε δικούς τους πίνακες (<code><?php echo esc_html(TWD_DB::table('*')); ?></code>) και δεν εμφανίζονται ποτέ στο κανονικό Warranty Manager.</p>
        </div>
        <?php
    }
}
