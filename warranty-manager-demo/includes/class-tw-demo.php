<?php
if (!defined('ABSPATH')) exit;

/**
 * Demo edition (0.0.0) – for sales staff who demonstrate the platform.
 *
 * Everything works as in the full product (bot checks, email code, details, terms, consents, emails/SMS,
 * «Οι εγγυήσεις μου»), with three differences:
 *
 *  1. The serial number is not typed: step 1 is filled in automatically with a free serial from the demo
 *     pool (by default the 10 serials DEMO-0001 … DEMO-0010, created on activation). The serial is held for
 *     the browser that received it (cookie), so two demos running at the same time never get the same one.
 *  2. RESET_MINUTES after a warranty is activated, everything about it is deleted (warranty, consents,
 *     Service submissions, claims, messages log, uploaded files, activity log, email codes and the
 *     «Οι εγγυήσεις μου» sign-in of that email) and its serial becomes available again for the next demo.
 *  3. A "demo" ribbon on every page, and a demo panel on the console dashboard (pool status, reset now).
 *
 * The reset runs from a one-off WP-Cron event per warranty and, as a safety net, from a sweep on the
 * plugin's own requests (at most every SWEEP_SECONDS), so it happens even where WP-Cron is unreliable.
 */
final class TW_Demo {
    const RESET_MINUTES = 5;
    /** How long a serial stays with the browser that received it, if the demo is not completed. */
    const HOLD_MINUTES = 60;
    const POOL_SIZE = 10;
    const PREFIX = 'DEMO-';
    const BATCH = 'demo';
    const PRODUCT = 'Προϊόν επίδειξης';
    const COOKIE = 'tw_demo_hold';
    const HOOK = 'tw_demo_reset';
    const SWEEP_SECONDS = 20;

    public static function init() {
        add_action(self::HOOK, [__CLASS__, 'sweep_now']);
        add_action('tw_warranty_activated', [__CLASS__, 'on_activated']);
        // Safety net for WP-Cron: our own pages, our ajax calls and the console.
        add_action('template_redirect', [__CLASS__, 'sweep'], -200);
        add_action('admin_init', [__CLASS__, 'maybe_sweep_ajax'], 0);
        add_action('wp_ajax_tw_demo_serial', [__CLASS__, 'ajax_serial']);
        add_action('wp_ajax_nopriv_tw_demo_serial', [__CLASS__, 'ajax_serial']);
        // The demo installation is not public: keep it out of search engines.
        add_filter('wp_robots', 'wp_robots_no_robots');
    }

    public static function table() {
        return TW_DB::table('demo_holds');
    }

    /** Table of held serials + the demo serial pool. Called from TW_DB::install(). */
    public static function install() {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $c = $wpdb->get_charset_collate();
        dbDelta("CREATE TABLE " . self::table() . " (
  serial_id bigint(20) unsigned NOT NULL,
  token_hash char(64) NOT NULL,
  expires_at datetime NOT NULL,
  created_at datetime NOT NULL,
  PRIMARY KEY  (serial_id),
  KEY expires_at (expires_at)
) $c;");
        self::seed();
    }

    /** The 10 demo serials (only the missing ones are added; existing serials are left as they are). */
    public static function seed() {
        global $wpdb;
        $t = TW_DB::table('serials');
        foreach (self::default_serials() as $sn) {
            $wpdb->query($wpdb->prepare(
                "INSERT IGNORE INTO $t (serial_no, product_code, product_name, status, import_batch, created_at) VALUES (%s, %s, %s, 'available', %s, %s)",
                $sn, 'DEMO', self::PRODUCT, self::BATCH, TW_DB::now()
            ));
        }
    }

    public static function default_serials() {
        $out = [];
        for ($i = 1; $i <= self::POOL_SIZE; $i++) $out[] = self::PREFIX . str_pad((string) $i, 4, '0', STR_PAD_LEFT);
        return $out;
    }

    private static function mysql_time($offset_seconds = 0) {
        return date('Y-m-d H:i:s', current_time('timestamp') + (int) $offset_seconds);
    }

    // ------------------------------------------------------------------ holds (one serial per demo in progress)

    private static function cookie_parts() {
        $raw = isset($_COOKIE[self::COOKIE]) ? (string) $_COOKIE[self::COOKIE] : '';
        if (!preg_match('/^(\d+)\.([a-f0-9]{32,64})$/', $raw, $m)) return null;
        return [(int) $m[1], $m[2]];
    }

    private static function set_cookie($value, $expires) {
        $opts = ['expires' => $expires, 'path' => COOKIEPATH ?: '/', 'domain' => COOKIE_DOMAIN ?: '', 'secure' => is_ssl(), 'httponly' => true, 'samesite' => 'Lax'];
        setcookie(self::COOKIE, $value, $opts);
        if ($value === '') unset($_COOKIE[self::COOKIE]); else $_COOKIE[self::COOKIE] = $value;
    }

    /** The serial this browser holds right now (still free), or null. */
    public static function current_hold() {
        global $wpdb;
        $c = self::cookie_parts();
        if (!$c) return null;
        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT s.*, s.serial_no AS serial FROM " . self::table() . " h JOIN " . TW_DB::table('serials') . " s ON s.id = h.serial_id
             WHERE h.serial_id = %d AND h.token_hash = %s AND h.expires_at > %s AND s.status = 'available'",
            $c[0], TW_OTP::hash($c[1]), TW_DB::now()
        ));
        return $row ?: null;
    }

    /** True when another demo in progress holds this serial (so this browser must not use it). */
    public static function held_by_other($serial_id) {
        global $wpdb;
        $h = $wpdb->get_row($wpdb->prepare("SELECT * FROM " . self::table() . " WHERE serial_id = %d AND expires_at > %s", (int) $serial_id, TW_DB::now()));
        if (!$h) return false;
        $c = self::cookie_parts();
        return !($c && $c[0] === (int) $serial_id && hash_equals($h->token_hash, TW_OTP::hash($c[1])));
    }

    /**
     * A free serial for this browser: the one it already holds, or the first free one not held by another demo.
     * The hold is an INSERT on the serial's primary key, so two browsers asking at once never get the same serial.
     * @return object|null serial row
     */
    public static function reserve() {
        global $wpdb;
        self::sweep_now();
        $cur = self::current_hold();
        if ($cur) {
            $wpdb->update(self::table(), ['expires_at' => self::mysql_time(self::HOLD_MINUTES * MINUTE_IN_SECONDS)], ['serial_id' => $cur->id]);
            self::set_cookie(self::cookie_parts()[0] . '.' . self::cookie_parts()[1], time() + self::HOLD_MINUTES * MINUTE_IN_SECONDS);
            return $cur;
        }
        $free = $wpdb->get_results(
            "SELECT s.*, s.serial_no AS serial FROM " . TW_DB::table('serials') . " s LEFT JOIN " . self::table() . " h ON h.serial_id = s.id
             WHERE s.status = 'available' AND h.serial_id IS NULL ORDER BY s.serial_no ASC LIMIT 50"
        );
        foreach ($free as $s) {
            $token = bin2hex(random_bytes(20));
            $suppress = $wpdb->suppress_errors(true);
            $ok = $wpdb->query($wpdb->prepare(
                "INSERT INTO " . self::table() . " (serial_id, token_hash, expires_at, created_at) VALUES (%d, %s, %s, %s)",
                $s->id, TW_OTP::hash($token), self::mysql_time(self::HOLD_MINUTES * MINUTE_IN_SECONDS), TW_DB::now()
            ));
            $wpdb->suppress_errors($suppress);
            if ($ok) {
                self::set_cookie($s->id . '.' . $token, time() + self::HOLD_MINUTES * MINUTE_IN_SECONDS);
                return $s;
            }
        }
        return null;
    }

    public static function release($serial_id) {
        global $wpdb;
        $wpdb->delete(self::table(), ['serial_id' => (int) $serial_id]);
        $c = self::cookie_parts();
        if ($c && $c[0] === (int) $serial_id) self::set_cookie('', time() - 3600);
    }

    /** Step 1 of the activation: the serial to show (read-only) in the form. */
    public static function ajax_serial() {
        if (!TW_Guard::hit('demo_serial', 300, HOUR_IN_SECONDS)) {
            wp_send_json_error(['message' => tw__('Πάρα πολλά αιτήματα. Δοκιμάστε ξανά σε λίγο.')], 429);
        }
        $s = self::reserve();
        $stats = self::pool_stats();
        if (!$s) {
            $wait = self::seconds_to_next_free();
            wp_send_json_error([
                'message' => $wait !== null
                    ? sprintf(tw__('Όλοι οι σειριακοί επίδειξης χρησιμοποιούνται αυτή τη στιγμή. Ο επόμενος ελευθερώνεται σε περίπου %s.'), self::mmss($wait))
                    : tw__('Όλοι οι σειριακοί επίδειξης χρησιμοποιούνται αυτή τη στιγμή. Δοκιμάστε ξανά σε λίγο.'),
                'retry' => $wait !== null ? max(10, min(60, $wait + 2)) : 30,
                'stats' => $stats,
            ]);
        }
        wp_send_json_success([
            'serial' => $s->serial,
            'product' => trim($s->product_name . ($s->product_code ? ' (' . $s->product_code . ')' : '')),
            'stats' => $stats,
            'reset_minutes' => self::RESET_MINUTES,
        ]);
    }

    public static function mmss($sec) {
        $sec = max(0, (int) $sec);
        return sprintf('%d:%02d', intdiv($sec, 60), $sec % 60);
    }

    /** ['free' => serials nobody holds, 'total' => serials not voided]. */
    public static function pool_stats() {
        global $wpdb;
        $s = TW_DB::table('serials');
        $total = (int) $wpdb->get_var("SELECT COUNT(*) FROM $s WHERE status <> 'void'");
        $free = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $s s LEFT JOIN " . self::table() . " h ON h.serial_id = s.id AND h.expires_at > %s WHERE s.status = 'available' AND h.serial_id IS NULL",
            TW_DB::now()
        ));
        return ['free' => $free, 'total' => $total];
    }

    /** Seconds until a serial is free again (a completed demo reset or a hold expiring), or null. */
    public static function seconds_to_next_free() {
        global $wpdb;
        $next_w = $wpdb->get_var("SELECT MIN(created_at) FROM " . TW_DB::table('warranties'));
        $next_h = $wpdb->get_var("SELECT MIN(expires_at) FROM " . self::table());
        $cands = [];
        if ($next_w) $cands[] = strtotime($next_w) + self::RESET_MINUTES * MINUTE_IN_SECONDS;
        if ($next_h) $cands[] = strtotime($next_h);
        if (!$cands) return null;
        return max(0, min($cands) - current_time('timestamp'));
    }

    // ------------------------------------------------------------------ reset after the demo

    public static function on_activated($w) {
        self::release($w->serial_id);
        wp_schedule_single_event(time() + self::RESET_MINUTES * MINUTE_IN_SECONDS + 5, self::HOOK, [(int) $w->id]);
    }

    /** Seconds left until this warranty is deleted. */
    public static function seconds_left($w) {
        return max(0, strtotime($w->created_at) + self::RESET_MINUTES * MINUTE_IN_SECONDS - current_time('timestamp'));
    }

    /** Admin-ajax requests of the plugin also run the sweep (admin_init fires for them). */
    public static function maybe_sweep_ajax() {
        if (wp_doing_ajax() && isset($_REQUEST['action']) && strpos((string) $_REQUEST['action'], 'tw_') === 0) self::sweep();
    }

    /** Throttled sweep for page loads. */
    public static function sweep() {
        if (get_transient('tw_demo_swept')) return;
        set_transient('tw_demo_swept', 1, self::SWEEP_SECONDS);
        self::sweep_now();
    }

    /** Deletes every demo whose time is up and frees expired holds. @return int warranties deleted */
    public static function sweep_now() {
        global $wpdb;
        // Demos left unfinished: their serial is free again, and the codes/log entries they created go too.
        $expired = $wpdb->get_col($wpdb->prepare("SELECT serial_id FROM " . self::table() . " WHERE expires_at <= %s", TW_DB::now()));
        if ($expired) {
            $wpdb->query($wpdb->prepare("DELETE FROM " . self::table() . " WHERE expires_at <= %s", TW_DB::now()));
            foreach ($expired as $sid) self::forget_unfinished((int) $sid);
        }
        $cut = self::mysql_time(-self::RESET_MINUTES * MINUTE_IN_SECONDS);
        $ids = $wpdb->get_col($wpdb->prepare("SELECT id FROM " . TW_DB::table('warranties') . " WHERE created_at <= %s ORDER BY id LIMIT 200", $cut));
        foreach ($ids as $id) self::purge((int) $id);
        return count($ids);
    }

    /** Codes and log entries of a demo that never reached the end (the serial has no warranty). */
    private static function forget_unfinished($serial_id) {
        global $wpdb;
        if ($wpdb->get_var($wpdb->prepare("SELECT id FROM " . TW_DB::table('warranties') . " WHERE serial_id = %d", $serial_id))) return;
        $wpdb->delete(TW_DB::table('otps'), ['serial_id' => $serial_id]);
        foreach (['events', 'events_archive'] as $n) {
            $wpdb->query($wpdb->prepare("DELETE FROM " . TW_DB::table($n) . " WHERE warranty_id = 0 AND serial_id = %d", $serial_id));
        }
    }

    /** Deletes all demos now, whatever their age (console button). @return int */
    public static function reset_all() {
        global $wpdb;
        $ids = $wpdb->get_col("SELECT id FROM " . TW_DB::table('warranties'));
        foreach ($ids as $id) self::purge((int) $id);
        foreach ($wpdb->get_col("SELECT serial_id FROM " . self::table()) as $sid) self::forget_unfinished((int) $sid);
        $wpdb->query("DELETE FROM " . self::table());
        // Serials left "registered" without a warranty (e.g. after a restore) become available again.
        $wpdb->query("UPDATE " . TW_DB::table('serials') . " s LEFT JOIN " . TW_DB::table('warranties') . " w ON w.serial_id = s.id SET s.status = 'available' WHERE s.status = 'registered' AND w.id IS NULL");
        return count($ids);
    }

    private static function unlink_rel($rel) {
        if ($rel && ($p = TW_Util::invoice_path($rel))) @unlink($p);
    }

    /** Removes one warranty and everything recorded about it; its serial becomes available again. */
    public static function purge($wid) {
        global $wpdb;
        $t = function ($n) { return TW_DB::table($n); };
        $w = $wpdb->get_row($wpdb->prepare("SELECT w.*, s.serial_no AS serial FROM {$t('warranties')} w LEFT JOIN {$t('serials')} s ON s.id = w.serial_id WHERE w.id = %d", $wid));
        if (!$w) return;

        // Uploaded files: invoice, Service receipts, claim attachments.
        self::unlink_rel($w->invoice_file);
        foreach ($wpdb->get_col($wpdb->prepare("SELECT receipt_file FROM {$t('services')} WHERE warranty_id = %d", $wid)) as $f) self::unlink_rel($f);
        foreach ($wpdb->get_col($wpdb->prepare("SELECT files FROM {$t('claims')} WHERE warranty_id = %d", $wid)) as $json) {
            foreach ((array) json_decode((string) $json, true) as $f) self::unlink_rel(is_array($f) ? ($f['file'] ?? '') : '');
        }

        foreach (['consents', 'services', 'notifications', 'claims'] as $n) {
            $wpdb->delete($t($n), ['warranty_id' => $wid]);
        }
        foreach (['events', 'events_archive'] as $n) {
            $wpdb->query($wpdb->prepare("DELETE FROM {$t($n)} WHERE warranty_id = %d OR (serial_id = %d AND serial_id <> 0)", $wid, (int) $w->serial_id));
        }
        $wpdb->delete($t('warranties'), ['id' => $wid]);
        $wpdb->query($wpdb->prepare("UPDATE {$t('serials')} SET status = 'available' WHERE id = %d AND status = 'registered'", (int) $w->serial_id));
        $wpdb->delete($t('otps'), ['serial_id' => (int) $w->serial_id]);
        $wpdb->delete(self::table(), ['serial_id' => (int) $w->serial_id]);

        // The person's own traces, unless another demo of the same email is still running: email codes and the
        // «Οι εγγυήσεις μου» sign-in, their general log entries, and the "codes per email" counter.
        $email = (string) $w->email;
        $others = $email !== '' ? (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$t('warranties')} WHERE email = %s", $email)) : 0;
        if ($email !== '' && !$others) {
            $wpdb->delete($t('otps'), ['email' => $email]);
            foreach (['events', 'events_archive'] as $n) {
                $wpdb->query($wpdb->prepare("DELETE FROM {$t($n)} WHERE warranty_id = 0 AND actor = %s", 'customer:' . $email));
            }
            TW_Guard::reset('otp_email|' . strtolower($email), '');
            // A GDPR request created from the portal, if any.
            if (function_exists('wp_get_user_request')) {
                foreach (get_posts(['post_type' => 'user_request', 'post_status' => 'any', 'title' => $email, 'numberposts' => 20, 'fields' => 'ids']) as $rid) {
                    wp_delete_post($rid, true);
                }
            }
        }
        wp_clear_scheduled_hook(self::HOOK, [(int) $wid]);
        TW_Warranty::forget_services();
        TW_DB::log_event('demo_reset', ['serial' => (string) $w->serial, 'warranty_code' => (string) $w->warranty_code], 0, 0, 'system');
    }

    // ------------------------------------------------------------------ console

    /** Pool status on the dashboard, with "reset now". */
    public static function admin_panel() {
        global $wpdb;
        $now = TW_DB::now();
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT s.id, s.serial_no, s.status, w.id AS wid, w.customer_name, w.email, w.created_at AS w_created, h.expires_at AS hold_until
             FROM " . TW_DB::table('serials') . " s
             LEFT JOIN " . TW_DB::table('warranties') . " w ON w.serial_id = s.id
             LEFT JOIN " . self::table() . " h ON h.serial_id = s.id AND h.expires_at > %s
             ORDER BY s.serial_no ASC LIMIT 100",
            $now
        ));
        $st = self::pool_stats();
        ?>
        <div class="tw-panel tw-demo-panel">
            <h2>Έκδοση επίδειξης – σειριακοί</h2>
            <p class="description">Ο σειριακός συμπληρώνεται αυτόματα στην ενεργοποίηση. Κάθε καταχώρηση διαγράφεται
                <strong><?php echo (int) self::RESET_MINUTES; ?> λεπτά</strong> μετά την ολοκλήρωσή της (με ό,τι καταχωρήθηκε: στοιχεία, αρχεία, Service,
                αιτήματα, μηνύματα, ημερολόγιο) και ο σειριακός γίνεται ξανά διαθέσιμος. Ένας σειριακός που δόθηκε σε επίδειξη που δεν ολοκληρώθηκε
                ελευθερώνεται μετά από <?php echo (int) self::HOLD_MINUTES; ?> λεπτά. Ελεύθεροι τώρα: <strong><?php echo (int) $st['free']; ?> από <?php echo (int) $st['total']; ?></strong>.</p>
            <table class="widefat striped"><thead><tr><th>S/N</th><th>Κατάσταση</th><th>Επίδειξη</th><th>Διαγραφή σε</th></tr></thead><tbody>
            <?php if (!$rows) echo '<tr><td colspan="4">Δεν υπάρχουν σειριακοί.</td></tr>'; ?>
            <?php foreach ($rows as $r) :
                if ($r->wid) {
                    $left = max(0, strtotime($r->w_created) + self::RESET_MINUTES * MINUTE_IN_SECONDS - current_time('timestamp'));
                    $state = 'Ολοκληρώθηκε'; $who = '<a href="' . esc_url(TW_Admin::url('warranties', ['view' => (int) $r->wid])) . '">' . esc_html($r->customer_name . ' · ' . $r->email) . '</a>';
                    $when = '<span class="tw-demo-left" data-left="' . (int) $left . '">' . esc_html(self::mmss($left)) . '</span>';
                } elseif ($r->status === 'void') {
                    $state = 'Ακυρωμένος'; $who = '—'; $when = '—';
                } elseif ($r->hold_until) {
                    $state = 'Σε εξέλιξη'; $who = 'Δόθηκε σε επίδειξη · ελευθερώνεται ' . esc_html(substr((string) $r->hold_until, 11, 5)) . ' αν δεν ολοκληρωθεί'; $when = '—';
                } else {
                    $state = 'Διαθέσιμος'; $who = '—'; $when = '—';
                } ?>
                <tr><td><code><?php echo esc_html($r->serial_no); ?></code></td><td><?php echo esc_html($state); ?></td><td><?php echo $who; // phpcs:ignore ?></td><td><?php echo $when; // phpcs:ignore ?></td></tr>
            <?php endforeach; ?>
            </tbody></table>
            <form method="post" action="<?php echo esc_url(TW_Admin::url('dashboard')); ?>" class="tw-inline-form" onsubmit="return confirm('Να διαγραφούν τώρα όλες οι καταχωρήσεις επίδειξης και να ελευθερωθούν όλοι οι σειριακοί;');">
                <?php wp_nonce_field('tw_demo_reset'); ?><input type="hidden" name="action" value="tw_demo_reset">
                <button class="button">Επαναφορά όλων τώρα</button>
                <span class="description">Διαγράφει αμέσως όλες τις καταχωρήσεις και ελευθερώνει όλους τους σειριακούς.</span>
            </form>
            <script>
            (function () {
                var els = document.querySelectorAll('.tw-demo-left');
                if (!els.length) return;
                var t0 = Date.now();
                setInterval(function () {
                    var gone = Math.floor((Date.now() - t0) / 1000), reload = false;
                    els.forEach(function (el) {
                        var l = Math.max(0, parseInt(el.getAttribute('data-left'), 10) - gone);
                        el.textContent = Math.floor(l / 60) + ':' + ('0' + (l % 60)).slice(-2);
                        if (l === 0) reload = true;
                    });
                    if (reload && gone > 2) setTimeout(function () { location.reload(); }, 4000);
                }, 1000);
            })();
            </script>
        </div>
        <?php
    }

    /** Ribbon shown on every page of the demo (console and customer pages). */
    public static function ribbon($context = 'public') {
        $text = $context === 'admin'
            ? sprintf('ΕΚΔΟΣΗ ΕΠΙΔΕΙΞΗΣ · Οι καταχωρήσεις διαγράφονται αυτόματα %d λεπτά μετά την ολοκλήρωση.', self::RESET_MINUTES)
            : sprintf(tw__('ΕΚΔΟΣΗ ΕΠΙΔΕΙΞΗΣ · Οι καταχωρήσεις διαγράφονται αυτόματα %d λεπτά μετά την ολοκλήρωση.'), self::RESET_MINUTES);
        return '<div class="tw-demo-ribbon" role="note">' . esc_html($text) . '</div>';
    }

    public static function ribbon_css() {
        return '<style>.tw-demo-ribbon{background:#b45309;color:#fff;text-align:center;font:600 13px/1.4 inherit;padding:7px 12px;letter-spacing:.02em}'
            . '.tw-console .tw-demo-ribbon{margin:-8px 0 18px;border-radius:8px}'
            . '.tw-demo-panel{padding:16px 18px;margin:0 0 22px;border-left:4px solid #b45309}.tw-demo-panel h2{margin-top:0}'
            . '@media print{.tw-demo-ribbon{display:none!important}}</style>';
    }
}
