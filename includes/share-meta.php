<?php
/*
 * Link preview for chat apps and social networks (Open Graph).
 *
 * When someone sends the Masar link on WhatsApp, Telegram, Teams ...,
 * the app fetches the page and shows this title, description and image
 * above the link. Used on the public pages only: a preview robot is
 * never signed in, so it always lands on the sign-in page.
 *
 * The image address must be absolute and reachable from the internet;
 * set APP_URL in .env when Masar runs behind a proxy.
 */

$shareBaseUrl = appBaseUrl();
$shareTitle = t('share_title');
$shareDescription = t('share_description');
$shareEscape = static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
?>
    <meta name="description" content="<?= $shareEscape($shareDescription) ?>">
    <meta name="theme-color" content="#244f42">
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="Masar">
    <meta property="og:title" content="<?= $shareEscape($shareTitle) ?>">
    <meta property="og:description" content="<?= $shareEscape($shareDescription) ?>">
    <meta property="og:url" content="<?= $shareEscape($shareBaseUrl . '/') ?>">
    <meta property="og:image" content="<?= $shareEscape($shareBaseUrl . '/assets/brand/masar-share.png') ?>">
    <meta property="og:image:type" content="image/png">
    <meta property="og:image:width" content="1200">
    <meta property="og:image:height" content="630">
    <meta property="og:image:alt" content="Masar">
    <meta property="og:locale" content="<?= currentLanguage() === 'ar' ? 'ar_JO' : 'en_US' ?>">
    <meta name="twitter:card" content="summary_large_image">
