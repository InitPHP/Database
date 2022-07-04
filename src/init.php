<?php
declare(strict_types=1);

if(extension_loaded('pdo') === FALSE){
    echo "The InitPHP Database library uses the PDO extension. This server does not have the PDO extension.";
    exit;
}

require_once __DIR__ . DIRECTORY_SEPARATOR . 'Exception' . DIRECTORY_SEPARATOR . 'ConnectionException.php';
require_once __DIR__ . DIRECTORY_SEPARATOR . 'Exception' . DIRECTORY_SEPARATOR . 'DatabaseException.php';
require_once __DIR__ . DIRECTORY_SEPARATOR . 'Exception' . DIRECTORY_SEPARATOR . 'ModelException.php';
require_once __DIR__ . DIRECTORY_SEPARATOR . 'Exception' . DIRECTORY_SEPARATOR . 'QueryExecuteException.php';
require_once __DIR__ . DIRECTORY_SEPARATOR . 'Exception' . DIRECTORY_SEPARATOR . 'ModelPermissionException.php';
require_once __DIR__ . DIRECTORY_SEPARATOR . 'Exception' . DIRECTORY_SEPARATOR . 'RelationshipsException.php';

require_once __DIR__ . DIRECTORY_SEPARATOR . 'Interfaces' . DIRECTORY_SEPARATOR . 'ConnectionInterface.php';
require_once __DIR__ . DIRECTORY_SEPARATOR . 'Interfaces' . DIRECTORY_SEPARATOR . 'QueryBuilderInterface.php';
require_once __DIR__ . DIRECTORY_SEPARATOR . 'Interfaces' . DIRECTORY_SEPARATOR . 'DBInterface.php';
require_once __DIR__ . DIRECTORY_SEPARATOR . 'Interfaces' . DIRECTORY_SEPARATOR . 'EntityInterface.php';
require_once __DIR__ . DIRECTORY_SEPARATOR . 'Interfaces' . DIRECTORY_SEPARATOR . 'ModelInterface.php';

require_once __DIR__ . DIRECTORY_SEPARATOR . 'Helper.php';
require_once __DIR__ . DIRECTORY_SEPARATOR . 'Connection.php';
require_once __DIR__ . DIRECTORY_SEPARATOR . 'QueryBuilder.php';
require_once __DIR__ . DIRECTORY_SEPARATOR . 'RelationshipsTrait.php';
require_once __DIR__ . DIRECTORY_SEPARATOR . 'Entity.php';
require_once __DIR__ . DIRECTORY_SEPARATOR . 'DB.php';
require_once __DIR__ . DIRECTORY_SEPARATOR . 'Model.php';
