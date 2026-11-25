<?php
/** @var object[] $warranties  @var string $email */
if (!defined('ABSPATH')) exit;
$labels = TWD_Util::status_labels();
$s = TWD_Settings::all();
$lang = TWD_I18n::lang();
$s['company_contact'] = TWD_Settings::company_contact($lang);
$mlabels = TWD_Settings::consent_labels($lang);
?>
<div data-portal-main>
<div class="tw-portal-head">
    <h3><?php twd_e('Οι εγγυήσεις μου'); ?></h3>
    <p><?php twd_e('Συνδεδεμένος/η ως'); ?> <strong><?php echo esc_html($email); ?></strong>
        <button type="button" class="tw-link" data-action="logout"><?php twd_e('Αποσύνδεση'); ?></button></p>
</div>
<?php /* Sales demo: one product per demo, so no «+ Καταχώρηση νέου προϊόντος». */ ?>

<?php if (!$warranties) : ?>
    <p><?php twd_e('Δεν βρέθηκαν εγγυήσεις.'); ?></p>
<?php endif; ?>

<?php foreach ($warranties as $w) :
    $timeline = TWD_Warranty::timeline($w);
    $days_left = TWD_Schedule::diff_days(TWD_Schedule::today(), $w->warranty_end);
    $status = twd__($labels[$w->status] ?? $w->status);
    ?>
    <article class="tw-card tw-card--<?php echo esc_attr($w->status); ?>">
        <header>
            <h4><?php echo esc_html($w->product_name ?: $w->product_code ?: twd__('Προϊόν')); ?></h4>
            <span class="tw-badge tw-badge--<?php echo esc_attr($w->status); ?>"><?php echo esc_html($status); ?></span>
        </header>
        <dl class="tw-dl">
            <dt><?php twd_e('Σειριακός αριθμός'); ?></dt><dd><code><?php echo esc_html($w->serial); ?></code></dd>
            <dt><?php twd_e('Κωδικός εγγύησης'); ?></dt><dd><code><?php echo esc_html($w->warranty_code); ?></code></dd>
            <dt><?php twd_e('Ημερομηνία αγοράς'); ?></dt><dd><?php echo esc_html(TWD_Util::date_label($w->purchase_date)); ?></dd>
            <dt><?php twd_e('Λήξη εγγύησης'); ?></dt><dd><?php echo esc_html(TWD_Util::date_label($w->warranty_end)); ?>
                <?php if ($w->status === 'active' && $days_left >= 0) : ?><small>(<?php echo esc_html(sprintf(twd__('σε %d ημέρες'), (int) $days_left)); ?>)</small><?php endif; ?></dd>
            <?php if ($w->status === 'locked') : ?>
                <dt><?php twd_e('Κλείδωμα'); ?></dt><dd><?php echo esc_html(TWD_Util::datetime_label($w->locked_at)); ?> – <?php echo esc_html($w->lock_reason); ?></dd>
            <?php endif; ?>
        </dl>
        <?php $tw_acc = TWD_Terms::accepted($w->id); ?>
        <p class="tw-portal-actions"><button type="button" class="tw-btn tw-btn--ghost tw-btn--sm" data-action="show-card" aria-expanded="false">🪪 <?php twd_e('Κάρτα εγγύησης'); ?></button>
            <?php if (false && $tw_acc) : // sales demo: the terms PDF link belongs to the production site ?><a class="tw-btn tw-btn--ghost tw-btn--sm" href="<?php echo esc_url(TWD_Terms_Public::portal_terms_url($w->id)); ?>" target="_blank" rel="noopener" title="<?php echo esc_attr(sprintf(twd__('Οι όροι που αποδεχθήκατε στις %s'), TWD_Util::date_label(substr($tw_acc['accepted_at'], 0, 10)))); ?>">📄 <?php twd_e('Όροι εγγύησης (PDF)'); ?></a><?php endif; ?></p>
        <?php if ($tw_acc) : ?><p class="tw-hint tw-portal-terms-hint"><?php echo esc_html(sprintf(twd__('Οι όροι εγγύησης που αποδεχθήκατε στις %s, όπως ίσχυαν για την αγορά σας.'), TWD_Util::date_label(substr($tw_acc['accepted_at'], 0, 10)))); ?></p><?php endif; ?>
        <?php $next = TWD_Warranty::next_service($w); ?>
        <div class="tw-done tw-card-view" hidden data-print-title="<?php echo esc_attr($w->warranty_code . ' - ' . $w->customer_name); ?>">
            <div class="tw-card-logo" hidden><?php echo TWD_App::logo_html('tw-card-logo-img'); // phpcs:ignore -- shown only on the printed card ?></div>
            <h3><?php twd_e('Κάρτα εγγύησης'); ?></h3>
            <p><?php twd_e('Ο κωδικός εγγύησής σας:'); ?></p>
            <p class="tw-warranty-code"><?php echo esc_html($w->warranty_code); ?></p>
            <dl class="tw-dl">
                <dt><?php twd_e('Σειριακός αριθμός'); ?></dt><dd><?php echo esc_html($w->serial); ?></dd>
                <dt><?php twd_e('Προϊόν'); ?></dt><dd><?php echo esc_html($w->product_name ?: ($w->product_code ?: '—')); ?></dd>
                <dt><?php twd_e('Λήξη εγγύησης'); ?></dt><dd><?php echo esc_html(TWD_Util::date_label($w->warranty_end)); ?></dd>
                <dt><?php twd_e('Επόμενο Service'); ?></dt><dd><?php echo esc_html($next ? TWD_Util::date_label($next['due']) : '—'); ?></dd>
                <?php if ($w->status !== 'active') : ?><dt><?php twd_e('Κατάσταση'); ?></dt><dd><?php echo esc_html($status); ?></dd><?php endif; ?>
            </dl>
            <p class="tw-done-actions"><button type="button" class="tw-btn" data-action="print-card">🖨 <?php twd_e('Εκτύπωση κάρτας εγγύησης'); ?></button>
                <button type="button" class="tw-link" data-action="hide-card"><?php twd_e('Κλείσιμο'); ?></button></p>
        </div>

        <h5><?php twd_e('Πρόγραμμα Service'); ?></h5>
        <table class="tw-table">
            <thead><tr><th><?php twd_e('Ορόσημο'); ?></th><th><?php twd_e('Ημερομηνία'); ?></th><th><?php twd_e('Κατάσταση'); ?></th></tr></thead>
            <tbody>
            <?php foreach ($timeline as $m) :
                if ($m['type'] === 'service') {
                    $label = sprintf(twd__('%1$s Service (%2$d έτη)'), TWD_I18n::ordinal($m['no']), $m['years']);
                    $sv = $m['service'];
                    $state = [
                        'done' => sprintf(twd__('Καταχωρήθηκε – Service %s'), $sv ? TWD_Util::date_label($sv->performed_date) : ''),
                        'submitted' => sprintf(twd__('Καταχωρήθηκε – Service %s'), $sv ? TWD_Util::date_label($sv->performed_date) : ''),
                        'rejected' => sprintf(twd__('Χρειάζεται νέα υποβολή έως %s'), $sv ? TWD_Util::date_label($sv->resubmit_until) : ''),
                        'due' => sprintf(twd__('Εκκρεμεί – καταχώρηση έως %s'), TWD_Util::date_label($m['lock_date'])),
                        'missed' => twd__('Δεν καταχωρήθηκε'),
                        'upcoming' => twd__('Προγραμματισμένο'),
                        'before_registration' => '—',
                    ][$m['state']];
                } else {
                    $label = twd__('Λήξη εγγύησης');
                    $state = twd__($m['state'] === 'expired' ? 'Έληξε' : 'Σε ισχύ');
                }
                ?>
                <tr class="tw-ms tw-ms--<?php echo esc_attr($m['state']); ?>"><td><?php echo esc_html($label); ?></td><td><?php echo esc_html(TWD_Util::date_label($m['due'])); ?></td><td><?php echo esc_html($state); ?></td></tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php foreach ($timeline as $m) : if (!$m['can_submit']) continue; $sv = $m['service']; ?>
            <form class="tw-service-form" data-warranty="<?php echo (int) $w->id; ?>" novalidate enctype="multipart/form-data">
                <h5><?php echo esc_html(sprintf(twd__('Υποβολή %s Service'), TWD_I18n::lang() === 'en' ? TWD_I18n::ordinal($m['no']) : $m['no'] . 'ου')); ?></h5>
                <?php if ($m['state'] === 'rejected' && $sv) : ?>
                    <p class="tw-alert tw-alert--error"><?php echo esc_html(sprintf(twd__('Η προηγούμενη υποβολή δεν εγκρίθηκε: %1$s. Υποβάλετε ξανά έως %2$s.'), $sv->review_note, TWD_Util::date_label($sv->resubmit_until))); ?></p>
                <?php else : ?>
                    <p class="tw-hint"><?php echo wp_kses(sprintf(esc_html(twd__('Μόλις γίνει το Service, καταχωρήστε το εδώ έως %s, ώστε η εγγύηση να συνεχίσει να ισχύει μέχρι το επόμενο Service.')), '<strong>' . esc_html(TWD_Util::date_label($m['lock_date'])) . '</strong>'), ['strong' => []]); ?></p>
                <?php endif; ?>
                <p class="tw-alert tw-alert--info twd-explain"><?php twd_e('Επίδειξη: κανονικά η υποβολή ανοίγει λίγο πριν από την ημερομηνία του Service. Εδώ είναι ανοιχτή από τώρα για να δείξετε τη διαδικασία.'); ?></p>
                <p class="twd-autofill"><button type="button" class="tw-btn tw-btn--ghost tw-btn--sm" data-demo-fill="service">⚡ <?php twd_e('Αυτόματη συμπλήρωση Service'); ?></button></p>
                <input type="hidden" name="milestone_no" value="<?php echo (int) $m['no']; ?>">
                <?php echo TWD_Guard::fields(); // phpcs:ignore ?>
                <div class="tw-grid">
                    <div class="tw-field">
                        <label><?php twd_e('Ημερομηνία Service'); ?> <span class="tw-req">*</span></label>
                        <input type="date" name="performed_date" required max="<?php echo esc_attr(TWD_Schedule::today()); ?>" min="<?php echo esc_attr($w->purchase_date); ?>">
                    </div>
                    <div class="tw-field">
                        <label><?php twd_e('ΑΦΜ τεχνικού'); ?> <span class="tw-req">*</span></label>
                        <?php echo TWD_Util::tax_id_field('technician_vat', 'technician_country', '', $w->partner_country ?: 'GR'); // phpcs:ignore ?>
                    </div>
                </div>
                <div class="tw-field">
                    <label><?php twd_e('Ονοματεπώνυμο / επωνυμία τεχνικού'); ?></label>
                    <input type="text" name="technician" maxlength="200">
                </div>
                <?php if (TWD_Settings::service_needs_fields()) : ?>
                <div class="tw-grid">
                    <div class="tw-field">
                        <label><?php twd_e('Αριθμός απόδειξης / παραστατικού'); ?> <span class="tw-req">*</span></label>
                        <input type="text" name="receipt_number" maxlength="100" required>
                    </div>
                    <div class="tw-field">
                        <label><?php twd_e('Ημερομηνία παραστατικού'); ?> <span class="tw-req">*</span></label>
                        <input type="date" name="receipt_date" required max="<?php echo esc_attr(TWD_Schedule::today()); ?>">
                    </div>
                </div>
                <?php endif; ?>
                <?php if (TWD_Settings::service_needs_file()) : ?>
                <div class="tw-field">
                    <label><?php echo esc_html(sprintf(twd__('Απόδειξη Service (PDF, JPG ή PNG, έως %d MB)'), (int) $s['invoice_max_mb'])); ?> <span class="tw-req">*</span></label>
                    <input type="file" name="receipt_file" accept=".pdf,.jpg,.jpeg,.png,application/pdf,image/jpeg,image/png" required>
                </div>
                <?php endif; ?>
                <div class="tw-field">
                    <label><?php twd_e('Σημειώσεις'); ?></label>
                    <input type="text" name="notes" maxlength="500">
                </div>
                <label class="tw-check"><input type="checkbox" name="genuine_parts" value="1" required> <span><?php echo esc_html(sprintf(twd__('Βεβαιώνω ότι το Service έγινε με αυθεντικά ανταλλακτικά %s.'), $s['company_name'])); ?> <span class="tw-req">*</span></span></label>
                <button type="submit" class="tw-btn"><?php twd_e('Υποβολή Service'); ?></button>
            </form>
        <?php endforeach; ?>

        <p class="tw-hint"><?php echo esc_html($w->partner_email
            ? sprintf(twd__('Για το Service απευθυνθείτε στον συνεργάτη από τον οποίο αγοράσατε το προϊόν (%1$s) ή απευθείας στη %2$s: %3$s. Το Service πρέπει να γίνεται με αυθεντικά ανταλλακτικά.'), $w->partner_email, $s['company_name'], $s['company_contact'])
            : sprintf(twd__('Για το Service απευθυνθείτε στον συνεργάτη από τον οποίο αγοράσατε το προϊόν ή απευθείας στη %1$s: %2$s. Το Service πρέπει να γίνεται με αυθεντικά ανταλλακτικά.'), $s['company_name'], $s['company_contact'])); ?></p>

        <?php echo TWD_Claims::portal_block($w); // phpcs:ignore -- escaped inside ?>

        <details class="tw-details">
            <summary><?php twd_e('Στοιχεία καταχώρησης'); ?></summary>
            <dl class="tw-dl">
                <dt><?php twd_e('Ονοματεπώνυμο'); ?></dt><dd><?php echo esc_html($w->customer_name); ?></dd>
                <dt><?php twd_e('Κινητό'); ?></dt><dd><?php echo esc_html($w->phone_mobile); ?></dd>
                <?php if ($w->phone_landline) : ?><dt><?php twd_e('Σταθερό'); ?></dt><dd><?php echo esc_html($w->phone_landline); ?></dd><?php endif; ?>
                <dt><?php twd_e('Διεύθυνση'); ?></dt><dd><?php echo esc_html(trim($w->address . ', ' . $w->postcode . ' ' . $w->city, ', ')); ?></dd>
                <dt><?php twd_e('Συνεργάτης'); ?></dt><dd><?php echo esc_html(TWD_Util::tax_id_text($w->partner_country, $w->partner_vat)); ?></dd>
                <dt><?php twd_e('Παραστατικό'); ?></dt><dd><?php echo esc_html($w->invoice_number); ?></dd>
                <dt><?php twd_e('Ενεργοποίηση'); ?></dt><dd><?php echo esc_html(TWD_Util::datetime_label($w->created_at)); ?></dd>
            </dl>
            <p class="tw-hint"><?php echo esc_html(sprintf(twd__('Για διόρθωση στοιχείων επικοινωνήστε μαζί μας: %s.'), $s['company_contact'])); ?></p>
        </details>
    </article>
<?php endforeach; ?>

<?php
$mk = TWD_Warranty::marketing_status($email);
$terms_rows = [];
foreach ($warranties as $w) {
    $c = $GLOBALS['wpdb']->get_row($GLOBALS['wpdb']->prepare("SELECT * FROM " . TWD_DB::table('consents') . " WHERE warranty_id = %d AND consent_type = 'terms' ORDER BY id DESC LIMIT 1", $w->id));
    if ($c) $terms_rows[] = $c;
}
$cust_lang = TWD_Warranty::customer_lang($email);
?>
<section class="tw-card tw-privacy-box" id="tw-my-data">
    <h4><?php twd_e('Τα δεδομένα μου'); ?></h4>

    <div class="tw-consents">
        <h5><?php twd_e('Οι άδειές μου'); ?></h5>
        <div class="tw-consent-row">
            <div><strong><?php twd_e('Όροι εγγύησης'); ?></strong><br><span class="tw-hint"><?php echo esc_html($terms_rows
                ? sprintf(twd__('Απαραίτητοι για την εγγύηση. Τους αποδεχθήκατε κατά την ενεργοποίηση (τελευταία φορά %s).'), TWD_Util::date_label(substr(max(array_map(function ($c) { return $c->created_at; }, $terms_rows)), 0, 10)))
                : twd__('Απαραίτητοι για την εγγύηση. Τους αποδεχθήκατε κατά την ενεργοποίηση.')); ?></span></div>
            <span class="tw-consent-state tw-consent-state--fixed"><?php twd_e('Απαραίτητο'); ?></span>
        </div>
        <div class="tw-consent-row">
            <div><strong><?php twd_e('Μηνύματα για Service και εγγύηση'); ?></strong><br><span class="tw-hint"><?php twd_e('Υπενθυμίσεις Service, λήξης και επιβεβαιώσεις. Στέλνονται πάντα, γιατί αφορούν την εγγύηση που έχετε.'); ?></span></div>
            <span class="tw-consent-state tw-consent-state--fixed"><?php twd_e('Απαραίτητο'); ?></span>
        </div>
        <form class="tw-consent-row tw-consent-marketing" data-action="marketing-form" novalidate>
            <div>
                <strong><?php twd_e('Ενημερώσεις και προσφορές'); ?></strong> <span class="tw-hint">(<?php twd_e('προαιρετικό'); ?>)</span><br>
                <span class="tw-hint"><?php echo esc_html($mlabels['marketing']); ?> <?php twd_e('Ισχύει για όλα τα προϊόντα σας'); ?><?php echo $mk['changed_at'] ? ' · ' . esc_html(sprintf(twd__('τελευταία αλλαγή %s'), TWD_Util::datetime_label($mk['changed_at']))) : ''; ?>.</span>
                <?php if ($mk['state'] === 'mixed') : ?><p class="tw-alert tw-alert--warn"><?php twd_e('Μέχρι σήμερα η επιλογή σας διέφερε ανά προϊόν. Επιλέξτε και αποθηκεύστε, για να ισχύει μία επιλογή για όλα.'); ?></p><?php endif; ?>
                <div class="tw-choice" role="radiogroup" aria-label="<?php echo esc_attr(twd__('Ενημερώσεις και προσφορές')); ?>">
                    <label><input type="radio" name="marketing" value="1" <?php checked($mk['state'], 'yes'); ?>> <?php twd_e('Ναι, θέλω να λαμβάνω'); ?></label>
                    <label><input type="radio" name="marketing" value="0" <?php checked($mk['state'], 'no'); ?>> <?php twd_e('Όχι'); ?></label>
                </div>
            </div>
            <div class="tw-consent-side">
                <span class="tw-consent-state tw-consent-state--<?php echo esc_attr($mk['state']); ?>"><?php echo esc_html(twd__(['yes' => 'Ενεργή', 'no' => 'Ανενεργή', 'mixed' => 'Μερική'][$mk['state']])); ?></span>
                <button type="submit" class="tw-btn" disabled><?php twd_e('Αποθήκευση επιλογής'); ?></button>
            </div>
        </form>
        <form class="tw-consent-row tw-consent-lang" data-action="lang-form" novalidate>
            <div>
                <strong><?php twd_e('Γλώσσα επικοινωνίας'); ?></strong><br>
                <span class="tw-hint"><?php twd_e('Η γλώσσα των email και των SMS που σας στέλνουμε, για όλα τα προϊόντα σας.'); ?></span>
                <div class="tw-choice" role="radiogroup" aria-label="<?php echo esc_attr(twd__('Γλώσσα επικοινωνίας')); ?>">
                    <?php foreach (TWD_I18n::languages() as $code => $name) : ?>
                        <label lang="<?php echo esc_attr($code); ?>"><input type="radio" name="comm_lang" value="<?php echo esc_attr($code); ?>" <?php checked($cust_lang, $code); ?>> <?php echo esc_html($name); ?></label>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="tw-consent-side">
                <button type="submit" class="tw-btn" disabled><?php twd_e('Αποθήκευση επιλογής'); ?></button>
            </div>
        </form>
    </div>

    <p><?php twd_e('Δείτε, εκτυπώστε ή αποθηκεύστε ως PDF όλα τα στοιχεία που έχουμε για εσάς και τις εγγυήσεις σας. Χρησιμεύει και ως βεβαίωση της εγγύησης.'); ?></p>
    <a class="tw-btn" href="<?php echo esc_url(add_query_arg(['copy' => '1', 'lang' => $lang], TWD_App::public_url('my'))); ?>" target="_blank" rel="noopener">📄 <?php twd_e('Αντίγραφο των στοιχείων μου'); ?></a>
    <button type="button" class="tw-btn tw-btn--ghost" data-action="erase-view"><?php twd_e('Διαγραφή των στοιχείων μου'); ?></button>
    <p class="tw-hint"><button type="button" class="tw-link" data-action="export"><?php twd_e('Αντίγραφο σε μορφή για μεταφορά σε άλλο σύστημα (JSON)'); ?></button></p>
</section>
</div>

<?php // «Διαγραφή των στοιχείων μου»: its own page inside «Οι εγγυήσεις μου» (explanation → tick → emailed code → deletion). ?>
<div class="tw-erase" data-portal-erase hidden>
    <p><button type="button" class="tw-link" data-action="erase-back">← <?php twd_e('Πίσω στις εγγυήσεις μου'); ?></button></p>
    <section class="tw-card tw-erase-card">
        <h3><?php twd_e('Διαγραφή των στοιχείων μου'); ?></h3>
        <div class="tw-erase-text"><?php echo TWD_Frontend::erase_html($lang); // escaped inside ?></div>
        <?php if ($warranties) : ?>
            <p class="tw-hint"><?php echo esc_html(sprintf(twd__('Θα διαγραφούν τα στοιχεία για: %s'), implode(', ', array_map(function ($w) { return trim(($w->product_name ?: twd__('Προϊόν')) . ' (S/N ' . $w->serial . ')'); }, $warranties)))); ?></p>
        <?php endif; ?>
        <form class="tw-step" data-step="erase-1" novalidate>
            <label class="tw-check tw-erase-check"><input type="checkbox" name="confirm" value="1"> <span><?php twd_e('Επιθυμώ τη διαγραφή των στοιχείων μου'); ?></span></label>
            <button type="submit" class="tw-btn tw-btn--danger" disabled><?php twd_e('Υποβολή'); ?></button>
        </form>
        <form class="tw-step" data-step="erase-2" novalidate hidden>
            <p class="tw-step-info"></p>
            <div class="tw-field">
                <label for="tw-erase-code"><?php twd_e('Κωδικός επιβεβαίωσης'); ?></label>
                <input id="tw-erase-code" name="code" type="text" inputmode="numeric" autocomplete="one-time-code" pattern="\d{6}" maxlength="6" required class="tw-code-input">
            </div>
            <button type="submit" class="tw-btn tw-btn--danger"><?php twd_e('Οριστική διαγραφή'); ?></button>
            <button type="button" class="tw-link" data-action="erase-resend"><?php twd_e('Αποστολή νέου κωδικού'); ?></button>
        </form>
        <div class="tw-erase-done" hidden><p class="tw-alert tw-alert--success" role="status"></p></div>
    </section>
</div>
