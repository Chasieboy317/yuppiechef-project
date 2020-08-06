<?php

require('../vendor/autoload.php');

$app = new Silex\Application();
$app['debug'] = true;

// Register the monolog logging service
$app->register(new Silex\Provider\MonologServiceProvider(), array(
  'monolog.logfile' => 'php://stderr',
));

// Register view rendering
$app->register(new Silex\Provider\TwigServiceProvider(), array(
    'twig.path' => __DIR__.'/views',
));

//database Provider
$app->register(new Silex\Provider\DoctrineServiceProvider(), array(
  'db.options' => array(
    'dbname' => getenv('dbname'),
    'host' => getenv('dbhost'),
    'user' => getenv('dbuser'),
    'password' => getenv('dbpass'),
    'port' => getenv('dbport')
  ),
));

// Our web handlers

$app->get('/', function() use($app) {
  $app['monolog']->addDebug('logging output.');
  return $app['twig']->render('index.twig');
});

$app->get('/review', function() use($app) {
  return $app['twig']->render('review.twig');
});

$app->post('/review', function() use($app) {
  //code for handling review submission here
  $app['db']->insert('reviews', array(
    'id' => strtotime(date("Y-m-d h:i:sa")),
    'description' => mysqli_real_escape_string($_POST['description']),
    'rating' => mysqli_real_escape_string($_POST['rating']),
    'href' => mysqli_real_escape_string($_POST['href']),
    'username' => mysqli_real_escape_string($_POST['name']),
    'email' => mysqli_real_escape_string($_POST['email']),
  ));
  //structure entry and put in database
  //send the user a successful message back
  $output = "";
  foreach($_POST as $key => $value) {
    $output.="$key: $value"."<br />";
  }
  echo $output;
  return $output;
});

$app->get('/view_all', function() use($app) {
  $statement = $app['db']->prepare('SELECT * FROM reviews');
  $statement->execute();
  $reviews = $statement->fetchAllAssociative();

  //$reviews = $app['db']->fetchAll('SELECT * FROM reviews');


  $response = "";
  foreach($reviews as $row_number => $row) {
    $response.=$row_number."\t|";
    foreach($row as $key => $value) {
      $response.=$value."|";
    }
    $response.="<br />";
  }
  //get all reviews and put into list and return
  return "<div class=\"container\">$response</div>";
});

$app->run();
