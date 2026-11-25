<?php
/**
 * Human-readable copy of everything held about the signed-in customer (GDPR right of access),
 * also usable as a warranty certificate. Printable / save as PDF from the browser.
 *
 * @var array $s  @var string $email  @var object[] $warranties
 */
if (!defined('ABSPATH')) exit;
global $wpdb;
$labels = TWD_Util::status_labels();
$lang = TWD_I18n::lang();
$s['company_contact'] = TWD_Settings::company_contact($lang);
$privacy = TWD_Settings::page_url('privacy');
$consent_labels = array_map('twd__', ['terms' => 'Αποδοχή όρων εγγύησης', 'data_accuracy' => 'Επιβεβαίωση ορθότητας στοιχείων', 'marketing' => 'Ενημερώσεις και προσφορές (email/SMS)']);
$source_labels = array_map('twd__', ['registration' => 'κατά την ενεργοποίηση', 'portal' => 'από «Οι εγγυήσεις μου»', 'erasure_request' => 'με αίτημα διαγραφής', 'inherited' => 'κατά την ενεργοποίηση, από την ήδη ισχύουσα συγκατάθεσή σας']);
$channel_labels = array_map('twd__', ['email' => 'Email', 'sms' => 'SMS', 'partner' => 'Email συνεργάτη']);
$review_labels = array_map('twd__', ['pending' => 'Καταχωρήθηκε', 'approved' => 'Καταχωρήθηκε – ελέγχθηκε από την εταιρεία', 'rejected' => 'Απορρίφθηκε']);
?><!doctype html>
<html lang="<?php echo esc_attr($lang); ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <?php echo TWD_App::favicon_tag(); // phpcs:ignore ?>
    <title><?php twd_e('Αντίγραφο στοιχείων'); ?> – <?php echo esc_html($s['company_name']); ?></title>
    <link rel="stylesheet" href="<?php echo esc_url(TWD_App::asset('assets/css/fonts.css')); ?>">
    <link rel="stylesheet" href="<?php echo esc_url(TWD_App::asset('assets/css/app-copy.css')); ?>">
    <?php echo TWD_App::brand_style(); // phpcs:ignore ?>
</head>
<body class="tw-copy">
<div class="tw-copy-bar">
    <button type="button" onclick="window.print()">🖨 <?php twd_e('Εκτύπωση / Αποθήκευση ως PDF'); ?></button>
    <a href="<?php echo esc_url(TWD_I18n::url(TWD_App::public_url('my'))); ?>">← <?php twd_e('Οι εγγυήσεις μου'); ?></a>
</div>
<main class="tw-copy-page">
    <header class="tw-copy-head">
        <div><?php echo TWD_App::logo_html('tw-copy-logo'); // phpcs:ignore ?></div>
        <div class="tw-copy-meta">
            <h1><?php twd_e('Αντίγραφο στοιχείων εγγύησης'); ?></h1>
            <p><?php twd_e('Για:'); ?> <strong><?php echo esc_html($email); ?></strong><br><?php twd_e('Ημερομηνία:'); ?> <?php echo esc_html(TWD_Util::datetime_label(TWD_DB::now())); ?></p>
        </div>
    </header>

    <p class="tw-copy-intro"><?php echo esc_html(sprintf(twd__('Το έγγραφο περιέχει όλα τα στοιχεία που διατηρεί η %s για τις εγγυήσεις των προϊόντων σας, όπως καταχωρήθηκαν στην πλατφόρμα εγγυήσεων. Μπορείτε να το φυλάξετε ως βεβαίωση της εγγύησης.'), $s['company_name'])); ?></p>

    <?php if (!$warranties) : ?><p><?php twd_e('Δεν βρέθηκαν εγγυήσεις.'); ?></p><?php endif; ?>

    <?php foreach ($warranties as $i => $w) :
        $timeline = TWD_Warranty::timeline($w);
        $history = TWD_Warranty::services_history($w->id);
        $consents = $wpdb->get_results($wpdb->prepare("SELECT * FROM " . TWD_DB::table('consents') . " WHERE warranty_id = %d ORDER BY id", $w->id));
        $sent = $wpdb->get_results($wpdb->prepare("SELECT * FROM " . TWD_DB::table('notifications') . " WHERE warranty_id = %d AND status = 'sent' AND channel <> 'partner' ORDER BY id", $w->id));
        ?>
        <section class="tw-copy-warranty">
            <h2><?php echo (int) ($i + 1); ?>. <?php echo esc_html($w->product_name ?: $w->product_code ?: twd__('Προϊόν')); ?>
                <span class="tw-copy-status tw-copy-status--<?php echo esc_attr($w->status); ?>"><?php echo esc_html(twd__($labels[$w->status] ?? $w->status)); ?></span></h2>

            <h3><?php twd_e('Εγγύηση'); ?></h3>
            <table class="tw-copy-kv">
                <tr><th><?php twd_e('Κωδικός εγγύησης'); ?></th><td><strong><?php echo esc_html($w->warranty_code); ?></strong></td></tr>
                <tr><th><?php twd_e('Σειριακός αριθμός'); ?></th><td><?php echo esc_html($w->serial); ?></td></tr>
                <tr><th><?php twd_e('Προϊόν'); ?></th><td><?php echo esc_html(trim($w->product_name . ($w->product_code ? ' (' . $w->product_code . ')' : ''))); ?></td></tr>
                <tr><th><?php twd_e('Ημερομηνία αγοράς'); ?></th><td><?php echo esc_html(TWD_Util::date_label($w->purchase_date)); ?></td></tr>
                <tr><th><?php twd_e('Αριθμός παραστατικού αγοράς'); ?></th><td><?php echo esc_html($w->invoice_number); ?><?php echo $w->invoice_file ? ' (' . esc_html(twd__('έχει ανέβει αρχείο')) . ')' : ''; ?></td></tr>
                <tr><th><?php twd_e('Λήξη εγγύησης'); ?></th><td><?php echo esc_html(TWD_Util::date_label($w->warranty_end)); ?></td></tr>
                <tr><th><?php twd_e('Ενεργοποίηση'); ?></th><td><?php echo esc_html(TWD_Util::datetime_label($w->created_at)); ?></td></tr>
                <?php if ($w->status === 'locked') : ?><tr><th><?php twd_e('Κλείδωμα'); ?></th><td><?php echo esc_html(TWD_Util::datetime_label($w->locked_at) . ' – ' . $w->lock_reason); ?></td></tr><?php endif; ?>
            </table>

            <h3><?php twd_e('Στοιχεία κατόχου'); ?></h3>
            <table class="tw-copy-kv">
                <tr><th><?php twd_e('Ονοματεπώνυμο'); ?></th><td><?php echo esc_html($w->customer_name); ?></td></tr>
                <tr><th>Email</th><td><?php echo esc_html($w->email); ?></td></tr>
                <tr><th><?php twd_e('Κινητό τηλέφωνο'); ?></th><td><?php echo esc_html($w->phone_mobile); ?></td></tr>
                <tr><th><?php twd_e('Σταθερό τηλέφωνο'); ?></th><td><?php echo esc_html($w->phone_landline ?: '—'); ?></td></tr>
                <tr><th><?php twd_e('Διεύθυνση'); ?></th><td><?php echo esc_html(trim($w->address . ', ' . $w->postcode . ' ' . $w->city, ', ')); ?></td></tr>
                <tr><th><?php twd_e('Συνεργάτης (πωλητής)'); ?></th><td><?php echo esc_html(TWD_Util::tax_id_text($w->partner_country, $w->partner_vat)); ?> – <?php echo esc_html($w->partner_email); ?></td></tr>
            </table>

            <h3><?php twd_e('Πρόγραμμα Service'); ?></h3>
            <table class="tw-copy-table">
                <thead><tr><th><?php twd_e('Ορόσημο'); ?></th><th><?php twd_e('Ημερομηνία'); ?></th><th><?php twd_e('Κατάσταση'); ?></th></tr></thead>
                <tbody>
                <?php foreach ($timeline as $m) :
                    if ($m['type'] === 'service') {
                        $label = sprintf(twd__('%1$s Service (%2$d έτη)'), TWD_I18n::ordinal($m['no']), $m['years']);
                        $state = ['done' => twd__('Καταχωρήθηκε'), 'submitted' => twd__('Καταχωρήθηκε'), 'rejected' => twd__('Χρειάζεται νέα καταχώρηση'), 'due' => sprintf(twd__('Εκκρεμεί – καταχώρηση έως %s'), TWD_Util::date_label($m['lock_date'])),
                            'missed' => twd__('Δεν καταχωρήθηκε'), 'upcoming' => twd__('Προγραμματισμένο'), 'before_registration' => '—'][$m['state']];
                    } else {
                        $label = twd__('Λήξη εγγύησης');
                        $state = twd__($m['state'] === 'expired' ? 'Έληξε' : 'Σε ισχύ');
                    } ?>
                    <tr><td><?php echo esc_html($label); ?></td><td><?php echo esc_html(TWD_Util::date_label($m['due'])); ?></td><td><?php echo esc_html($state); ?></td></tr>
                <?php endforeach; ?>
                </tbody>
            </table>

            <h3><?php twd_e('Καταχωρήσεις Service'); ?></h3>
            <?php if (!$history) : ?><p class="tw-copy-muted"><?php twd_e('Καμία καταχώρηση.'); ?></p><?php else : ?>
            <table class="tw-copy-table">
                <thead><tr><th>Service</th><th><?php twd_e('Ημ/νία Service'); ?></th><th><?php twd_e('Τεχνικός'); ?></th><th><?php twd_e('Απόδειξη'); ?></th><th><?php twd_e('Καταχώρηση'); ?></th><th><?php twd_e('Κατάσταση'); ?></th></tr></thead>
                <tbody>
                <?php foreach (array_reverse($history) as $sv) : ?>
                    <tr><td><?php echo esc_html(TWD_I18n::ordinal($sv->milestone_no)); ?></td><td><?php echo esc_html(TWD_Util::date_label($sv->performed_date)); ?></td>
                        <td><?php echo esc_html(trim(($sv->technician_vat ? TWD_Util::tax_id_text($sv->technician_country, $sv->technician_vat) : '') . ($sv->technician ? ' – ' . $sv->technician : ''), ' –') ?: '—'); ?></td>
                        <td><?php echo esc_html(TWD_Warranty::receipt_text($sv)); ?></td>
                        <td><?php echo esc_html(TWD_Util::datetime_label($sv->created_at)); ?> (<?php echo esc_html(twd__($sv->source === 'customer' ? 'από εσάς' : 'από την εταιρεία')); ?>)</td>
                        <td><?php echo esc_html($review_labels[$sv->review_status] ?? $sv->review_status); ?><?php echo $sv->review_status === 'rejected' && $sv->review_note ? ': ' . esc_html($sv->review_note) : ''; ?></td></tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>

            <h3><?php twd_e('Συγκαταθέσεις'); ?></h3>
            <table class="tw-copy-table">
                <thead><tr><th><?php twd_e('Ημ/νία'); ?></th><th><?php twd_e('Θέμα'); ?></th><th><?php twd_e('Απόφαση'); ?></th><th><?php twd_e('Πώς'); ?></th></tr></thead>
                <tbody>
                <?php foreach ($consents as $c) : ?>
                    <tr><td><?php echo esc_html(TWD_Util::datetime_label($c->created_at)); ?></td>
                        <td><?php echo esc_html($consent_labels[$c->consent_type] ?? $c->consent_type); ?><?php echo $c->terms_version ? ' – ' . esc_html(TWD_Terms::period_label($c->terms_set ?: TWD_Terms::BASE)) : ''; ?></td>
                        <td><?php twd_e($c->granted ? 'Ναι' : 'Όχι'); ?></td>
                        <td><?php echo esc_html($source_labels[$c->source] ?? $c->source); ?><?php echo $c->ip ? ', IP ' . esc_html($c->ip) : ''; ?></td></tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <p class="tw-copy-muted"><?php twd_e('Σήμερα: ενημερώσεις και προσφορές'); ?> <strong><?php twd_e($w->marketing_opt_in ? 'ΝΑΙ' : 'ΟΧΙ'); ?></strong>. <?php twd_e('Τα μηνύματα για το Service και την εγγύηση αποστέλλονται ανεξάρτητα από αυτή την επιλογή.'); ?></p>

            <h3><?php twd_e('Μηνύματα που σας στάλθηκαν'); ?></h3>
            <?php if (!$sent) : ?><p class="tw-copy-muted"><?php twd_e('Κανένα μήνυμα ακόμη.'); ?></p><?php else : ?>
            <table class="tw-copy-table">
                <thead><tr><th><?php twd_e('Ημ/νία'); ?></th><th><?php twd_e('Θέμα'); ?></th><th><?php twd_e('Μέσο'); ?></th></tr></thead>
                <tbody>
                <?php foreach ($sent as $n) : ?>
                    <tr><td><?php echo esc_html(TWD_Util::datetime_label($n->created_at)); ?></td><td><?php echo esc_html(TWD_Cron::row_label($n, true)); ?></td><td><?php echo esc_html($channel_labels[$n->channel] ?? $n->channel); ?><?php echo esc_html($n->delivery_status === 'delivered' ? ' – ' . twd__('παραδόθηκε') : (in_array($n->delivery_status, ['failed', 'complaint'], true) ? ' – ' . twd__('δεν παραδόθηκε') : '')); ?></td></tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </section>
    <?php endforeach; ?>

    <section class="tw-copy-info">
        <h2><?php twd_e('Πώς χρησιμοποιούμε τα στοιχεία σας'); ?></h2>
        <ul>
            <li><strong><?php twd_e('Σκοπός:'); ?></strong> <?php twd_e('η ενεργοποίηση και η εξυπηρέτηση της εγγύησης και οι υπενθυμίσεις για το Service και τη λήξη της. Ενημερώσεις και προσφορές στέλνουμε μόνο αν έχετε συμφωνήσει.'); ?></li>
            <li><strong><?php twd_e('Διάρκεια:'); ?></strong> <?php twd_e('για όσο ισχύει η εγγύηση και για το διάστημα που απαιτεί η νομοθεσία· στη συνέχεια τα στοιχεία ανωνυμοποιούνται.'); ?></li>
            <li><strong><?php twd_e('Αποδέκτες:'); ?></strong> <?php echo esc_html(sprintf(twd__('η %s και οι πάροχοι που στέλνουν τα email/SMS για λογαριασμό της.'), $s['company_name'])); ?></li>
            <li><strong><?php twd_e('Διόρθωση στοιχείων:'); ?></strong> <?php echo esc_html(sprintf(twd__('επικοινωνήστε μαζί μας: %s.'), $s['company_contact'])); ?></li>
            <li><strong><?php twd_e('Διαγραφή:'); ?></strong> <?php twd_e('από «Οι εγγυήσεις μου» → «Αίτημα διαγραφής». Τα στοιχεία ενεργών εγγυήσεων διατηρούνται όσο διαρκεί η εγγύηση, ώστε να μπορεί να εξυπηρετηθεί.'); ?></li>
            <?php if ($privacy) : ?><li><strong><?php twd_e('Πολιτική απορρήτου:'); ?></strong> <?php echo esc_html($privacy); ?></li><?php endif; ?>
        </ul>
    </section>
    <footer class="tw-copy-foot"><?php echo esc_html($s['company_name'] . ' – ' . $s['company_contact']); ?></footer>
</main>
</body>
</html>
