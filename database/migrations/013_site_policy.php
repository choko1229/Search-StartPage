<?php
declare(strict_types=1);
return new class {
    public function up(PDO $pdo): void
    {
        $value=['flags'=>['cloud_sync'=>true,'background_uploads'=>true,'weather'=>true,'external_suggestions'=>true,'favorite_metadata'=>true],
            'limits'=>['background_max_bytes'=>null,'login_attempts'=>null,'login_window_seconds'=>null]];
        $pdo->prepare("INSERT IGNORE INTO site_settings(setting_key,value_json,updated_at) VALUES ('site_policy',?,UTC_TIMESTAMP())")->execute([json_encode($value,JSON_THROW_ON_ERROR)]);
    }
    public function down(PDO $pdo): void
    {
        $pdo->exec("DELETE FROM site_settings WHERE setting_key='site_policy'");
    }
};
