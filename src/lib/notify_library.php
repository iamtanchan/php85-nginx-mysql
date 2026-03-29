<?php
declare(strict_types=1);

const NOTIFY_STATE_FILE = __DIR__ . '/../data/notify_state.json';
const NOTIFY_UPLOAD_DIR = __DIR__ . '/../uploads/notify';
const NOTIFY_UPLOAD_WEB_PATH = 'uploads/notify';

function notify_definitions(): array
{
    return array(
        'service_stop' => array(
            'key' => 'service_stop',
            'label' => '運休',
        ),
        'schedule_review' => array(
            'key' => 'schedule_review',
            'label' => 'ダイヤ検討中',
        ),
        'closed' => array(
            'key' => 'closed',
            'label' => '営業終了',
        ),
    );
}

function notify_default_state(): array
{
    $items = array();
    foreach (notify_definitions() as $key => $definition) {
        $items[$key] = array(
            'key' => $key,
            'label' => (string)$definition['label'],
            'title' => (string)$definition['label'],
            'image_path' => '',
            'updated_at' => '',
        );
    }

    return array(
        'selected_by_station' => array(),
        'items' => $items,
        'updated_at' => '',
    );
}

function notify_state_path(): string
{
    return NOTIFY_STATE_FILE;
}

function notify_load_state(): array
{
    $default = notify_default_state();
    $path = notify_state_path();
    if (!is_file($path)) {
        return $default;
    }

    $raw = file_get_contents($path);
    if ($raw === false || trim($raw) === '') {
        return $default;
    }

    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) {
        return $default;
    }

    $state = $default;
    $selected_by_station = isset($decoded['selected_by_station']) && is_array($decoded['selected_by_station'])
        ? $decoded['selected_by_station']
        : array();
    foreach ($selected_by_station as $station_key => $selected_key) {
        $station_id = (int)$station_key;
        $selected_key = trim((string)$selected_key);
        if ($station_id > 0 && isset($state['items'][$selected_key])) {
            $state['selected_by_station'][(string)$station_id] = $selected_key;
        }
    }

    $state['updated_at'] = trim((string)($decoded['updated_at'] ?? ''));
    $items = isset($decoded['items']) && is_array($decoded['items']) ? $decoded['items'] : array();
    foreach ($state['items'] as $key => $item) {
        $loaded_item = isset($items[$key]) && is_array($items[$key]) ? $items[$key] : array();
        $image_path = trim((string)($loaded_item['image_path'] ?? ''));
        $state['items'][$key] = array(
            'key' => $key,
            'label' => (string)$item['label'],
            'title' => trim((string)($loaded_item['title'] ?? $item['label'])) ?: (string)$item['label'],
            'image_path' => $image_path,
            'updated_at' => trim((string)($loaded_item['updated_at'] ?? '')),
        );
    }

    return $state;
}

function notify_save_state(array $state): void
{
    $defaults = notify_default_state();
    $data = $defaults;
    $selected_by_station = isset($state['selected_by_station']) && is_array($state['selected_by_station'])
        ? $state['selected_by_station']
        : array();
    foreach ($selected_by_station as $station_key => $selected_key) {
        $station_id = (int)$station_key;
        $selected_key = trim((string)$selected_key);
        if ($station_id > 0 && isset($defaults['items'][$selected_key])) {
            $data['selected_by_station'][(string)$station_id] = $selected_key;
        }
    }

    $items = isset($state['items']) && is_array($state['items']) ? $state['items'] : array();
    foreach ($defaults['items'] as $key => $item) {
        $source = isset($items[$key]) && is_array($items[$key]) ? $items[$key] : array();
        $data['items'][$key] = array(
            'key' => $key,
            'label' => (string)$item['label'],
            'title' => trim((string)($source['title'] ?? $item['label'])) ?: (string)$item['label'],
            'image_path' => trim((string)($source['image_path'] ?? '')),
            'updated_at' => trim((string)($source['updated_at'] ?? '')),
        );
    }

    $data['updated_at'] = date('c');

    $dir = dirname(notify_state_path());
    if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
        throw new RuntimeException('通知設定保存先ディレクトリを作成できませんでした。');
    }

    $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($json === false) {
        throw new RuntimeException('通知設定をJSONに変換できませんでした。');
    }

    if (file_put_contents(notify_state_path(), $json . PHP_EOL, LOCK_EX) === false) {
        throw new RuntimeException('通知設定を保存できませんでした。');
    }
}

function notify_selection_options(): array
{
    $options = array(
        '' => '通常表示',
    );

    foreach (notify_definitions() as $key => $definition) {
        $options[$key] = (string)$definition['label'];
    }

    return $options;
}

function notify_get_selected_key_for_station(array $state, int $station_id): string
{
    if ($station_id <= 0) {
        return '';
    }

    $selected_by_station = isset($state['selected_by_station']) && is_array($state['selected_by_station'])
        ? $state['selected_by_station']
        : array();
    $selected_key = trim((string)($selected_by_station[(string)$station_id] ?? ''));
    $definitions = notify_definitions();
    return isset($definitions[$selected_key]) ? $selected_key : '';
}

function notify_set_selected_key_for_station(array $state, int $station_id, string $selected_key): array
{
    if ($station_id <= 0) {
        return $state;
    }

    if (!isset($state['selected_by_station']) || !is_array($state['selected_by_station'])) {
        $state['selected_by_station'] = array();
    }

    $definitions = notify_definitions();
    if ($selected_key === '' || !isset($definitions[$selected_key])) {
        unset($state['selected_by_station'][(string)$station_id]);
        return $state;
    }

    $state['selected_by_station'][(string)$station_id] = $selected_key;
    return $state;
}

function notify_generate_token(): string
{
    try {
        return bin2hex(random_bytes(8));
    } catch (Throwable $e) {
        return str_replace('.', '', uniqid('', true));
    }
}

function notify_store_uploaded_image(array $file, string $key): string
{
    $definitions = notify_definitions();
    if (!isset($definitions[$key])) {
        throw new RuntimeException('不正な通知種別です。');
    }

    $error = (int)($file['error'] ?? UPLOAD_ERR_NO_FILE);
    if ($error === UPLOAD_ERR_NO_FILE) {
        throw new RuntimeException('画像ファイルを選択してください。');
    }
    if ($error !== UPLOAD_ERR_OK) {
        throw new RuntimeException('画像ファイルのアップロードに失敗しました。');
    }

    $tmp_name = trim((string)($file['tmp_name'] ?? ''));
    if ($tmp_name === '' || !is_uploaded_file($tmp_name)) {
        throw new RuntimeException('アップロード画像を確認できませんでした。');
    }

    $extension = strtolower(pathinfo((string)($file['name'] ?? ''), PATHINFO_EXTENSION));
    if (!in_array($extension, array('jpg', 'jpeg', 'png'), true)) {
        throw new RuntimeException('通知画像は jpg / png のみ登録できます。');
    }

    $image_info = @getimagesize($tmp_name);
    if (!is_array($image_info) || count($image_info) < 2) {
        throw new RuntimeException('画像サイズを確認できませんでした。');
    }

    $width = (int)$image_info[0];
    $height = (int)$image_info[1];
    $mime = trim((string)($image_info['mime'] ?? ''));

    if (!in_array($mime, array('image/jpeg', 'image/png'), true)) {
        throw new RuntimeException('通知画像は jpg / png のみ登録できます。');
    }
    if ($width !== 1920 || $height !== 1080) {
        throw new RuntimeException('通知画像は 1920x1080px のみ登録できます。');
    }

    if (!is_dir(NOTIFY_UPLOAD_DIR) && !mkdir(NOTIFY_UPLOAD_DIR, 0775, true) && !is_dir(NOTIFY_UPLOAD_DIR)) {
        throw new RuntimeException('通知画像の保存先ディレクトリを作成できませんでした。');
    }

    $filename = 'notify_' . $key . '_' . date('Ymd_His') . '_' . notify_generate_token() . '.' . $extension;
    $target_path = NOTIFY_UPLOAD_DIR . DIRECTORY_SEPARATOR . $filename;
    if (!move_uploaded_file($tmp_name, $target_path)) {
        throw new RuntimeException('通知画像を保存できませんでした。');
    }

    return NOTIFY_UPLOAD_WEB_PATH . '/' . $filename;
}

function notify_local_path(string $image_path): ?string
{
    $normalized = str_replace('\\', '/', trim($image_path));
    if ($normalized === '' || strpos($normalized, NOTIFY_UPLOAD_WEB_PATH . '/') !== 0) {
        return null;
    }

    $relative = substr($normalized, strlen(NOTIFY_UPLOAD_WEB_PATH) + 1);
    if ($relative === '' || strpos($relative, '..') !== false) {
        return null;
    }

    return NOTIFY_UPLOAD_DIR . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
}

function notify_delete_image_file(string $image_path): void
{
    $local_path = notify_local_path($image_path);
    if ($local_path !== null && is_file($local_path)) {
        @unlink($local_path);
    }
}

function notify_get_last_departure_for_station(PDO $db, int $station_id): string
{
    if ($station_id <= 0) {
        return '';
    }

    $day_bounds = current_day_bounds();
    $stt = $db->prepare(
        'SELECT MAX(departure_time) AS departure_time
         FROM timetable
         WHERE station_id = :station_id
           AND created_at >= :day_start
           AND created_at < :day_end'
    );
    $stt->bindValue(':station_id', $station_id, PDO::PARAM_INT);
    $stt->bindValue(':day_start', $day_bounds['start']);
    $stt->bindValue(':day_end', $day_bounds['end']);
    $stt->execute();

    $departure_time = trim((string)$stt->fetchColumn());
    return $departure_time;
}

function notify_resolve_active_notice(PDO $db, int $station_id): array
{
    $state = notify_load_state();
    $selected_key = notify_get_selected_key_for_station($state, $station_id);
    $items = isset($state['items']) && is_array($state['items']) ? $state['items'] : array();

    if ($selected_key !== '' && isset($items[$selected_key])) {
        $selected_item = $items[$selected_key];
        $image_path = trim((string)($selected_item['image_path'] ?? ''));
        if ($image_path !== '') {
            return array(
                'active' => true,
                'mode' => 'manual',
                'key' => $selected_key,
                'label' => (string)($selected_item['label'] ?? ''),
                'title' => (string)($selected_item['title'] ?? ''),
                'image_path' => $image_path,
                'updated_at' => (string)($selected_item['updated_at'] ?? ''),
                'last_departure' => '',
            );
        }
    }

    if (isset($items['closed'])) {
        $closed_item = $items['closed'];
        $image_path = trim((string)($closed_item['image_path'] ?? ''));
        if ($image_path !== '') {
            $today = date('Y-m-d');
            $last_departure = notify_get_last_departure_for_station($db, $station_id);
            if ($last_departure !== '') {
                $last_departure_ts = strtotime($today . ' ' . $last_departure);
                if ($last_departure_ts !== false && time() >= $last_departure_ts) {
                    return array(
                        'active' => true,
                        'mode' => 'auto',
                        'key' => 'closed',
                        'label' => (string)($closed_item['label'] ?? ''),
                        'title' => (string)($closed_item['title'] ?? ''),
                        'image_path' => $image_path,
                        'updated_at' => (string)($closed_item['updated_at'] ?? ''),
                        'last_departure' => substr($last_departure, 0, 5),
                    );
                }
            }
        }
    }

    return array(
        'active' => false,
        'mode' => '',
        'key' => '',
        'label' => '',
        'title' => '',
        'image_path' => '',
        'updated_at' => '',
        'last_departure' => '',
    );
}
