<?php

require_once __DIR__.'/../vendor/autoload.php';

use Spatie\YamlFrontMatter\YamlFrontMatter as YFM;

$object = YFM::parse(file_get_contents(__DIR__.'/../'.$argv[1]));

// envファイルをparse
$envJson = file_get_contents(__DIR__.'/../env.json');
$env = json_decode($envJson, true);
$env['dsn'] = 'mysql:dbname='.$env['dbname'].';host='.$env['host'].';charset=utf8mb4';

// sql文をprepare
$sql = [];
$sql['select'] = 'SELECT * FROM :table WHERE title = :title;';
$sql['insert'] = 'INSERT INTO :table (title, body) VALUES (:title, :body);';
$sql['update'] = 'UPDATE :table SET title = :title, body = :body WHERE title = :title';

// sqlに代入
$sql['table'] = $env['table'];
$sql['title'] = $object->matter('title');
$sql['body'] = $object->body();

// dbに接続。参考：
// https://qiita.com/te2ji/items/56c194b6cb9898d10f7f
try {
    $pdo = new PDO(
        $env['dsn'],
        $env['username'],
        $env['password'],
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
//            PDO::MYSQL_ATTR_MULTI_STATEMENTS => false,
        ]
    );
    $stmt_select = $pdo->prepare($sql['select']);
    $stmt_select->bindValue(':table', $sql['table'], PDO::PARAM_STR);
    $stmt_select->bindValue(':title', $sql['title'], PDO::PARAM_STR);
    $stmt_select->bindValue(':body', $sql['body'], PDO::PARAM_STR);

    // dataがあるか
    $stmt_select->execute();
    $result = $stmt_select->fetch();
    // dataがあればupdate, そうでなければinsert
    $operation = (false !== $result) ? 'update' : 'insert';
    
    $stmt = $pdo->prepare($sql[$operation]);
    $stmt->bindValue(':table', $sql['table'], PDO::PARAM_STR);
    $stmt->bindValue(':title', $sql['title'], PDO::PARAM_STR);
    $stmt->bindValue(':body', $sql['body'], PDO::PARAM_STR);

    // 実行
    $stmt->execute();
} catch (PDOException $e) {
    exit($e->getMessage()); 
}

