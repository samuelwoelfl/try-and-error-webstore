<?php
declare(strict_types=1);

const UPLOAD_MAX_BYTES = 8 * 1024 * 1024;
const UPLOAD_ALLOWED_MIME = [
    'image/png' => '.png',
    'image/jpeg' => '.jpg',
    'image/webp' => '.webp',
    'image/gif' => '.gif',
];

/**
 * @param string $webRoot absolute path to the directory that holds index.html/uploads/
 *                         (i.e. wherever public/'s contents were deployed to) — the caller
 *                         computes this from its own fixed position in the api/ tree, since
 *                         inc/'s location relative to it varies by hosting setup.
 * @return string public URL path (e.g. "/uploads/xyz.png")
 */
function handle_image_upload(string $fieldName, string $webRoot): string
{
    if (!isset($_FILES[$fieldName]) || $_FILES[$fieldName]['error'] === UPLOAD_ERR_NO_FILE) {
        json_error('Keine Datei erhalten.', 400);
    }
    $file = $_FILES[$fieldName];
    if ($file['error'] !== UPLOAD_ERR_OK) {
        json_error('Upload fehlgeschlagen.', 400);
    }
    if ($file['size'] > UPLOAD_MAX_BYTES) {
        json_error('Datei ist zu groß (max. 8 MB).', 400);
    }

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    if (!isset(UPLOAD_ALLOWED_MIME[$mime])) {
        json_error('Nur PNG, JPEG, WEBP oder GIF erlaubt.', 400);
    }

    $uploadDir = $webRoot . '/uploads';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    $filename = time() . '-' . bin2hex(random_bytes(6)) . UPLOAD_ALLOWED_MIME[$mime];
    $destination = $uploadDir . '/' . $filename;

    if (!move_uploaded_file($file['tmp_name'], $destination)) {
        json_error('Datei konnte nicht gespeichert werden.', 500);
    }

    return '/uploads/' . $filename;
}
