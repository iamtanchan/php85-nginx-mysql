<?php
declare(strict_types=1);

const GUIDANCE_STATE_FILE = __DIR__ . '/../data/guidance_state.json';
const GUIDANCE_UPLOAD_DIR = __DIR__ . '/../uploads/guidance';
const GUIDANCE_UPLOAD_WEB_PATH = 'uploads/guidance';
const GUIDANCE_LEAD_MINUTES_DEFAULT = 5;
const GUIDANCE_LEAD_MINUTES_MAX = 30;

function guidance_definitions(): array
{
    return array(
        'pre_boarding' => array(
            'key' => 'pre_boarding',
            'label' => '乗船前の注意事項',
        ),
        'route_guidance' => array(
            'key' => 'route_guidance',
            'label' => 'ルート案内',
        ),
    );
}

function guidance_default_state(): array
{
    $items = array();
    foreach (guidance_definitions() as $key => $definition) {
        $items[$key] = array(
            'key' => $key,
            'label' => (string)$definition['label'],
            'title' => (string)$definition['label'],
            'video_path' => '',
            'updated_at' => '',
        );
    }

    return array(
        'lead_minutes' => GUIDANCE_LEAD_MINUTES_DEFAULT,
        'items' => $items,
        'updated_at' => '',
    );
}

function guidance_state_path(): string
{
    return GUIDANCE_STATE_FILE;
}

function guidance_normalize_lead_minutes(int $minutes): int
{
    if ($minutes < 0) {
        return 0;
    }
    if ($minutes > GUIDANCE_LEAD_MINUTES_MAX) {
        return GUIDANCE_LEAD_MINUTES_MAX;
    }
    return $minutes;
}

function guidance_load_state(): array
{
    $default = guidance_default_state();
    $path = guidance_state_path();
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
    $state['lead_minutes'] = guidance_normalize_lead_minutes((int)($decoded['lead_minutes'] ?? GUIDANCE_LEAD_MINUTES_DEFAULT));
    $state['updated_at'] = trim((string)($decoded['updated_at'] ?? ''));

    $items = isset($decoded['items']) && is_array($decoded['items']) ? $decoded['items'] : array();
    foreach ($state['items'] as $key => $item) {
        $loaded_item = isset($items[$key]) && is_array($items[$key]) ? $items[$key] : array();
        $state['items'][$key] = array(
            'key' => $key,
            'label' => (string)$item['label'],
            'title' => trim((string)($loaded_item['title'] ?? $item['label'])) ?: (string)$item['label'],
            'video_path' => trim((string)($loaded_item['video_path'] ?? '')),
            'updated_at' => trim((string)($loaded_item['updated_at'] ?? '')),
        );
    }

    return $state;
}

function guidance_save_state(array $state): void
{
    $defaults = guidance_default_state();
    $data = $defaults;
    $data['lead_minutes'] = guidance_normalize_lead_minutes((int)($state['lead_minutes'] ?? GUIDANCE_LEAD_MINUTES_DEFAULT));

    $items = isset($state['items']) && is_array($state['items']) ? $state['items'] : array();
    foreach ($defaults['items'] as $key => $item) {
        $source = isset($items[$key]) && is_array($items[$key]) ? $items[$key] : array();
        $data['items'][$key] = array(
            'key' => $key,
            'label' => (string)$item['label'],
            'title' => trim((string)($source['title'] ?? $item['label'])) ?: (string)$item['label'],
            'video_path' => trim((string)($source['video_path'] ?? '')),
            'updated_at' => trim((string)($source['updated_at'] ?? '')),
        );
    }

    $data['updated_at'] = date('c');

    $dir = dirname(guidance_state_path());
    if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
        throw new RuntimeException('案内動画設定保存先ディレクトリを作成できませんでした。');
    }

    $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($json === false) {
        throw new RuntimeException('案内動画設定をJSONに変換できませんでした。');
    }

    if (file_put_contents(guidance_state_path(), $json . PHP_EOL, LOCK_EX) === false) {
        throw new RuntimeException('案内動画設定を保存できませんでした。');
    }
}

function guidance_generate_token(): string
{
    try {
        return bin2hex(random_bytes(8));
    } catch (Throwable $e) {
        return str_replace('.', '', uniqid('', true));
    }
}

function guidance_store_uploaded_video(array $file, string $key): string
{
    $definitions = guidance_definitions();
    if (!isset($definitions[$key])) {
        throw new RuntimeException('不正な案内動画種別です。');
    }

    $error = (int)($file['error'] ?? UPLOAD_ERR_NO_FILE);
    if ($error === UPLOAD_ERR_NO_FILE) {
        throw new RuntimeException('動画ファイルを選択してください。');
    }
    if ($error !== UPLOAD_ERR_OK) {
        throw new RuntimeException('動画ファイルのアップロードに失敗しました。');
    }

    $tmp_name = trim((string)($file['tmp_name'] ?? ''));
    if ($tmp_name === '' || !is_uploaded_file($tmp_name)) {
        throw new RuntimeException('アップロード動画を確認できませんでした。');
    }

    $extension = strtolower(pathinfo((string)($file['name'] ?? ''), PATHINFO_EXTENSION));
    $allowed_extensions = array('mp4', 'webm', 'ogv', 'mov', 'm4v');
    if ($extension === '' || !in_array($extension, $allowed_extensions, true)) {
        throw new RuntimeException('案内動画は mp4 / webm / ogv / mov / m4v のみ登録できます。');
    }

    if (function_exists('finfo_open')) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        if ($finfo !== false) {
            $mime = (string)finfo_file($finfo, $tmp_name);
            if (is_resource($finfo)) {
                finfo_close($finfo);
            }
            $allowed_mimes = array('video/mp4', 'video/webm', 'video/ogg', 'application/ogg', 'video/quicktime', 'video/x-m4v');
            if ($mime !== '' && !in_array($mime, $allowed_mimes, true)) {
                throw new RuntimeException('案内動画の形式を判定できませんでした。');
            }
        }
    }

    if (!is_dir(GUIDANCE_UPLOAD_DIR) && !mkdir(GUIDANCE_UPLOAD_DIR, 0775, true) && !is_dir(GUIDANCE_UPLOAD_DIR)) {
        throw new RuntimeException('案内動画の保存先ディレクトリを作成できませんでした。');
    }

    $filename = 'guidance_' . $key . '_' . date('Ymd_His') . '_' . guidance_generate_token() . '.' . $extension;
    $target_path = GUIDANCE_UPLOAD_DIR . DIRECTORY_SEPARATOR . $filename;
    if (!move_uploaded_file($tmp_name, $target_path)) {
        throw new RuntimeException('案内動画を保存できませんでした。');
    }

    return GUIDANCE_UPLOAD_WEB_PATH . '/' . $filename;
}

function guidance_local_path(string $video_path): ?string
{
    $normalized = str_replace('\\', '/', trim($video_path));
    if ($normalized === '' || strpos($normalized, GUIDANCE_UPLOAD_WEB_PATH . '/') !== 0) {
        return null;
    }

    $relative = substr($normalized, strlen(GUIDANCE_UPLOAD_WEB_PATH) + 1);
    if ($relative === '' || strpos($relative, '..') !== false) {
        return null;
    }

    return GUIDANCE_UPLOAD_DIR . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
}

function guidance_delete_video_file(string $video_path): void
{
    $local_path = guidance_local_path($video_path);
    if ($local_path !== null && is_file($local_path)) {
        @unlink($local_path);
    }
}

function guidance_is_ready(array $state): bool
{
    $items = isset($state['items']) && is_array($state['items']) ? $state['items'] : array();
    foreach (guidance_definitions() as $key => $definition) {
        $video_path = trim((string)($items[$key]['video_path'] ?? ''));
        if ($video_path === '') {
            return false;
        }
    }

    return guidance_normalize_lead_minutes((int)($state['lead_minutes'] ?? 0)) > 0;
}

function guidance_export_payload(array $state): array
{
    $items = array();
    foreach (guidance_definitions() as $key => $definition) {
        $item = isset($state['items'][$key]) && is_array($state['items'][$key]) ? $state['items'][$key] : array();
        $items[] = array(
            'key' => $key,
            'label' => (string)$definition['label'],
            'title' => trim((string)($item['title'] ?? $definition['label'])) ?: (string)$definition['label'],
            'video_path' => trim((string)($item['video_path'] ?? '')),
            'updated_at' => trim((string)($item['updated_at'] ?? '')),
        );
    }

    return array(
        'ready' => guidance_is_ready($state),
        'lead_minutes' => guidance_normalize_lead_minutes((int)($state['lead_minutes'] ?? GUIDANCE_LEAD_MINUTES_DEFAULT)),
        'items' => $items,
        'updated_at' => trim((string)($state['updated_at'] ?? '')),
    );
}
