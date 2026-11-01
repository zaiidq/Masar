<?php

declare(strict_types=1);

/*
 * MEU registration rules used by the recommendation validator.
 *
 * A student is "graduating" when the credit hours still needed after the
 * current in-progress courses fit within the graduating limit, i.e. they
 * can finish their plan in the semester being planned.
 *
 * Change the numbers here if the university rules change; the AI prompt
 * and the PHP validator both read them from this file.
 */

return [
    'credit_limits' => [
        'regular' => [
            'normal' => 18,
            'graduating' => 21,
        ],
        'summer' => [
            'normal' => 9,
            'graduating' => 12,
        ],
    ],

    /* Minutes after which a record stuck in "processing" counts as failed. */
    'analysis_timeout_minutes' => 10,
];
