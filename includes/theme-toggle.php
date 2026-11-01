<?php
/* Theme toggle button. Labels are translated; theme.js swaps them. */
?>
<button
    type="button"
    class="theme-toggle"
    data-theme-toggle
    data-label-to-dark="<?= htmlspecialchars(t('theme_to_dark'), ENT_QUOTES, 'UTF-8') ?>"
    data-label-to-light="<?= htmlspecialchars(t('theme_to_light'), ENT_QUOTES, 'UTF-8') ?>"
    aria-label="<?= htmlspecialchars(t('theme_to_dark'), ENT_QUOTES, 'UTF-8') ?>"
    aria-pressed="false"
    title="<?= htmlspecialchars(t('theme_change'), ENT_QUOTES, 'UTF-8') ?>"
>
    <span class="theme-toggle__icon" data-theme-icon aria-hidden="true">☾</span>
</button>
