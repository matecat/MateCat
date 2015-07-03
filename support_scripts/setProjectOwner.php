<?php
if ( !@include_once '../inc/Bootstrap.php' ) {
    header( "Location: configMissing" );
}

Bootstrap::start();
$db = Database::obtain( INIT::$DB_SERVER, INIT::$DB_USER, INIT::$DB_PASS, INIT::$DB_DATABASE );
$db->connect();

$filterArgs = array(
        'url'   => array(
                'filter' => FILTER_SANITIZE_STRING, 'options' => FILTER_FLAG_STRIP_HIGH, FILTER_FLAG_STRIP_LOW
        ),
        'email' => array( 'filter' => FILTER_SANITIZE_STRING )
);

$postInput = filter_input_array( INPUT_POST, $filterArgs );

if ( !( stripos( $postInput[ 'url' ], "/translate/" ) > 0 ) ) {
    echo "This URL is not a link to a matecat project.";
    exit;
}
$matches = null;
preg_match("/(([0-9]+)-([a-z0-9]*))/",$postInput[ 'url' ], $matches);

if($matches === null){
    echo "Job id and password not found";
    exit;
}

$id = $matches[2];
$pass = $matches[3];

$query = "update jobs set owner = '%s' where id = %d and password = '%s'";

$db->query(
        sprintf(
            $query,
                trim($postInput[ 'email' ]),
                $id,
                $pass
        )
);

if($db->affected_rows > 0 ) echo "<h1> Success! </h1>";