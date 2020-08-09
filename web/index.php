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

$app->get('/products', function() use($app) {
  $products = array(
    array(
      'id' => '1234',
      'name' => 'spatula'
    ),
    array(
      'id' => '1599',
      'name' => 'french press'
    ),
  );
  return $app->json(json_encode($products), 200);
});

$app->get('/review', function() use($app) {
  return $app['twig']->render('review.twig');
});

$app->post('/review', function() use($app) {
  //code for handling review submission here
  $app['db']->insert('reviews', array(
    'id' => md5(strtotime(date("Y-m-d h:i:sa")).rand(0, 999)),
    'timestamp' => strtotime(date("Y-m-d h:i:sa")),
    'description' => ($_POST['description']),
    'rating' => ($_POST['rating']),
    'href' => ($_POST['href']),
    'username' => ($_POST['name']),
    'email' => ($_POST['email']),
  ));
  //structure entry and put in database
  //send the user a successful message back
  $output = "";
  foreach($_POST as $key => $value) {
    $output.="$key: $value"."<br />";
  }
  return $output;
});


$app->get('/view_review/{id}', function($id) use($app) {
  return $app['twig']->render('view_review.twig');
});

$app->get('/get_review/{id}', function($id) use($app) {
  $review = $app['db']->fetchAssoc('SELECT * FROM reviews WHERE id = ?', array("$id"));
  return $app->json(json_encode($review), 200);
});

$app->post('/edit_review', function() use($app) {
  $app['db']->update('reviews', array('description' => $_POST['description']));
  return new Response('Successfully updated review', 200);
});

$app->get('/product/{id}', function($id) use($app) {
  //$product = $app['db']->fetchAssoc('SELECT * FROM products WHERE id = ?', array("$id"));
  $product = array(
    'id' => '1234',
    'name' => 'french press',
    'link' => '/products/'.$id
  );
  return $app->json(json_encode($product), 200);
});

$app->get('/get_all_reviews', function() use($app) {
  $reviews = $app['db']->fetchAll('SELECT * FROM reviews');
  return $app->json(json_encode($reviews), 200);

});

$app->get('/view_all', function() use($app) {
  /*$statement = $app['db']->prepare('SELECT * FROM reviews');
  $statement->execute();
  $reviews = $statement->fetchAllAssociative();*/

  /*foreach($reviews as $row_number => $row) {
    $response.=$row_number."\t|";
    foreach($row as $key => $value) {
      $response.=$value."|";
    }
    $response.="<br />";
  }*/
  //get all reviews and put into list and return
  //return "<div class=\"container\">$response</div>";
  return $app['twig']->render('view_all.twig');
});

$app->run();
