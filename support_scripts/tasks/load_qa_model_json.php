<?php

$root = realpath(dirname(__FILE__) . '/../../');
include_once $root . "/inc/Bootstrap.php";
Bootstrap::start();

$db = Database::obtain(INIT::$DB_SERVER, INIT::$DB_USER, INIT::$DB_PASS, INIT::$DB_DATABASE);
$db->debug = false;
$db->connect();

function usage() {
  echo "Usage: \n
--file file.json       The file to load\n
--id_project 42        The project id to assign the model\n";

  exit;
}

$options = getopt( 'h', array( 'file:', 'id_project:'));

if (array_key_exists('h', $options))               usage() ;
if (empty($options))                               usage() ;
if (!array_key_exists('file', $options))           usage() ;
if (!array_key_exists('id_project', $options))     usage() ;


$project = Projects_ProjectDao::findById( $options['id_project']);

$content = file_get_contents( $options['file']);
$json = json_decode( $content, true );

$model_root = $json['model'];

// var_dump($model_root);
$model = LQA\ModelDao::createRecord( $model_root );

$default_severities = $model_root['severities'];
$categories = $model_root['categories'];

function insertRecord($record, $model_id, $parent_id) {
    global $default_severities ;

    if ( !array_key_exists('severities', $record) ) {
       $record['severities'] = $default_severities ;
    }

    $category = LQA\CategoryDao::createRecord(array(
        'id_model' => $model_id,
        'label' => $record['label'],
        'id_parent' => $parent_id,
        'severities' => json_encode( $record['severities'] )
    ));

    if ( array_key_exists('subcategories', $record)) {
        foreach($record['subcategories'] as $sub) {
            insertRecord($sub, $model_id, $category->id);
        }
    }
}

foreach($categories as $record) {
    insertRecord($record, $model->id, null);
}

$project->id_qa_model = $model->id ;
$conn = Database::obtain()->getConnection();

$stmt = $conn->prepare(
    "UPDATE projects SET id_qa_model = :id_qa_model WHERE id = :id "
);

$stmt->execute( array(
    'id' => $project->id,
    'id_qa_model' => $model->id
));
