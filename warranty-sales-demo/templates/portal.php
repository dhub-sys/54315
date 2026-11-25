<?php
if (!defined('ABSPATH')) exit;
$register_url = TWD_Settings::page_url('register');
?>
<div class="tw-app tw-portal" data-tw="portal" lang="<?php echo esc_attr(TWD_I18n::lang()); ?>">
    <noscript><p class="tw-alert tw-alert--error"><?php twd_e('Για την πύλη εγγυήσεων απαιτείται JavaScript.'); ?></p></noscript>
    <div class="tw-alert" role="alert" hidden></div>
    <p class="tw-loading" data-portal="loading"><?php twd_e('Φόρτωση…'); ?></p>
    <?php // Sign-in timer: fixed at the corner of the screen, always visible while signed in. ?>
    <div class="tw-session-pill" data-portal-timer hidden role="status" aria-live="off">
        <span class="tw-session-ico" aria-hidden="true">⏱</span>
        <span><?php twd_e('Σύνδεση:'); ?> <strong data-left>–</strong></span>
        <button type="button" data-action="session-refresh" title="<?php echo esc_attr(twd__('Ανανέωση σύνδεσης')); ?>" aria-label="<?php echo esc_attr(twd__('Ανανέωση σύνδεσης')); ?>">↻</button>
    </div>

    <div data-portal="login" hidden>
        <form class="tw-step" data-step="login-1" novalidate>
            <?php echo TWD_Guard::fields(); // phpcs:ignore ?>
            <h3><?php twd_e('Οι εγγυήσεις μου'); ?></h3>
            <p><?php twd_e('Συνδεθείτε με το email που δηλώσατε κατά την ενεργοποίηση. Θα σας στείλουμε έναν κωδικό μίας χρήσης, χωρίς κωδικό πρόσβασης.'); ?></p>
            <div class="tw-field">
                <label for="tw-login-email"><?php twd_e('Email'); ?></label>
                <input id="tw-login-email" name="email" type="email" autocomplete="email" required maxlength="190" value="<?php echo esc_attr(TWD_Demo::demo_email()); ?>">
            </div>
            <?php echo TWD_Guard::widget('public', 'portal_login'); // phpcs:ignore ?>
            <button type="submit" class="tw-btn"><?php twd_e('Αποστολή κωδικού'); ?></button>
            <?php if ($register_url) : ?><p class="tw-hint"><?php twd_e('Δεν έχετε ενεργοποιήσει ακόμη την εγγύηση;'); ?> <a href="<?php echo esc_url($register_url); ?>"><?php twd_e('Ενεργοποίηση εγγύησης'); ?></a></p><?php endif; ?>
        </form>
        <form class="tw-step" data-step="login-2" novalidate hidden>
            <?php echo TWD_Guard::fields(); // phpcs:ignore ?>
            <h3><?php twd_e('Κωδικός σύνδεσης'); ?></h3>
            <p class="tw-step-info"></p>
            <div class="tw-field">
                <label for="tw-login-code"><?php twd_e('Κωδικός'); ?></label>
                <input id="tw-login-code" name="code" type="text" inputmode="numeric" autocomplete="one-time-code" pattern="\d{6}" maxlength="6" required class="tw-code-input">
            </div>
            <button type="submit" class="tw-btn"><?php twd_e('Σύνδεση'); ?></button>
            <button type="button" class="tw-link" data-action="login-restart"><?php twd_e('Αλλαγή email'); ?></button>
        </form>
    </div>

    <div data-portal="content" hidden></div>
</div>
