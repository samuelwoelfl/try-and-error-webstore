<?php
declare(strict_types=1);

function fmt_euro(int $cents): string
{
    return number_format($cents / 100, 2, ',', '.') . ' €';
}

function gen_order_number(): string
{
    return 'MV-' . random_int(100000, 999999);
}
