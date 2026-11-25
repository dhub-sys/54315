<?php
/**
 * Sales demo page (shortcode [warranty_sales_demo] on a password-protected page).
 * @var object|null $session  @var array $stats  @var int $minutes  @var int $page_id  @var string $nonce
 */
if (!defined('ABSPATH')) exit;
?>
<div class="twd-demo" data-page="<?php echo (int) $page_id; ?>" data-nonce="<?php echo esc_attr($nonce); ?>">
<?php if (!$session) : ?>
    <div class="tw-app twd-start">
        <p class="twd-kicker"><?php twd_e('Επίδειξη για πωλητές'); ?></p>
        <h3><?php echo esc_html(sprintf(twd__('Ενεργοποίηση και διαχείριση εγγύησης – %s'), TWD_Settings::get('company_name'))); ?></h3>
        <p><?php echo esc_html(sprintf(twd__('Η επίδειξη ακολουθεί ακριβώς τη διαδρομή του πελάτη και διαρκεί %d λεπτά. Στη λήξη της διαγράφονται όλα όσα καταχωρήθηκαν και ο σειριακός είναι ξανά διαθέσιμος.'), $minutes)); ?></p>
        <ol class="twd-steps">
            <li><?php twd_e('Σειριακός αριθμός: δίνεται αυτόματα ένας από τους σειριακούς επίδειξης.'); ?></li>
            <li><?php twd_e('Email και κωδικός επαλήθευσης (OTP): εμφανίζεται με επεξηγήσεις και συμπληρώνεται αυτόματα.'); ?></li>
            <li><?php twd_e('Στοιχεία κατόχου και αγοράς: με «Αυτόματη συμπλήρωση» ή με το χέρι.'); ?></li>
            <li><?php twd_e('Όροι εγγύησης, αποδοχές και έκδοση κωδικού εγγύησης.'); ?></li>
            <li><?php twd_e('«Οι εγγυήσεις μου»: είσοδος, πρόγραμμα Service και υποβολή Service.'); ?></li>
        </ol>
        <p class="twd-pool"><?php echo esc_html(sprintf(twd__('Ελεύθεροι σειριακοί επίδειξης: %1$d από %2$d'), $stats['free'], $stats['total'])); ?></p>
        <div class="tw-alert" role="alert" hidden></div>
        <button type="button" class="tw-btn" data-demo-start>▶ <?php twd_e('Έναρξη επίδειξης'); ?></button>
        <p class="tw-hint"><?php twd_e('Δεν στέλνεται κανένα email ή SMS. Τα δεδομένα της επίδειξης δεν εμφανίζονται ποτέ στην κανονική πλατφόρμα.'); ?></p>
    </div>
<?php else : ?>
    <div class="twd-bar" role="status">
        <span class="twd-bar-tag"><?php twd_e('ΕΠΙΔΕΙΞΗ'); ?></span>
        <span>S/N <code><?php echo esc_html($session->serial); ?></code></span>
        <span class="twd-bar-time">⏱ <?php twd_e('Λήγει σε'); ?> <strong data-demo-left data-left="<?php echo (int) TWD_Demo::seconds_left($session); ?>"><?php echo esc_html(TWD_Demo::mmss(TWD_Demo::seconds_left($session))); ?></strong></span>
        <button type="button" class="twd-bar-end" data-demo-end><?php twd_e('Τερματισμός'); ?></button>
    </div>
    <nav class="twd-tabs" role="tablist">
        <button type="button" role="tab" data-tab="register" class="is-active"><?php twd_e('Ενεργοποίηση εγγύησης'); ?></button>
        <button type="button" role="tab" data-tab="my"><?php twd_e('Οι εγγυήσεις μου'); ?></button>
    </nav>
    <div data-tab-panel="register">
        <?php $s = TWD_Settings::all(); $privacy = TWD_Settings::page_url('privacy'); include TWD_DIR . 'templates/register.php'; ?>
    </div>
    <div data-tab-panel="my" hidden>
        <?php include TWD_DIR . 'templates/portal.php'; ?>
    </div>
<?php endif; ?>
</div>
