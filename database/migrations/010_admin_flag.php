<?php
declare(strict_types=1);
return new class {
    public function up(PDO $pdo): void
    {
        $query = $pdo->prepare("SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='administrators' AND column_name='admin_flag'");
        $query->execute();
        if (!(int)$query->fetchColumn()) {
            $pdo->exec('ALTER TABLE administrators ADD COLUMN admin_flag TINYINT UNSIGNED NOT NULL DEFAULT 1');
        }
    }
    public function down(PDO $pdo): void
    {
        $query = $pdo->prepare("SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='administrators' AND column_name='admin_flag'");
        $query->execute();
        if ((int)$query->fetchColumn()) $pdo->exec('ALTER TABLE administrators DROP COLUMN admin_flag');
    }
};
