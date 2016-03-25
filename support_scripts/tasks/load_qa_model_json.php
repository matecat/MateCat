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

$model_record = LQA\ModelDao::createModelFromJsonDefinition( $json );

$dao = new \Projects_ProjectDao( \Database::obtain() );
$dao->updateField( $this->project, 'id_qa_model', $model_record->id );

echo "done \n";
