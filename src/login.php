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
  $count = 0;
  try{
    $sql= 'SELECT * FROM login WHERE login_id = :id AND pass = :pass';
    $stt = $db->prepare($sql);
    $stt->bindValue(':id', $user);
    $stt->bindValue(':pass', $pass);
    $stt->execute();
    $count = $stt->rowCount();
  } catch(PDOException $e){
		die('エラーメッセージ'.$e->getMessage());
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
  }
  else {
    $errorMessage = "ユーザIDあるいはパスワードに誤りがあります。";
  }
}
  
?>
<!doctype html>
<html lang="ja">
<head>
    <?php render_app_head('時刻表管理システム'); ?>
</head>
<body class="<?php print(app_body_classes('login')); ?>">
<div class="app-login-shell relative isolate overflow-hidden">
    <div class="mx-auto flex min-h-screen w-full max-w-[640px] items-center px-4 py-8 sm:px-6 lg:px-8">
        <div class="w-full">
            <div class="app-login-card overflow-hidden rounded-[36px] border border-white/15 bg-white/10 shadow-[0_30px_120px_rgba(15,23,42,0.28)] backdrop-blur-xl">
                <section class="bg-white/94 px-6 py-8 sm:px-8 sm:py-10 lg:px-10 lg:py-12">
                    <div class="space-y-3">
                        <h2 class="app-login-panel-title text-3xl font-bold tracking-[0.01em] text-slate-950">ログイン</h2>
                        <p class="app-login-panel-copy text-sm leading-7 text-slate-500">管理画面に入るには認証が必要です。</p>
                    </div>

                    <?php if ($errorMessage !== '') { ?>
                        <div class="<?php print(app_alert_classes('error', 'mt-6')); ?>" role="alert"><?php print(h($errorMessage)); ?></div>
                    <?php } ?>

                    <form class="app-login-form mt-8 space-y-5" action="" method="POST">
                        <div class="<?php print(app_field_classes()); ?>">
                            <label class="<?php print(app_label_classes()); ?>" for="userid">ユーザー名</label>
                            <input
                                type="text"
                                id="userid"
                                name="user"
                                class="<?php print(app_input_classes('min-h-[3.5rem] text-base')); ?>"
                                value="<?php print(h($viewUserId)); ?>"
                                required
                            >
                        </div>

                        <div class="<?php print(app_field_classes()); ?>">
                            <label class="<?php print(app_label_classes()); ?>" for="password">パスワード</label>
                            <input
                                type="password"
                                id="password"
                                name="pass"
                                class="<?php print(app_input_classes('min-h-[3.5rem] text-base')); ?>"
                                value=""
                                required
                            >
                        </div>

                        <label class="inline-flex items-center gap-3 rounded-full bg-slate-100/90 px-4 py-3 text-sm font-medium text-slate-500">
                            <input class="h-4 w-4 rounded border-slate-300 text-blue-600 focus:ring-blue-200" type="checkbox" value="1" id="keepLogin" name="select">
                            <span>ログイン状態を保存する</span>
                        </label>

                        <div class="flex flex-wrap items-center gap-3 pt-2">
                            <input type="submit" class="adm-btn adm-btn-pink <?php print(app_button_classes('primary', 'lg', 'w-full sm:w-auto')); ?>" id="btnlogin" name="login" value="ログイン">
                        </div>
                    </form>

                    <div class="app-login-footer mt-10 border-t border-slate-200/80 pt-5 text-sm text-slate-400">
                        <a class="transition hover:text-slate-600" href="http://www.amazing-pocket.com">© AmazingPocket Inc.</a>
                    </div>
                </section>
            </div>
        </div>
    </div>
</div>
<?php render_app_scripts(); ?>
</body>
</html>
