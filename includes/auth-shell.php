<?php

declare(strict_types=1);

/**
 * Page frame shared by the password reset pages (same look as the
 * sign-in page): brand panel on one side, form pane on the other.
 *
 * Requires includes/language.php. Call authShellStart() before the form
 * and authShellEnd() after it.
 */

function authShellStart(string $pageTitle, string $fallbackUri): void
{
    $currentLanguage = currentLanguage();
    $switchToLanguage = $currentLanguage === 'en' ? 'ar' : 'en';
    $languageSwitchUrl = '/masar/switch-language.php?lang='
        . urlencode($switchToLanguage)
        . '&return='
        . urlencode($_SERVER['REQUEST_URI'] ?? $fallbackUri);
    $e = static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    ?>
<!DOCTYPE html>
<html lang="<?= $e($currentLanguage) ?>" dir="<?= $e(currentDirection()) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="referrer" content="no-referrer">
    <title><?= $e($pageTitle) ?> | Masar</title>

    <link rel="icon" type="image/svg+xml" href="/masar/assets/brand/masar-favicon.svg">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Tajawal:wght@400;500;700&display=swap"
        rel="stylesheet"
    >
    <?php require __DIR__ . '/theme-head.php'; ?>
</head>
<body class="auth-body-new">
<main class="auth-shell-new">
    <aside class="auth-brand-new">
        <a class="auth-brand-new__logo" href="/masar/auth/login.php" aria-label="Masar">
            <img src="/masar/assets/brand/masar-logo-horizontal-white.svg" alt="Masar">
        </a>

        <div class="auth-brand-new__content">
            <p class="auth-brand-new__eyebrow"><?= $e(t('auth_brand_eyebrow')) ?></p>
            <h1><?= $e(t('auth_brand_title')) ?></h1>
            <p class="auth-brand-new__intro"><?= $e(t('auth_brand_body')) ?></p>
        </div>

        <p class="auth-brand-new__note"><?= $e(t('auth_brand_note')) ?></p>
    </aside>

    <section class="auth-form-pane-new">
        <div class="auth-toolbar-new">
            <a class="auth-mobile-logo-new" href="/masar/auth/login.php" aria-label="Masar">
                <img class="brand-logo-light" src="/masar/assets/brand/masar-logo-horizontal.svg" alt="Masar">
                <img class="brand-logo-dark" src="/masar/assets/brand/masar-logo-horizontal-white.svg" alt="Masar">
            </a>

            <div class="auth-toolbar-new__actions">
                <?php require __DIR__ . '/theme-toggle.php'; ?>

                <a class="auth-language-new" href="<?= $e($languageSwitchUrl) ?>">
                    <?= $e(t('switch_language')) ?>
                </a>
            </div>
        </div>

        <div class="auth-form-wrap-new">
    <?php
}

function authShellEnd(): void
{
    ?>
            <p class="auth-switch-copy-new">
                <a href="/masar/auth/login.php"><?= htmlspecialchars(t('auth_back_to_login')) ?></a>
            </p>
        </div>

        <p class="auth-pane-footer-new"><?= htmlspecialchars(t('auth_footer_note')) ?></p>
    </section>
</main>
</body>
</html>
    <?php
}
