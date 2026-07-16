<?php
declare(strict_types=1);

function require_admin(): void
{
    if (empty($_SESSION['admin_id'])) {
        json_error('Nicht angemeldet.', 401);
    }
}

/** Non-exiting check for endpoints that behave differently for admins vs. the public (e.g. hidden works). */
function is_admin(): bool
{
    return !empty($_SESSION['admin_id']);
}
