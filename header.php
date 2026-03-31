<?php

declare(strict_types=1);

if (!function_exists('shared_header_escape')) {
    function shared_header_escape($value): string
    {
        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }
}

$shared_header_station_name = trim((string)($shared_header_station_name ?? ''));
$shared_header_page_title = trim((string)($shared_header_page_title ?? ''));
$shared_header_station_id = (int)($shared_header_station_id ?? 0);
$shared_header_master_href = 'master.php';
if ($shared_header_station_id > 0) {
    $shared_header_master_href .= '?s=' . $shared_header_station_id;
}
?>
<header class="shared-page-header <?php print(app_header_classes('mb-4')); ?>">
    <div class="shared-page-header-copy relative z-[1] space-y-3">
        <div class="shared-page-header-station text-[clamp(1.85rem,3vw,3.2rem)] font-extrabold leading-none tracking-[0.01em] text-slate-950"><?php print(shared_header_escape($shared_header_station_name . '港')); ?></div>
        <div class="shared-page-header-title max-w-3xl text-sm font-medium tracking-[0.02em] text-slate-500 sm:text-base"><?php print(shared_header_escape($shared_header_page_title . '画面')); ?></div>
    </div>
    <div class="relative z-[1] flex flex-wrap items-center gap-3">
        <a class="shared-page-header-link <?php print(app_button_classes('secondary')); ?>" href="<?php print(shared_header_escape($shared_header_master_href)); ?>">マスタ画面へ</a>
    </div>
</header>