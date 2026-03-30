<?php
require_once __DIR__ . '/lib/admin_bootstrap.php';

// エラーメッセージ
$errorMessage = "";
// 画面に表示するため特殊文字をエスケープする
$viewUserId = "";
// ログインボタンが押された場合      
if (isset($_POST["login"])) {

    $user = $_POST["user"];
    $pass = $_POST["pass"];
    $viewUserId = $user;
    $count = 0;
    try {
        $sql = 'SELECT * FROM login WHERE login_id = :id AND pass = :pass';
        $stt = $db->prepare($sql);
        $stt->bindValue(':id', $user);
        $stt->bindValue(':pass', $pass);
        $stt->execute();
        $count = $stt->rowCount();
    } catch (PDOException $e) {
        die('エラーメッセージ' . $e->getMessage());
    }
    // 認証成功
    if ($count == 1) {
        // セッションIDを新規に発行する
        // Regenerate the session ID on login to prevent session fixation.
        session_regenerate_id(TRUE);

        $row = $stt->fetch();
        $_SESSION["auth"] = $row['auth'];
        $_SESSION["LOGIN"] = $user;
        header('Location: master.php?s=1');
        exit;
    } else {
        $errorMessage = "ユーザIDあるいはパスワードに誤りがあります。";
    }
}

?>
<!doctype html>
<html lang="ja">

<head>
    <?php render_app_head('時刻表管理システム'); ?>
</head>

<body class="<?php print(app_body_classes()); ?>">
    <div class="adm-page">
        <main class="<?php print(app_page_shell_classes('max-w-xl')); ?> flex min-h-screen flex-col justify-center">
            <section class="adm-panel <?php print(app_panel_classes()); ?>">
                <div class="space-y-2 pb-5">
                    <h1 class="text-2xl font-bold tracking-[0.01em] text-slate-950">ログイン</h1>
                    <p class="text-sm leading-7 text-slate-500">管理画面に入るには認証が必要です。ユーザー名とパスワードを入力してください。</p>
                </div>

                <?php if ($errorMessage !== '') { ?>
                    <div class="<?php print(app_alert_classes('error', 'mb-5')); ?>" role="alert"><?php print(h($errorMessage)); ?></div>
                <?php } ?>

                <form class="space-y-5" action="" method="POST">
                    <div class="<?php print(app_field_classes()); ?>">
                        <label class="<?php print(app_label_classes()); ?>" for="userid">ユーザー名</label>
                        <input
                            type="text"
                            id="userid"
                            name="user"
                            class="<?php print(app_input_classes('min-h-[3.25rem]')); ?>"
                            value="<?php print(h($viewUserId)); ?>"
                            required
                            autofocus>
                    </div>

                    <div class="<?php print(app_field_classes()); ?>">
                        <label class="<?php print(app_label_classes()); ?>" for="password">パスワード</label>
                        <input
                            type="password"
                            id="password"
                            name="pass"
                            class="<?php print(app_input_classes('min-h-[3.25rem]')); ?>"
                            value=""
                            required>
                    </div>

                    <label class="inline-flex items-center gap-3 text-sm text-slate-500">
                        <input class="h-4 w-4 rounded border-slate-300 text-blue-600 focus:ring-blue-200" type="checkbox" value="1" id="keepLogin" name="select">
                        <span>ログイン状態を保存する</span>
                    </label>

                    <div class="flex items-center pt-2">
                        <input type="submit" class="adm-btn adm-btn-pink <?php print(app_button_classes('primary', 'lg')); ?>" id="btnlogin" name="login" value="ログイン">
                    </div>
                </form>
            </section>

            <div class="app-credit mt-6 text-center text-sm text-slate-500">
                <a class="transition hover:text-slate-700" href="http://www.amazing-pocket.com">© AmazingPocket Inc.</a>
            </div>
        </main>
    </div>
    <?php render_app_scripts(); ?>
</body>

</html>