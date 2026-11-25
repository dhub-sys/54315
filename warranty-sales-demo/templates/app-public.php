<?php
/** @var string $screen  @var array $s */
if (!defined('ABSPATH')) exit;
$lang = TWD_I18n::lang();
$s['company_contact'] = TWD_Settings::company_contact($lang);
$privacy = TWD_Settings::page_url('privacy');
$config = [
    'ajax' => admin_url('admin-ajax.php'),
    'maxMb' => (int) $s['invoice_max_mb'],
    'invoiceMode' => TWD_Settings::invoice_mode(),
    'turnstile' => TWD_Guard::on('public') ? TWD_Guard::site_key() : '',
    'lang' => $lang,
    'i18n' => TWD_I18n::js(),
];
$here = TWD_App::public_url($screen === 'my' ? 'my' : '');
?><!doctype html>
<html lang="<?php echo esc_attr($lang); ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <?php echo TWD_App::favicon_tag(); // phpcs:ignore ?>
    <title><?php echo esc_html(twd__($screen === 'my' ? 'Οι εγγυήσεις μου' : 'Ενεργοποίηση εγγύησης') . ' – ' . $s['company_name']); ?></title>
    <link rel="stylesheet" href="<?php echo esc_url(TWD_App::asset('assets/css/fonts.css')); ?>">
    <link rel="stylesheet" href="<?php echo esc_url(TWD_App::asset('assets/css/frontend.css')); ?>">
    <link rel="stylesheet" href="<?php echo esc_url(TWD_App::asset('assets/css/app-public.css')); ?>">
    <?php echo TWD_App::brand_style(); // phpcs:ignore ?>
</head>
<body class="tw-public tw-public--<?php echo esc_attr($screen); ?>">
<header class="tw-pub-head">
    <div class="tw-pub-inner">
        <a class="tw-pub-brand" href="<?php echo esc_url(TWD_I18n::url(home_url('/'))); ?>"><?php echo TWD_App::logo_html('tw-pub-logo'); // phpcs:ignore ?></a>
        <nav class="tw-pub-nav" aria-label="<?php echo esc_attr(twd__('Εγγυήσεις')); ?>">
            <a href="<?php echo esc_url(TWD_I18n::url(TWD_App::public_url())); ?>" class="<?php echo $screen === 'register' ? 'is-active' : ''; ?>"><?php twd_e('Ενεργοποίηση εγγύησης'); ?></a>
            <a href="<?php echo esc_url(TWD_I18n::url(TWD_App::public_url('my'))); ?>" class="<?php echo $screen === 'my' ? 'is-active' : ''; ?>"><?php twd_e('Οι εγγυήσεις μου'); ?></a>
            <span class="tw-lang-switch" role="group" aria-label="Language">
                <?php foreach (['el' => 'EL', 'en' => 'EN'] as $code => $short) : ?>
                    <a href="<?php echo esc_url(add_query_arg('lang', $code, $here)); ?>" hreflang="<?php echo esc_attr($code); ?>" lang="<?php echo esc_attr($code); ?>" title="<?php echo esc_attr(TWD_I18n::languages()[$code]); ?>" class="<?php echo $lang === $code ? 'is-active' : ''; ?>"<?php echo $lang === $code ? ' aria-current="true"' : ''; ?>><?php echo esc_html($short); ?></a>
                <?php endforeach; ?>
            </span>
        </nav>
    </div>
</header>
<main class="tw-pub-main">
    <?php include TWD_DIR . 'templates/' . ($screen === 'my' ? 'portal.php' : 'register.php'); ?>
</main>
<footer class="tw-pub-foot">
    <div class="tw-pub-inner">
        <span>© <?php echo esc_html(current_time('Y') . ' ' . $s['company_name']); ?></span>
        <span><?php echo esc_html($s['company_contact']); ?></span>
        <?php if ($privacy) : ?><a href="<?php echo esc_url($privacy); ?>" target="_blank" rel="noopener"><?php twd_e('Πολιτική απορρήτου'); ?></a><?php endif; ?>
    </div>
</footer>
<script>var TWD = <?php echo wp_json_encode($config, JSON_UNESCAPED_UNICODE); ?>;</script>
<script src="<?php echo esc_url(TWD_App::asset('assets/js/otp-input.js')); ?>"></script>
<script src="<?php echo esc_url(TWD_App::asset('assets/js/frontend.js')); ?>"></script>
<?php if (TWD_Guard::on('public')) : ?><script src="<?php echo esc_url(TWD_Guard::explicit_script_url()); ?>" async defer></script><?php endif; ?>
</body>
</html>
