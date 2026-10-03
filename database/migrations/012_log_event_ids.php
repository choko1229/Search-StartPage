<?php
declare(strict_types=1);
return new class {
    public function up(PDO $pdo): void
    {
        $query=$pdo->prepare("SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='log_entries' AND column_name='event_id'");$query->execute();
        if (!(int)$query->fetchColumn()) $pdo->exec('ALTER TABLE log_entries ADD COLUMN event_id CHAR(32) CHARACTER SET ascii COLLATE ascii_bin NULL');
        $query=$pdo->prepare("SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema=DATABASE() AND table_name='log_entries' AND index_name='log_event_unique'");$query->execute();
        if (!(int)$query->fetchColumn()) $pdo->exec('ALTER TABLE log_entries ADD UNIQUE INDEX log_event_unique(event_id)');
    }
    public function down(PDO $pdo): void
    {
        $query=$pdo->prepare("SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='log_entries' AND column_name='event_id'");$query->execute();
        if ((int)$query->fetchColumn()) $pdo->exec('ALTER TABLE log_entries DROP COLUMN event_id');
    }
};
