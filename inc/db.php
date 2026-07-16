<?php
declare(strict_types=1);

function get_db(): PDO
{
    static $pdo = null;
    if ($pdo !== null) {
        return $pdo;
    }

    $host = env('DB_HOST', 'localhost');
    $port = env('DB_PORT', '3306');
    $name = env('DB_NAME', '');
    $user = env('DB_USER', '');
    $pass = env('DB_PASS', '');

    $dsn = "mysql:host=$host;port=$port;dbname=$name;charset=utf8mb4";
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);

    $marker = __DIR__ . '/../.installed';
    if (!is_file($marker)) {
        migrate_db($pdo);
        seed_db($pdo);
        @file_put_contents($marker, date('c'));
    } else {
        // Adds columns introduced after the site was first installed — CREATE TABLE
        // IF NOT EXISTS above is a no-op once the table already exists, so new
        // columns need this separate, always-run, idempotent check.
        migrate_settings_columns($pdo);
        migrate_orders_columns($pdo);
        migrate_works_columns($pdo);
    }

    return $pdo;
}

function migrate_works_columns(PDO $pdo): void
{
    $existing = $pdo->query('SHOW COLUMNS FROM works')->fetchAll(PDO::FETCH_COLUMN);
    $columns = [
        'is_hidden' => 'TINYINT(1) NOT NULL DEFAULT 0',
    ];
    foreach ($columns as $name => $definition) {
        if (!in_array($name, $existing, true)) {
            $pdo->exec("ALTER TABLE works ADD COLUMN $name $definition");
        }
    }
}

function migrate_orders_columns(PDO $pdo): void
{
    $existing = $pdo->query('SHOW COLUMNS FROM orders')->fetchAll(PDO::FETCH_COLUMN);
    $columns = [
        'fulfilled_at' => 'DATETIME DEFAULT NULL',
    ];
    foreach ($columns as $name => $definition) {
        if (!in_array($name, $existing, true)) {
            $pdo->exec("ALTER TABLE orders ADD COLUMN $name $definition");
        }
    }
}

function migrate_settings_columns(PDO $pdo): void
{
    $existing = $pdo->query('SHOW COLUMNS FROM settings')->fetchAll(PDO::FETCH_COLUMN);
    $columns = [
        'hero_eyebrow' => "VARCHAR(255) NOT NULL DEFAULT ''",
        'hero_image_a_url' => 'VARCHAR(500) DEFAULT NULL',
        'hero_image_b_url' => 'VARCHAR(500) DEFAULT NULL',
        'works_eyebrow' => "VARCHAR(255) NOT NULL DEFAULT ''",
        'works_title' => "VARCHAR(255) NOT NULL DEFAULT ''",
        'works_count_label' => "VARCHAR(100) NOT NULL DEFAULT ''",
        'about_eyebrow' => "VARCHAR(255) NOT NULL DEFAULT ''",
        'about_image_url' => 'VARCHAR(500) DEFAULT NULL',
        'order_notification_email' => 'VARCHAR(255) DEFAULT NULL',
        'order_sender_name' => 'VARCHAR(255) DEFAULT NULL',
        'order_sender_email' => 'VARCHAR(255) DEFAULT NULL',
    ];
    foreach ($columns as $name => $definition) {
        if (!in_array($name, $existing, true)) {
            $pdo->exec("ALTER TABLE settings ADD COLUMN $name $definition");
        }
    }
}

function migrate_db(PDO $pdo): void
{
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS works (
            id INT AUTO_INCREMENT PRIMARY KEY,
            title VARCHAR(255) NOT NULL,
            technique VARCHAR(255) NOT NULL DEFAULT '',
            width_cm INT NOT NULL DEFAULT 0,
            height_cm INT NOT NULL DEFAULT 0,
            year INT NOT NULL DEFAULT 0,
            price_cents INT NOT NULL DEFAULT 0,
            kind ENUM('unique','edition') NOT NULL DEFAULT 'unique',
            edition_label VARCHAR(255) NOT NULL DEFAULT '',
            status ENUM('verfuegbar','verkauft') NOT NULL DEFAULT 'verfuegbar',
            description TEXT NOT NULL,
            image_url VARCHAR(500) DEFAULT NULL,
            sort_order INT NOT NULL DEFAULT 0,
            is_hidden TINYINT(1) NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS settings (
            id INT PRIMARY KEY,
            hero_title VARCHAR(500) NOT NULL DEFAULT '',
            hero_sub TEXT NOT NULL,
            hero_eyebrow VARCHAR(255) NOT NULL DEFAULT '',
            hero_image_a_url VARCHAR(500) DEFAULT NULL,
            hero_image_b_url VARCHAR(500) DEFAULT NULL,
            works_eyebrow VARCHAR(255) NOT NULL DEFAULT '',
            works_title VARCHAR(255) NOT NULL DEFAULT '',
            works_count_label VARCHAR(100) NOT NULL DEFAULT '',
            about_title VARCHAR(500) NOT NULL DEFAULT '',
            about_text TEXT NOT NULL,
            about_eyebrow VARCHAR(255) NOT NULL DEFAULT '',
            about_image_url VARCHAR(500) DEFAULT NULL,
            logo_url VARCHAR(500) DEFAULT NULL,
            order_notification_email VARCHAR(255) DEFAULT NULL,
            order_sender_name VARCHAR(255) DEFAULT NULL,
            order_sender_email VARCHAR(255) DEFAULT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    migrate_settings_columns($pdo);

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS admin_users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            email VARCHAR(255) NOT NULL UNIQUE,
            password_hash VARCHAR(255) NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS orders (
            id INT AUTO_INCREMENT PRIMARY KEY,
            order_number VARCHAR(32) NOT NULL UNIQUE,
            status ENUM('pending','paid','failed','cancelled') NOT NULL DEFAULT 'pending',
            payment_method ENUM('stripe','paypal','invoice') NOT NULL,
            first_name VARCHAR(255) NOT NULL,
            last_name VARCHAR(255) NOT NULL,
            email VARCHAR(255) NOT NULL,
            street VARCHAR(255) NOT NULL,
            zip VARCHAR(20) NOT NULL,
            city VARCHAR(255) NOT NULL,
            total_cents INT NOT NULL,
            currency VARCHAR(10) NOT NULL DEFAULT 'eur',
            stripe_payment_intent_id VARCHAR(255) DEFAULT NULL,
            paypal_order_id VARCHAR(255) DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            paid_at DATETIME DEFAULT NULL,
            fulfilled_at DATETIME DEFAULT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS order_items (
            id INT AUTO_INCREMENT PRIMARY KEY,
            order_id INT NOT NULL,
            work_id INT NOT NULL,
            title VARCHAR(255) NOT NULL,
            meta_line VARCHAR(500) NOT NULL DEFAULT '',
            unit_price_cents INT NOT NULL,
            qty INT NOT NULL,
            kind ENUM('unique','edition') NOT NULL,
            FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
            FOREIGN KEY (work_id) REFERENCES works(id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
}

function seed_db(PDO $pdo): void
{
    if ((int) $pdo->query('SELECT COUNT(*) FROM works')->fetchColumn() === 0) {
        $insert = $pdo->prepare("
            INSERT INTO works (title, technique, width_cm, height_cm, year, price_cents, kind, edition_label, status, description, sort_order)
            VALUES (:title, :technique, :width_cm, :height_cm, :year, :price_cents, :kind, :edition_label, :status, :description, :sort_order)
        ");
        $seed = [
            ['title' => 'Morgennebel', 'technique' => 'Öl auf Leinwand', 'width_cm' => 80, 'height_cm' => 100, 'year' => 2024, 'price_cents' => 145000, 'kind' => 'unique', 'edition_label' => '', 'status' => 'verfuegbar', 'description' => 'Ein weiches Landschaftsmotiv im ersten Licht des Tages. Lasierende Ölschichten lassen den Nebel über dem Feld beinahe schweben.'],
            ['title' => 'Küstenlicht', 'technique' => 'Acryl auf Leinwand', 'width_cm' => 60, 'height_cm' => 80, 'year' => 2023, 'price_cents' => 98000, 'kind' => 'unique', 'edition_label' => '', 'status' => 'verkauft', 'description' => 'Kräftige Acrylflächen fangen das flirrende Licht über dem Wasser ein — eines meiner ausdrucksstärksten Werke.'],
            ['title' => 'Stille', 'technique' => 'Aquarell auf Papier', 'width_cm' => 30, 'height_cm' => 40, 'year' => 2024, 'price_cents' => 32000, 'kind' => 'unique', 'edition_label' => '', 'status' => 'verfuegbar', 'description' => 'Eine zarte, meditative Studie in gedeckten Tönen. Gerahmt hinter entspiegeltem Museumsglas.'],
            ['title' => 'Feldstudie', 'technique' => 'Kunstdruck (Fine Art)', 'width_cm' => 50, 'height_cm' => 70, 'year' => 2024, 'price_cents' => 12000, 'kind' => 'edition', 'edition_label' => 'Edition von 50', 'status' => 'verfuegbar', 'description' => 'Hochwertiger Fine-Art-Print auf Hahnemühle-Papier, handsigniert und nummeriert. Ohne Rahmen.'],
            ['title' => 'Abendrot', 'technique' => 'Öl auf Leinwand', 'width_cm' => 100, 'height_cm' => 120, 'year' => 2022, 'price_cents' => 198000, 'kind' => 'unique', 'edition_label' => '', 'status' => 'verfuegbar', 'description' => 'Ein großformatiges Statement in warmen Rot- und Ockertönen — das Herzstück meiner Landschaftsserie.'],
            ['title' => 'Fragment', 'technique' => 'Mischtechnik auf Holz', 'width_cm' => 40, 'height_cm' => 40, 'year' => 2024, 'price_cents' => 54000, 'kind' => 'unique', 'edition_label' => '', 'status' => 'verfuegbar', 'description' => 'Collagierte Papiere, Kreide und Öl auf Holztafel. Ein taktiles, kleines Unikat.'],
            ['title' => 'Horizont', 'technique' => 'Kunstdruck (Fine Art)', 'width_cm' => 40, 'height_cm' => 50, 'year' => 2023, 'price_cents' => 9500, 'kind' => 'edition', 'edition_label' => 'Edition von 100', 'status' => 'verfuegbar', 'description' => 'Ruhiger Farbverlauf als handsignierter Fine-Art-Print. Passt in jeden Standardrahmen.'],
        ];
        foreach ($seed as $i => $row) {
            $row['sort_order'] = $i;
            $insert->execute($row);
        }
    }

    if ((int) $pdo->query('SELECT COUNT(*) FROM settings')->fetchColumn() === 0) {
        $pdo->prepare("
            INSERT INTO settings (
                id, hero_title, hero_sub, hero_eyebrow, works_eyebrow, works_title, works_count_label,
                about_title, about_text, about_eyebrow, logo_url, order_notification_email
            )
            VALUES (
                1, :hero_title, :hero_sub, :hero_eyebrow, :works_eyebrow, :works_title, :works_count_label,
                :about_title, :about_text, :about_eyebrow, NULL, :order_notification_email
            )
        ")->execute([
            'hero_title' => 'Malerei aus dem Atelier.',
            'hero_sub' => 'Handgemalte Unikate und limitierte Editionen — direkt aus dem Studio zu dir nach Hause. Jedes Werk erzählt eine eigene, stille Geschichte.',
            'hero_eyebrow' => 'Originale & Editionen',
            'works_eyebrow' => 'Die Sammlung',
            'works_title' => 'Alle Werke',
            'works_count_label' => 'Arbeiten',
            'about_title' => 'Try & Error',
            'about_text' => "Try & Error ist ein offenes Atelier-Projekt aus Stuttgart.\n\nHier entstehen handgemalte Unikate und kleine Editionen — meist in Öl und Acryl, manchmal in Aquarell. Das Projekt kreist um Licht, Landschaft und stille Momente und versteht das Ausprobieren als festen Teil der Arbeit.\n\nJedes Original ist ein Unikat und wird sorgfältig verpackt versendet. Editionen entstehen als kleine, limitierte Auflagen. Bei Fragen zu einem Werk schreib uns gern.",
            'about_eyebrow' => 'Über das Projekt',
            'order_notification_email' => env('ADMIN_EMAIL', 'admin@atelier.de'),
        ]);
    }

    if ((int) $pdo->query('SELECT COUNT(*) FROM admin_users')->fetchColumn() === 0) {
        $email = env('ADMIN_EMAIL', 'admin@atelier.de');
        $password = env('ADMIN_PASSWORD', 'change-me-now');
        $pdo->prepare('INSERT INTO admin_users (email, password_hash) VALUES (:email, :hash)')->execute([
            'email' => strtolower($email),
            'hash' => password_hash($password, PASSWORD_BCRYPT),
        ]);
        if (env('ADMIN_PASSWORD') === null) {
            error_log("[admin] Kein ADMIN_PASSWORD in .env gesetzt — Fallback-Login: $email / $password. Bitte .env ändern und admin_users-Tabelle leeren, um neu zu seeden.");
        }
    }
}
