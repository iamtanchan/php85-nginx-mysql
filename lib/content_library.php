<?php
declare(strict_types=1);

const CONTENT_COMMON_STATION_ID = 0;
const CONTENT_COMMON_SLOT_MAX = 5;
const CONTENT_STATION_MAX = 10;
const CONTENT_DISPLAY_MAX = 3;
const CONTENT_DISPLAY_SWAP_INTERVAL_DEFAULT = 8;
const CONTENT_DISPLAY_SWAP_INTERVAL_MAX = 600;
const CONTENT_IMAGE_MAX_BYTES = 20971520;
const CONTENT_VIDEO_MAX_BYTES = 419430400;
const CONTENT_UPLOAD_DIR = __DIR__ . '/../uploads/content';
const CONTENT_UPLOAD_WEB_PATH = 'uploads/content';

function content_normalize_swap_interval_seconds(int $seconds): int
{
    if ($seconds <= 0) {
        return 0;
    }
    if ($seconds > CONTENT_DISPLAY_SWAP_INTERVAL_MAX) {
        return CONTENT_DISPLAY_SWAP_INTERVAL_MAX;
    }
    return $seconds;
}

function content_fetch_display_settings(PDO $db, int $station_id): array
{
    $default = array(
        'station_id' => $station_id,
        'swap_interval_seconds' => CONTENT_DISPLAY_SWAP_INTERVAL_DEFAULT,
    );

    if ($station_id <= 0) {
        return $default;
    }

    $stt = $db->prepare(
        'SELECT station_id, swap_interval_seconds
         FROM content_display_setting
         WHERE station_id = :station_id
         LIMIT 1'
    );
    $stt->bindValue(':station_id', $station_id, PDO::PARAM_INT);
    $stt->execute();
    $row = $stt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        return $default;
    }

    return array(
        'station_id' => (int)$row['station_id'],
        'swap_interval_seconds' => content_normalize_swap_interval_seconds((int)$row['swap_interval_seconds']),
    );
}

function content_update_display_settings(PDO $db, int $station_id, int $swap_interval_seconds): void
{
    if ($station_id <= 0) {
        throw new RuntimeException('station_id is required');
    }

    $swap_interval_seconds = content_normalize_swap_interval_seconds($swap_interval_seconds);
    $stt = $db->prepare(
        'INSERT INTO content_display_setting (station_id, swap_interval_seconds)
         VALUES (:station_id, :swap_interval_seconds)
         ON DUPLICATE KEY UPDATE
             swap_interval_seconds = VALUES(swap_interval_seconds)'
    );
    $stt->bindValue(':station_id', $station_id, PDO::PARAM_INT);
    $stt->bindValue(':swap_interval_seconds', $swap_interval_seconds, PDO::PARAM_INT);
    $stt->execute();
}

function content_normalize_type(string $content_type): string
{
    $content_type = trim($content_type);
    return in_array($content_type, array('image', 'movie'), true) ? $content_type : 'image';
}

function content_has_uploaded_file(array $file): bool
{
    return isset($file['error']) && (int)$file['error'] !== UPLOAD_ERR_NO_FILE;
}

function content_parse_ini_size_to_bytes(string $value): int
{
    $value = trim($value);
    if ($value === '') {
        return 0;
    }

    $unit = strtolower(substr($value, -1));
    $bytes = (int)$value;
    switch ($unit) {
        case 'g':
            return $bytes * 1024 * 1024 * 1024;
        case 'm':
            return $bytes * 1024 * 1024;
        case 'k':
            return $bytes * 1024;
        default:
            return (int)$value;
    }
}

function content_effective_max_bytes(string $content_type): int
{
    $limit = $content_type === 'movie' ? CONTENT_VIDEO_MAX_BYTES : CONTENT_IMAGE_MAX_BYTES;
    $limits = array($limit);

    $upload_max = content_parse_ini_size_to_bytes((string)ini_get('upload_max_filesize'));
    if ($upload_max > 0) {
        $limits[] = $upload_max;
    }

    $post_max = content_parse_ini_size_to_bytes((string)ini_get('post_max_size'));
    if ($post_max > 0) {
        $limits[] = $post_max;
    }

    return (int)min($limits);
}

function content_format_bytes(int $bytes): string
{
    if ($bytes >= 1024 * 1024 * 1024) {
        return rtrim(rtrim(number_format($bytes / (1024 * 1024 * 1024), 2, '.', ''), '0'), '.') . 'GB';
    }
    if ($bytes >= 1024 * 1024) {
        return rtrim(rtrim(number_format($bytes / (1024 * 1024), 2, '.', ''), '0'), '.') . 'MB';
    }
    if ($bytes >= 1024) {
        return rtrim(rtrim(number_format($bytes / 1024, 2, '.', ''), '0'), '.') . 'KB';
    }
    return (string)$bytes . 'B';
}

function content_upload_error_message(int $error, string $content_type): string
{
    $label = $content_type === 'movie' ? '動画' : '画像';
    $max_label = content_format_bytes(content_effective_max_bytes($content_type));

    switch ($error) {
        case UPLOAD_ERR_INI_SIZE:
        case UPLOAD_ERR_FORM_SIZE:
            return $label . 'ファイルが大きすぎます。上限は ' . $max_label . ' です。';
        case UPLOAD_ERR_PARTIAL:
            return $label . 'ファイルのアップロードが途中で中断されました。';
        case UPLOAD_ERR_NO_TMP_DIR:
            return '一時保存用のディレクトリが見つかりません。';
        case UPLOAD_ERR_CANT_WRITE:
            return $label . 'ファイルを書き込めませんでした。';
        case UPLOAD_ERR_EXTENSION:
            return 'PHP拡張の制限によりアップロードが停止しました。';
        default:
            return $label . 'ファイルのアップロードに失敗しました。';
    }
}

function content_upload_token(): string
{
    try {
        return bin2hex(random_bytes(8));
    } catch (Throwable $e) {
        return str_replace('.', '', uniqid('', true));
    }
}

function content_store_uploaded_media(array $file, string $content_type): string
{
    if (!content_has_uploaded_file($file)) {
        throw new RuntimeException('ファイルを選択してください。');
    }

    $error = (int)($file['error'] ?? UPLOAD_ERR_NO_FILE);
    if ($error !== UPLOAD_ERR_OK) {
        throw new RuntimeException(content_upload_error_message($error, $content_type));
    }

    $tmp_name = (string)($file['tmp_name'] ?? '');
    if ($tmp_name === '' || !is_uploaded_file($tmp_name)) {
        throw new RuntimeException('アップロードファイルを確認できませんでした。');
    }

    $size = (int)($file['size'] ?? 0);
    if ($size <= 0) {
        throw new RuntimeException('アップロードされたファイルが空です。');
    }

    $max_bytes = content_effective_max_bytes($content_type);
    if ($size > $max_bytes) {
        throw new RuntimeException('ファイルサイズが上限を超えています。上限は ' . content_format_bytes($max_bytes) . ' です。');
    }

    $map = array(
        'image' => array(
            'extensions' => array('jpg', 'jpeg', 'png', 'gif', 'webp'),
            'mimes' => array('image/jpeg', 'image/png', 'image/gif', 'image/webp'),
            'prefix' => 'image_',
        ),
        'movie' => array(
            'extensions' => array('mp4', 'webm', 'ogv', 'mov', 'm4v'),
            'mimes' => array('video/mp4', 'video/webm', 'video/ogg', 'application/ogg', 'video/quicktime', 'video/x-m4v'),
            'prefix' => 'video_',
        ),
    );
    $settings = $map[$content_type] ?? $map['image'];

    $extension = strtolower(pathinfo((string)($file['name'] ?? ''), PATHINFO_EXTENSION));
    if ($extension === '' || !in_array($extension, $settings['extensions'], true)) {
        $label = $content_type === 'movie' ? 'mp4 / webm / ogv / mov / m4v' : 'jpg / jpeg / png / gif / webp';
        throw new RuntimeException('対応していないファイル形式です。' . $label . ' を利用してください。');
    }

    if (function_exists('finfo_open')) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        if ($finfo !== false) {
            $mime = (string)finfo_file($finfo, $tmp_name);
            if (is_resource($finfo)) {
                finfo_close($finfo);
            }
            if ($mime !== '' && !in_array($mime, $settings['mimes'], true)) {
                throw new RuntimeException('ファイル形式を判定できませんでした。別のファイルを選択してください。');
            }
        }
    }

    if (!is_dir(CONTENT_UPLOAD_DIR) && !mkdir(CONTENT_UPLOAD_DIR, 0775, true) && !is_dir(CONTENT_UPLOAD_DIR)) {
        throw new RuntimeException('アップロード先ディレクトリを作成できませんでした。');
    }

    $filename = $settings['prefix'] . date('Ymd_His') . '_' . content_upload_token() . '.' . $extension;
    $relative_path = CONTENT_UPLOAD_WEB_PATH . '/' . $filename;
    $target_path = CONTENT_UPLOAD_DIR . DIRECTORY_SEPARATOR . $filename;
    if (!move_uploaded_file($tmp_name, $target_path)) {
        throw new RuntimeException('ファイルを保存できませんでした。');
    }

    return str_replace('\\', '/', $relative_path);
}

function content_local_path(string $content_value): ?string
{
    $normalized = str_replace('\\', '/', trim($content_value));
    if ($normalized === '' || strpos($normalized, CONTENT_UPLOAD_WEB_PATH . '/') !== 0) {
        return null;
    }

    $relative = substr($normalized, strlen(CONTENT_UPLOAD_WEB_PATH) + 1);
    if ($relative === '' || strpos($relative, '..') !== false) {
        return null;
    }

    return CONTENT_UPLOAD_DIR . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
}

function content_delete_media_file(string $content_value): void
{
    $path = content_local_path($content_value);
    if ($path !== null && is_file($path)) {
        @unlink($path);
    }
}

function content_fetch_item(PDO $db, int $content_id): ?array
{
    $stt = $db->prepare(
        'SELECT content_item_id AS id, station_id, slot_no, title, content_type, content_value, start_at, end_at, is_active, sort_order, note, created_at, updated_at
         FROM content_item
         WHERE content_item_id = :id
         LIMIT 1'
    );
    $stt->bindValue(':id', $content_id, PDO::PARAM_INT);
    $stt->execute();
    $row = $stt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

function content_is_visible_for_station(array $row, int $station_id): bool
{
    $item_station_id = (int)($row['station_id'] ?? 0);
    return $item_station_id === CONTENT_COMMON_STATION_ID || $item_station_id === $station_id;
}

function content_fetch_counts(PDO $db, int $station_id): array
{
    $common_stt = $db->prepare('SELECT COUNT(*) FROM content_item WHERE station_id = :station_id');
    $common_stt->bindValue(':station_id', CONTENT_COMMON_STATION_ID, PDO::PARAM_INT);
    $common_stt->execute();
    $common_count = (int)$common_stt->fetchColumn();

    $station_stt = $db->prepare('SELECT COUNT(*) FROM content_item WHERE station_id = :station_id');
    $station_stt->bindValue(':station_id', $station_id, PDO::PARAM_INT);
    $station_stt->execute();
    $station_count = (int)$station_stt->fetchColumn();

    $display_stt = $db->prepare('SELECT COUNT(*) FROM content_display WHERE station_id = :station_id');
    $display_stt->bindValue(':station_id', $station_id, PDO::PARAM_INT);
    $display_stt->execute();
    $display_count = (int)$display_stt->fetchColumn();

    return array(
        'common' => $common_count,
        'station' => $station_count,
        'display' => $display_count,
    );
}

function content_fetch_common_slots(PDO $db): array
{
    $stt = $db->prepare(
        'SELECT content_item_id AS id, slot_no, title, content_type, content_value, updated_at
         FROM content_item
         WHERE station_id = :station_id AND slot_no IS NOT NULL
         ORDER BY slot_no ASC'
    );
    $stt->bindValue(':station_id', CONTENT_COMMON_STATION_ID, PDO::PARAM_INT);
    $stt->execute();

    $slots = array();
    foreach ($stt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $slot_no = (int)($row['slot_no'] ?? 0);
        if ($slot_no > 0) {
            $slots[$slot_no] = $row;
        }
    }
    return $slots;
}

function content_slot_title(array $slot_map, int $slot_no): string
{
    if (!isset($slot_map[$slot_no])) {
        return '未登録';
    }
    return (string)($slot_map[$slot_no]['title'] ?? '登録済み');
}

function content_next_sort_order(PDO $db, int $item_station_id): int
{
    $stt = $db->prepare('SELECT COALESCE(MAX(sort_order), 0) + 10 FROM content_item WHERE station_id = :station_id');
    $stt->bindValue(':station_id', $item_station_id, PDO::PARAM_INT);
    $stt->execute();
    return (int)$stt->fetchColumn();
}

function content_find_common_slot(PDO $db, int $slot_no): ?array
{
    $stt = $db->prepare(
        'SELECT content_item_id AS id, station_id, slot_no, title, content_type, content_value
         FROM content_item
         WHERE station_id = :station_id AND slot_no = :slot_no
         LIMIT 1'
    );
    $stt->bindValue(':station_id', CONTENT_COMMON_STATION_ID, PDO::PARAM_INT);
    $stt->bindValue(':slot_no', $slot_no, PDO::PARAM_INT);
    $stt->execute();
    $row = $stt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

function content_resolve_target_station_id(array $stations, int $default_station_id, string $create_target): int
{
    if ($create_target === '0') {
        return CONTENT_COMMON_STATION_ID;
    }
    return resolve_station_id($stations, (int)$create_target ?: $default_station_id);
}

function content_save_item(
    PDO $db,
    array $stations,
    int $default_station_id,
    string $create_target,
    int $create_slot_no,
    string $create_title,
    string $create_content_type,
    array $upload_file
): void {
    if ($create_title === '') {
        throw new RuntimeException('タイトルを入力してください。');
    }

    $create_title = function_exists('mb_substr') ? mb_substr($create_title, 0, 120, 'UTF-8') : substr($create_title, 0, 120);
    $create_content_type = content_normalize_type($create_content_type);
    $item_station_id = content_resolve_target_station_id($stations, $default_station_id, $create_target);
    $uploaded_path = content_store_uploaded_media($upload_file, $create_content_type);
    $old_content_path = '';

    try {
        $db->beginTransaction();
        if ($item_station_id === CONTENT_COMMON_STATION_ID) {
            if ($create_slot_no < 1 || $create_slot_no > CONTENT_COMMON_SLOT_MAX) {
                throw new RuntimeException('共通コンテンツ枠は1から5の範囲で指定してください。');
            }

            $existing = content_find_common_slot($db, $create_slot_no);
            if ($existing) {
                $old_content_path = (string)$existing['content_value'];
                $stt = $db->prepare(
                    'UPDATE content_item
                     SET title = :title, content_type = :content_type, content_value = :content_value, slot_no = :slot_no, sort_order = :sort_order, is_active = 1, start_at = NULL, end_at = NULL
                     WHERE content_item_id = :id'
                );
                $stt->bindValue(':id', (int)$existing['id'], PDO::PARAM_INT);
                $stt->bindValue(':title', $create_title);
                $stt->bindValue(':content_type', $create_content_type);
                $stt->bindValue(':content_value', $uploaded_path);
                $stt->bindValue(':slot_no', $create_slot_no, PDO::PARAM_INT);
                $stt->bindValue(':sort_order', $create_slot_no, PDO::PARAM_INT);
                $stt->execute();
            } else {
                $stt = $db->prepare(
                    'INSERT INTO content_item (station_id, slot_no, title, content_type, content_value, start_at, end_at, is_active, sort_order, note)
                     VALUES (:station_id, :slot_no, :title, :content_type, :content_value, NULL, NULL, 1, :sort_order, \'\')'
                );
                $stt->bindValue(':station_id', CONTENT_COMMON_STATION_ID, PDO::PARAM_INT);
                $stt->bindValue(':slot_no', $create_slot_no, PDO::PARAM_INT);
                $stt->bindValue(':title', $create_title);
                $stt->bindValue(':content_type', $create_content_type);
                $stt->bindValue(':content_value', $uploaded_path);
                $stt->bindValue(':sort_order', $create_slot_no, PDO::PARAM_INT);
                $stt->execute();
            }
        } else {
            $counts = content_fetch_counts($db, $item_station_id);
            if ((int)$counts['station'] >= CONTENT_STATION_MAX) {
                throw new RuntimeException('停留所コンテンツは最大' . CONTENT_STATION_MAX . '件までです。');
            }

            $stt = $db->prepare(
                'INSERT INTO content_item (station_id, slot_no, title, content_type, content_value, start_at, end_at, is_active, sort_order, note)
                 VALUES (:station_id, NULL, :title, :content_type, :content_value, NULL, NULL, 1, :sort_order, \'\')'
            );
            $stt->bindValue(':station_id', $item_station_id, PDO::PARAM_INT);
            $stt->bindValue(':title', $create_title);
            $stt->bindValue(':content_type', $create_content_type);
            $stt->bindValue(':content_value', $uploaded_path);
            $stt->bindValue(':sort_order', content_next_sort_order($db, $item_station_id), PDO::PARAM_INT);
            $stt->execute();
        }
        $db->commit();

        if ($old_content_path !== '' && $old_content_path !== $uploaded_path) {
            content_delete_media_file($old_content_path);
        }
    } catch (Throwable $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        if ($uploaded_path !== '') {
            content_delete_media_file($uploaded_path);
        }
        throw $e;
    }
}

function content_fetch_published_ids(PDO $db, int $station_id): array
{
    $stt = $db->prepare(
        'SELECT content_id
         FROM content_display
         WHERE station_id = :station_id
         ORDER BY sort_order ASC'
    );
    $stt->bindValue(':station_id', $station_id, PDO::PARAM_INT);
    $stt->execute();

    $ids = array();
    foreach ($stt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $ids[] = (int)$row['content_id'];
    }
    return $ids;
}

function content_fetch_rows(PDO $db, int $station_id): array
{
    $stt = $db->prepare(
        'SELECT
             ci.content_item_id AS id,
             ci.station_id,
             ci.slot_no,
             ci.title,
             ci.content_type,
             ci.content_value,
             ci.start_at,
             ci.end_at,
             ci.is_active,
             ci.sort_order,
             ci.note,
             ci.created_at,
             ci.updated_at,
             cd.sort_order AS display_sort_order,
             CASE WHEN cd.content_id IS NULL THEN 0 ELSE 1 END AS is_published
         FROM content_item ci
         LEFT JOIN content_display cd
           ON cd.content_id = ci.content_item_id
          AND cd.station_id = :display_station_id
         WHERE ci.station_id = :common_station_id OR ci.station_id = :station_id
         ORDER BY
             CASE WHEN ci.station_id = :common_station_order THEN 0 ELSE 1 END ASC,
             CASE WHEN ci.slot_no IS NULL THEN 1 ELSE 0 END ASC,
             ci.slot_no ASC,
             ci.sort_order ASC,
             ci.content_item_id ASC'
    );
    $stt->bindValue(':display_station_id', $station_id, PDO::PARAM_INT);
    $stt->bindValue(':common_station_id', CONTENT_COMMON_STATION_ID, PDO::PARAM_INT);
    $stt->bindValue(':station_id', $station_id, PDO::PARAM_INT);
    $stt->bindValue(':common_station_order', CONTENT_COMMON_STATION_ID, PDO::PARAM_INT);
    $stt->execute();
    return $stt->fetchAll(PDO::FETCH_ASSOC);
}

function content_fetch_all_rows(PDO $db): array
{
    $stt = $db->prepare(
        'SELECT
             ci.content_item_id AS id,
             ci.station_id,
             ci.slot_no,
             ci.title,
             ci.content_type,
             ci.content_value,
             ci.start_at,
             ci.end_at,
             ci.is_active,
             ci.sort_order,
             ci.note,
             ci.created_at,
             ci.updated_at
         FROM content_item ci
         ORDER BY
             CASE WHEN ci.station_id = :common_station_id THEN 0 ELSE 1 END ASC,
             ci.station_id ASC,
             CASE WHEN ci.slot_no IS NULL THEN 1 ELSE 0 END ASC,
             ci.slot_no ASC,
             ci.sort_order ASC,
             ci.content_item_id ASC'
    );
    $stt->bindValue(':common_station_id', CONTENT_COMMON_STATION_ID, PDO::PARAM_INT);
    $stt->execute();
    return $stt->fetchAll(PDO::FETCH_ASSOC);
}

function content_fetch_selected_ids(PDO $db): array
{
    $stt = $db->prepare('SELECT DISTINCT content_id FROM content_display');
    $stt->execute();

    $ids = array();
    foreach ($stt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $content_id = (int)($row['content_id'] ?? 0);
        if ($content_id > 0) {
            $ids[] = $content_id;
        }
    }
    return $ids;
}

function content_is_selected(PDO $db, int $content_id): bool
{
    $stt = $db->prepare('SELECT 1 FROM content_display WHERE content_id = :content_id LIMIT 1');
    $stt->bindValue(':content_id', $content_id, PDO::PARAM_INT);
    $stt->execute();
    return (bool)$stt->fetch(PDO::FETCH_NUM);
}

function content_replace_display_items(PDO $db, int $station_id, array $content_ids): void
{
    $deduped = array();
    foreach ($content_ids as $content_id) {
        $content_id = (int)$content_id;
        if ($content_id > 0 && !in_array($content_id, $deduped, true)) {
            $deduped[] = $content_id;
        }
    }

    if (count($deduped) > CONTENT_DISPLAY_MAX) {
        throw new RuntimeException('ディスプレイに設定できるコンテンツは最大' . CONTENT_DISPLAY_MAX . '件です。');
    }

    $delete = $db->prepare('DELETE FROM content_display WHERE station_id = :station_id');
    $delete->bindValue(':station_id', $station_id, PDO::PARAM_INT);
    $delete->execute();

    if (count($deduped) === 0) {
        return;
    }

    $insert = $db->prepare(
        'INSERT INTO content_display (station_id, content_id, sort_order)
         VALUES (:station_id, :content_id, :sort_order)'
    );
    foreach ($deduped as $index => $content_id) {
        $insert->bindValue(':station_id', $station_id, PDO::PARAM_INT);
        $insert->bindValue(':content_id', $content_id, PDO::PARAM_INT);
        $insert->bindValue(':sort_order', $index + 1, PDO::PARAM_INT);
        $insert->execute();
    }
}

function content_bump_display_counter(PDO $db, int $station_id): void
{
    $stt = $db->prepare('SELECT ch FROM display WHERE display_id = :id LIMIT 1');
    $stt->bindValue(':id', $station_id, PDO::PARAM_INT);
    $stt->execute();
    $row = $stt->fetch(PDO::FETCH_ASSOC);

    if ($row) {
        $next = ((int)$row['ch'] + 1) % 100;
        $upd = $db->prepare('UPDATE display SET ch = :ch WHERE display_id = :id');
        $upd->bindValue(':id', $station_id, PDO::PARAM_INT);
        $upd->bindValue(':ch', $next, PDO::PARAM_INT);
        $upd->execute();
        return;
    }

    $ins = $db->prepare('INSERT INTO display (display_id, reset, ch) VALUES (:id, :reset, :ch)');
    $ins->bindValue(':id', $station_id, PDO::PARAM_INT);
    $ins->bindValue(':reset', date('Y-m-d H:i:s'));
    $ins->bindValue(':ch', 1, PDO::PARAM_INT);
    $ins->execute();
}

function content_fetch_published_rows(PDO $db, int $station_id): array
{
    $stt = $db->prepare(
        'SELECT
             cd.sort_order,
             ci.content_item_id AS id,
             ci.station_id,
             ci.slot_no,
             ci.title,
             ci.content_type,
             ci.content_value,
             ci.start_at,
             ci.end_at,
             ci.is_active,
             ci.note,
             ci.updated_at
         FROM content_display cd
         INNER JOIN content_item ci ON ci.content_item_id = cd.content_id
         WHERE cd.station_id = :station_id
           AND ci.is_active = 1
           AND (ci.start_at IS NULL OR ci.start_at <= NOW())
           AND (ci.end_at IS NULL OR ci.end_at >= NOW())
         ORDER BY cd.sort_order ASC, ci.content_item_id ASC'
    );
    $stt->bindValue(':station_id', $station_id, PDO::PARAM_INT);
    $stt->execute();
    return $stt->fetchAll(PDO::FETCH_ASSOC);
}
