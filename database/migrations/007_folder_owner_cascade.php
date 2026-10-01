<?php
declare(strict_types=1);
return new class {
    public function up(PDO $pdo): void
    {
        $query=$pdo->query("SELECT CONSTRAINT_NAME,DELETE_RULE FROM information_schema.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME='favorites' AND REFERENCED_TABLE_NAME='favorite_folders'");
        $constraint=$query->fetch(PDO::FETCH_ASSOC);
        if($constraint && $constraint['DELETE_RULE']==='CASCADE')return;
        if($constraint) {
            if(!preg_match('/^[a-zA-Z0-9_]+$/D',$constraint['CONSTRAINT_NAME']))throw new RuntimeException('Invalid constraint identifier');
            $pdo->exec('ALTER TABLE favorites DROP FOREIGN KEY `'.$constraint['CONSTRAINT_NAME'].'`');
        }
        // Logical folder deletion first detaches its favorites in the document.
        // Cascading here also permits deleting a complete user and all relations.
        $pdo->exec('ALTER TABLE favorites ADD CONSTRAINT favorite_folder_owner FOREIGN KEY (user_id,folder_id) REFERENCES favorite_folders(user_id,id) ON DELETE CASCADE');
    }
    public function down(PDO $pdo): void
    {
        $pdo->exec('ALTER TABLE favorites DROP FOREIGN KEY favorite_folder_owner');
        $pdo->exec('ALTER TABLE favorites ADD CONSTRAINT favorite_folder_owner FOREIGN KEY (user_id,folder_id) REFERENCES favorite_folders(user_id,id)');
    }
};
