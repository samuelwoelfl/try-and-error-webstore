<?php
declare(strict_types=1);

function require_admin(): void
{
    if (empty($_SESSION['admin_id'])) {
        json_error('Nicht angemeldet.', 401);
    }
}
