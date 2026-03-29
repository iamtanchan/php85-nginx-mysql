<?php
declare(strict_types=1);

session_start();
setlocale(LC_ALL, 'ja_JP.UTF-8');
date_default_timezone_set('Asia/Tokyo');
require_once __DIR__ . '/database.php';

$db = app_create_database_connection();

const MESSAGE_DRAG_SPEED_DEFAULT = 4;
const MESSAGE_DRAG_SPEED_MIN = 1;
const MESSAGE_DRAG_SPEED_MAX = 10;
const APP_TAILWIND_CDN_URL = 'https://cdn.tailwindcss.com?plugins=forms,typography';
const APP_THEME_META_COLOR = '#0f172a';

function h($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function app_tw(...$parts): string
{
    $classes = array();
    foreach ($parts as $part) {
        if (is_array($part)) {
            $classes[] = app_tw(...$part);
            continue;
        }

        $value = trim((string)$part);
        if ($value !== '') {
            $classes[] = $value;
        }
    }

    return implode(' ', $classes);
}

function app_body_classes(string $variant = 'default'): string
{
    if ($variant === 'login') {
        return app_tw(
            'min-h-screen bg-gradient-to-br from-slate-950 via-slate-900 to-blue-700',
            'font-sans text-slate-900 antialiased'
        );
    }

    if ($variant === 'display') {
        return app_tw(
            'min-h-screen bg-gradient-to-b from-slate-950 via-slate-900 to-blue-950',
            'font-sans text-white antialiased'
        );
    }

    return app_tw(
        'min-h-screen bg-gradient-to-b from-slate-50 via-white to-blue-50',
        'font-sans text-slate-900 antialiased'
    );
}

function app_page_shell_classes(string $width = 'max-w-[1500px]'): string
{
    return app_tw(
        'mx-auto w-full',
        $width,
        'px-4 pb-10 pt-6 sm:px-6 lg:px-8'
    );
}

function app_surface_classes(string $extra = ''): string
{
    return app_tw(
        'rounded-[30px] border border-slate-200 bg-white shadow-xl shadow-slate-950/5',
        $extra
    );
}

function app_panel_classes(string $extra = ''): string
{
    return app_surface_classes(app_tw('px-5 py-5 sm:px-6 sm:py-6', $extra));
}

function app_header_classes(string $extra = ''): string
{
    return app_surface_classes(app_tw(
        'relative overflow-hidden px-5 py-5 sm:px-6 sm:py-6',
        'flex flex-col gap-5 lg:flex-row lg:items-end lg:justify-between',
        $extra
    ));
}

function app_button_classes(string $variant = 'secondary', string $size = 'md', string $extra = ''): string
{
    $base = 'inline-flex items-center justify-center gap-2 rounded-full border font-semibold tracking-[0.01em] transition duration-200 ease-out focus:outline-none focus:ring-4 focus:ring-blue-100 disabled:cursor-not-allowed disabled:opacity-45 disabled:shadow-none disabled:translate-y-0';

    $sizes = array(
        'sm' => 'min-h-[2.5rem] px-4 py-2 text-sm',
        'md' => 'min-h-[2.9rem] px-5 py-3 text-sm',
        'lg' => 'min-h-[3.15rem] px-6 py-3.5 text-[15px]',
    );

    $variants = array(
        'primary' => 'border-blue-600 bg-blue-600 text-white shadow-lg shadow-blue-600/20 hover:-translate-y-0.5 hover:bg-blue-500 hover:shadow-xl hover:shadow-blue-600/25',
        'secondary' => 'border-slate-200 bg-white text-slate-700 shadow-md shadow-slate-950/5 hover:-translate-y-0.5 hover:border-blue-200 hover:text-blue-700 hover:shadow-lg hover:shadow-slate-950/10',
        'soft' => 'border-blue-100 bg-blue-50 text-blue-700 shadow-sm shadow-blue-100/80 hover:-translate-y-0.5 hover:border-blue-200 hover:bg-blue-100',
        'danger' => 'border-rose-200 bg-rose-50 text-rose-700 shadow-md shadow-rose-200/50 hover:-translate-y-0.5 hover:border-rose-300 hover:bg-rose-100',
        'ghost' => 'border-transparent bg-transparent text-slate-500 shadow-none hover:text-slate-900 hover:bg-slate-100/80',
        'dark' => 'border-slate-950 bg-slate-950 text-white shadow-lg shadow-slate-950/20 hover:-translate-y-0.5 hover:bg-slate-900',
    );

    return app_tw(
        $base,
        $sizes[$size] ?? $sizes['md'],
        $variants[$variant] ?? $variants['secondary'],
        $extra
    );
}

function app_badge_classes(string $variant = 'neutral', string $extra = ''): string
{
    $base = 'inline-flex items-center gap-2 rounded-full px-3 py-1 text-xs font-semibold tracking-[0.14em] uppercase';
    $variants = array(
        'neutral' => 'bg-slate-100 text-slate-600',
        'brand' => 'bg-blue-50 text-blue-700',
        'success' => 'bg-emerald-100 text-emerald-700',
        'warning' => 'bg-amber-100 text-amber-700',
        'danger' => 'bg-rose-100 text-rose-700',
        'dark' => 'bg-slate-900 text-slate-100',
    );

    return app_tw($base, $variants[$variant] ?? $variants['neutral'], $extra);
}

function app_alert_classes(string $type = 'success', string $extra = ''): string
{
    $base = 'mb-5 rounded-[24px] border px-5 py-4 text-sm font-medium shadow-lg shadow-slate-950/5';
    $variants = array(
        'success' => 'border-emerald-200 bg-emerald-50 text-emerald-800',
        'error' => 'border-rose-200 bg-rose-50 text-rose-800',
        'warning' => 'border-amber-200 bg-amber-50 text-amber-800',
        'info' => 'border-sky-200 bg-sky-50 text-sky-800',
    );

    return app_tw($base, $variants[$type] ?? $variants['info'], $extra);
}

function app_field_classes(string $extra = ''): string
{
    return app_tw('space-y-2.5', $extra);
}

function app_label_classes(string $extra = ''): string
{
    return app_tw('block text-sm font-semibold tracking-[0.01em] text-slate-700', $extra);
}

function app_input_classes(string $extra = ''): string
{
    return app_tw(
        'block w-full rounded-2xl border border-slate-200 bg-white/90 px-4 py-3 text-[15px] text-slate-900 shadow-[inset_0_1px_0_rgba(255,255,255,0.8)] transition placeholder:text-slate-400',
        'focus:border-blue-500 focus:outline-none focus:ring-4 focus:ring-blue-100',
        $extra
    );
}

function app_select_classes(string $extra = ''): string
{
    return app_input_classes(app_tw('pr-10', $extra));
}

function app_textarea_classes(string $extra = ''): string
{
    return app_input_classes(app_tw('min-h-[10rem] resize-y', $extra));
}

function app_help_classes(string $extra = ''): string
{
    return app_tw('text-sm leading-6 text-slate-500', $extra);
}

function app_table_frame_classes(string $extra = ''): string
{
    return app_tw(
        'overflow-hidden rounded-[28px] border border-slate-200/80 bg-white shadow-lg shadow-slate-950/5',
        $extra
    );
}

function app_table_classes(string $extra = ''): string
{
    return app_tw('min-w-full border-separate border-spacing-0 text-left text-sm text-slate-700', $extra);
}

function app_modal_root_classes(string $extra = ''): string
{
    return app_tw(
        'fixed inset-0 z-50 hidden items-center justify-center bg-slate-950/55 p-4 backdrop-blur-md',
        $extra
    );
}

function app_modal_dialog_classes(string $size = 'md', string $extra = ''): string
{
    $sizes = array(
        'sm' => 'w-full max-w-md',
        'md' => 'w-full max-w-xl',
        'lg' => 'w-full max-w-3xl',
        'xl' => 'w-full max-w-5xl',
    );

    return app_tw($sizes[$size] ?? $sizes['md'], $extra);
}

function app_modal_card_classes(string $extra = ''): string
{
    return app_surface_classes(app_tw('overflow-hidden bg-white/95', $extra));
}

function app_modal_header_classes(string $extra = ''): string
{
    return app_tw('flex items-start justify-between gap-4 border-b border-slate-200/70 px-5 py-5 sm:px-6', $extra);
}

function app_modal_body_classes(string $extra = ''): string
{
    return app_tw('space-y-5 px-5 py-5 sm:px-6', $extra);
}

function app_modal_footer_classes(bool $between = false, string $extra = ''): string
{
    return app_tw(
        'flex flex-col-reverse gap-3 border-t border-slate-200/70 px-5 py-5 sm:flex-row sm:items-center',
        $between ? 'sm:justify-between' : 'sm:justify-end',
        $extra
    );
}

function app_toggle_button_classes(bool $enabled, string $extra = ''): string
{
    return app_tw(
        'inline-flex items-center gap-2 rounded-full border px-2 py-2 text-xs font-semibold tracking-[0.18em] uppercase transition focus:outline-none focus:ring-4 focus:ring-blue-100',
        $enabled
            ? 'border-blue-600 bg-blue-600 text-white shadow-[0_10px_20px_rgba(37,99,235,0.18)]'
            : 'border-slate-200 bg-slate-100 text-slate-500',
        $extra
    );
}

function app_toggle_switch_classes(bool $enabled, string $extra = ''): string
{
    return app_tw(
        'block h-6 w-11 rounded-full p-1 transition',
        $enabled ? 'bg-blue-700' : 'bg-slate-300',
        $extra
    );
}

function app_toggle_knob_classes(bool $enabled, string $extra = ''): string
{
    return app_tw(
        'block h-4 w-4 rounded-full bg-white shadow transition-transform',
        $enabled ? 'translate-x-5' : 'translate-x-0',
        $extra
    );
}

function render_app_head(string $title, array $options = array()): void
{
    print('<meta charset="UTF-8">' . "\n");
    print('<meta name="viewport" content="width=device-width, initial-scale=1.0">' . "\n");
    print('<meta name="theme-color" content="' . h(APP_THEME_META_COLOR) . '">' . "\n");
    print('<title>' . h($title) . '</title>' . "\n");
    print('<script src="' . h(APP_TAILWIND_CDN_URL) . '"></script>' . "\n");

    if (!empty($options['jquery'])) {
        print(render_app_script_tag('scripts/jquery-3.2.1.min.js') . "\n");
    }

    if (isset($options['head_scripts']) && is_array($options['head_scripts'])) {
        foreach ($options['head_scripts'] as $script) {
            $markup = render_app_script_tag($script);
            if ($markup !== '') {
                print($markup . "\n");
            }
        }
    }
}

function render_app_scripts(array $scripts = array(), bool $include_app_modal = true): void
{
    render_app_confirm_modal();

    $app_scripts = array('js/app_confirm.js?v=2.0.0');
    if ($include_app_modal) {
        array_unshift($app_scripts, 'js/app_modal.js?v=1.0.0');
    }
    $app_scripts = array_merge($app_scripts, $scripts);
    foreach ($app_scripts as $script) {
        $markup = render_app_script_tag($script);
        if ($markup !== '') {
            print($markup . "\n");
        }
    }
}

function render_app_confirm_modal(): void
{
    print(
        '<div hidden aria-hidden="true" class="' .
        h(app_tw(
            'animate-pulse border-amber-300 bg-amber-300 border-blue-300 bg-blue-300 border-fuchsia-300 bg-fuchsia-300 border-rose-300 bg-rose-300',
            'bg-blue-50 ring-2 ring-blue-300 opacity-60 scale-[0.98] w-3 w-10',
            'z-[60] max-w-5xl aspect-video h-11 w-11 bg-slate-950/70 shadow-[0_30px_120px_rgba(15,23,42,0.45)]',
            'shadow-[0_10px_20px_rgba(251,191,36,0.28)] shadow-[0_10px_20px_rgba(59,130,246,0.22)] shadow-[0_10px_20px_rgba(251,113,133,0.22)] shadow-[0_10px_20px_rgba(232,121,249,0.22)]'
        )) .
        '"></div>' .
        '<div class="app-modal app-confirm-modal ' . h(app_modal_root_classes()) . '" id="appConfirmModal" tabindex="-1" role="dialog" aria-modal="true" aria-labelledby="appConfirmTitle" aria-hidden="true" hidden>' .
        '<div class="app-modal-dialog app-modal-dialog-sm ' . h(app_modal_dialog_classes('sm')) . '">' .
        '<div class="app-modal-card ' . h(app_modal_card_classes()) . '">' .
        '<div class="app-modal-body app-confirm-body ' . h(app_modal_body_classes()) . '">' .
        '<h2 class="sr-only" id="appConfirmTitle">確認</h2>' .
        '<p class="app-confirm-message text-base font-medium leading-7 text-slate-800" data-app-confirm-message></p>' .
        '</div>' .
        '<div class="app-modal-footer app-modal-footer-between ' . h(app_modal_footer_classes(true)) . '">' .
        '<button type="button" class="adm-btn adm-btn-soft ' . h(app_button_classes('soft')) . '" data-app-confirm-cancel data-modal-close>キャンセル</button>' .
        '<button type="button" class="adm-btn adm-btn-danger ' . h(app_button_classes('danger')) . '" data-app-confirm-submit>実行する</button>' .
        '</div>' .
        '</div>' .
        '</div>' .
        '</div>' . "\n"
    );
}

function render_app_script_tag($script): string
{
    $src = '';
    $defer = false;
    $type = 'text/javascript';
    $attributes = array();

    if (is_array($script)) {
        $src = trim((string)($script['src'] ?? ''));
        $defer = !empty($script['defer']);
        $type_candidate = trim((string)($script['type'] ?? ''));
        if ($type_candidate !== '') {
            $type = $type_candidate;
        }
        if (isset($script['attributes']) && is_array($script['attributes'])) {
            $attributes = $script['attributes'];
        }
    } else {
        $src = trim((string)$script);
    }

    if ($src === '') {
        return '';
    }

    $parts = array('<script src="' . h($src) . '"');
    if ($type !== 'text/javascript') {
        $parts[] = 'type="' . h($type) . '"';
    }
    if ($defer) {
        $parts[] = 'defer';
    }
    foreach ($attributes as $name => $value) {
        $name = trim((string)$name);
        if ($name === '') {
            continue;
        }
        if ($value === true) {
            $parts[] = h($name);
            continue;
        }
        if ($value === false || $value === null) {
            continue;
        }
        $parts[] = h($name) . '="' . h((string)$value) . '"';
    }
    $parts[] = '></script>';

    return implode(' ', $parts);
}

function require_login(PDO $db): string
{
    $login = isset($_SESSION['LOGIN']) ? (string)$_SESSION['LOGIN'] : '';
    if ($login === '') {
        header('Location: login.php');
        exit;
    }

    /** @noinspection SqlNoDataSourceInspection */
    $stt = $db->prepare('SELECT login_id FROM login WHERE login_id = :id LIMIT 1');
    $stt->bindValue(':id', $login);
    $stt->execute();
    if (!$stt->fetch(PDO::FETCH_ASSOC)) {
        header('Location: login.php');
        exit;
    }

    return $login;
}

function get_stations(PDO $db): array
{
    /** @noinspection SqlNoDataSourceInspection */
    $stt = $db->prepare('SELECT station_id AS id, name, name_e FROM station ORDER BY station_id');
    $stt->execute();
    return $stt->fetchAll(PDO::FETCH_ASSOC);
}

function get_active_stations(array $stations): array
{
    $result = array();
    foreach ($stations as $station) {
        $id = isset($station['id']) ? (int)$station['id'] : 0;
        $name = trim((string)($station['name'] ?? ''));
        if ($id > 0 && $name !== '' && $name !== '　') {
            $result[] = $station;
        }
    }

    return count($result) > 0 ? $result : $stations;
}

function resolve_station_id(array $stations, int $requested): int
{
    if ($requested > 0) {
        foreach ($stations as $station) {
            if ((int)$station['id'] === $requested) {
                return $requested;
            }
        }
    }

    foreach ($stations as $station) {
        $id = (int)$station['id'];
        if ($id > 0) {
            return $id;
        }
    }

    return 1;
}

function get_station_name(array $stations, int $station_id): string
{
    foreach ($stations as $station) {
        if ((int)$station['id'] === $station_id) {
            return (string)$station['name'];
        }
    }

    return '';
}

function current_day_bounds(): array
{
    $start = new DateTimeImmutable('today');
    $end = $start->modify('+1 day');

    return array(
        'start' => $start->format('Y-m-d H:i:s'),
        'end' => $end->format('Y-m-d H:i:s'),
        'date' => $start->format('Y-m-d'),
    );
}

function normalize_message_drag_speed(int $speed): int
{
    if ($speed <= 0) {
        return MESSAGE_DRAG_SPEED_DEFAULT;
    }

    if ($speed <= MESSAGE_DRAG_SPEED_MAX) {
        if ($speed < MESSAGE_DRAG_SPEED_MIN) {
            return MESSAGE_DRAG_SPEED_MIN;
        }
        return $speed;
    }

    $level = (int)round(($speed - 20) / 20) + 1;
    if ($level < MESSAGE_DRAG_SPEED_MIN) {
        return MESSAGE_DRAG_SPEED_MIN;
    }
    if ($level > MESSAGE_DRAG_SPEED_MAX) {
        return MESSAGE_DRAG_SPEED_MAX;
    }

    return $level;
}

function fetch_station_message_settings(PDO $db, int $station_id): array
{
    $default = array(
        'station_id' => $station_id,
        'drag_speed' => MESSAGE_DRAG_SPEED_DEFAULT,
    );

    if ($station_id <= 0) {
        return $default;
    }

    /** @noinspection SqlNoDataSourceInspection */
    $stt = $db->prepare(
        'SELECT station_id, drag_speed
         FROM message_display_setting
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
        'drag_speed' => normalize_message_drag_speed((int)$row['drag_speed']),
    );
}

function ensure_station_display_language_setting_schema(PDO $db): void
{
    static $initialized = false;
    if ($initialized) {
        return;
    }

    /** @noinspection SqlNoDataSourceInspection */
    $db->exec(
        'CREATE TABLE IF NOT EXISTS display_language_setting (
            station_id int NOT NULL,
            english_enabled tinyint(1) NOT NULL DEFAULT 0,
            created_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (station_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci'
    );

    $initialized = true;
}

function fetch_station_display_language_settings(PDO $db, int $station_id): array
{
    $default = array(
        'station_id' => $station_id,
        'english_enabled' => false,
    );

    if ($station_id <= 0) {
        return $default;
    }

    ensure_station_display_language_setting_schema($db);

    /** @noinspection SqlNoDataSourceInspection */
    $stt = $db->prepare(
        'SELECT station_id, english_enabled
         FROM display_language_setting
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
        'english_enabled' => (int)$row['english_enabled'] === 1,
    );
}

function update_station_display_language_settings(PDO $db, int $station_id, bool $english_enabled): void
{
    if ($station_id <= 0) {
        throw new RuntimeException('station_id is required');
    }

    ensure_station_display_language_setting_schema($db);

    /** @noinspection SqlNoDataSourceInspection */
    $stt = $db->prepare(
        'INSERT INTO display_language_setting (station_id, english_enabled)
         VALUES (:station_id, :english_enabled)
         ON DUPLICATE KEY UPDATE
             english_enabled = VALUES(english_enabled)'
    );
    $stt->bindValue(':station_id', $station_id, PDO::PARAM_INT);
    $stt->bindValue(':english_enabled', $english_enabled ? 1 : 0, PDO::PARAM_INT);
    $stt->execute();
}

function update_station_message_settings(PDO $db, int $station_id, int $drag_speed): void
{
    if ($station_id <= 0) {
        throw new RuntimeException('station_id is required');
    }

    $drag_speed = normalize_message_drag_speed($drag_speed);

    /** @noinspection SqlNoDataSourceInspection */
    $stt = $db->prepare(
        'INSERT INTO message_display_setting (station_id, drag_speed)
         VALUES (:station_id, :drag_speed)
         ON DUPLICATE KEY UPDATE
             drag_speed = VALUES(drag_speed)'
    );
    $stt->bindValue(':station_id', $station_id, PDO::PARAM_INT);
    $stt->bindValue(':drag_speed', $drag_speed, PDO::PARAM_INT);
    $stt->execute();
}

function fetch_station_message_rows(PDO $db, int $station_id): array
{
    if ($station_id <= 0) {
        return array();
    }

    /** @noinspection SqlNoDataSourceInspection */
    $stt = $db->prepare(
        'SELECT message_id, station_id, message, message_e, sort_order, is_visible, created_at, updated_at
         FROM message
         WHERE station_id = :station_id
         ORDER BY sort_order ASC, message_id ASC'
    );
    $stt->bindValue(':station_id', $station_id, PDO::PARAM_INT);
    $stt->execute();
    return $stt->fetchAll(PDO::FETCH_ASSOC);
}

function fetch_visible_station_message_rows(PDO $db, int $station_id): array
{
    if ($station_id <= 0) {
        return array();
    }

    /** @noinspection SqlNoDataSourceInspection */
    $stt = $db->prepare(
        'SELECT message_id, station_id, message, message_e, sort_order, is_visible, created_at, updated_at
         FROM message
         WHERE station_id = :station_id AND is_visible = 1
         ORDER BY sort_order ASC, message_id ASC'
    );
    $stt->bindValue(':station_id', $station_id, PDO::PARAM_INT);
    $stt->execute();
    return $stt->fetchAll(PDO::FETCH_ASSOC);
}

function fetch_station_message_text(PDO $db, int $station_id): string
{
    $rows = fetch_visible_station_message_rows($db, $station_id);
    $messages = array();

    foreach ($rows as $row) {
        $message = trim((string)($row['message'] ?? ''));
        if ($message !== '') {
            $messages[] = $message;
        }
    }

    return implode("\n", $messages);
}

function redirect_with_params(string $page, array $params = array()): void
{
    $url = $page;
    if (count($params) > 0) {
        $url .= '?' . http_build_query($params);
    }

    header('Location: ' . $url);
    exit;
}

function parse_datetime_local(?string $value): ?string
{
    $raw = trim((string)$value);
    if ($raw === '') {
        return null;
    }

    $dt = DateTime::createFromFormat('Y-m-d\TH:i', $raw);
    if (!$dt) {
        return null;
    }

    return $dt->format('Y-m-d H:i:s');
}
