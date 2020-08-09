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
  $product = $app->fetchAssoc('SELECT href FROM products WHERE id = ?', array($_POST['product']));
  //code for handling review submission here
  $app['db']->insert('reviews', array(
    'id' => md5(strtotime(date("Y-m-d h:i:sa")).rand(0, 999)),
    'product_id' => ($_POST['product']),
    'timestamp' => strtotime(date("Y-m-d h:i:sa")),
    'description' => ($_POST['description']),
    'rating' => ($_POST['rating']),
    'href' => ($product['href']),
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
  $app['db']->update('reviews', array('description' => $_POST['description']), array('id' => $_POST['id']));
  return 'Successfully updated review';
});

$app->get('/product/{id}', function($id) use($app) {
  //$product = $app['db']->fetchAssoc('SELECT name, href FROM products WHERE id = ?', array("$id"));
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
  return $app['twig']->render('view_all.twig');
});

$app->get('/report', function() use($app) {
  //average product rating
  return $app['twig']->render('report.twig');
});

$app->get('/report/get_data', function() use($app) {
  $product_ids = $app['db']->fetchAll('SELECT * FROM products');
  $average_rating = array();

  //for each product id
  for ($product_ids as $row_key => $row) {
    $product_id = $row['product_id'];
    $reviews = $app['db']->fetchAll("SELECT rating, FROM reviews WHERE product_id = $product_id");
    $average_total = 0;
    //for each review for that product
    for ($reviews as $review_row_key => $review_row) {
      $review_rating = $review_row['rating'];
      $average_total+=$rating_rating;
    }
    $average_rating[$product_id]=array(
      'rating' => $average_total/count($reviews),
      'name' => $row['name'],
      'href' => $row['href'],
      'total_reviews' => $average_total
    );
  }

  return $app->json(json_encode($average_rating), 200);
});

$app->error(function(\Exception $e) {
  return ("Sorry, but something went terrible wrong<br />$e");
});

$app->run();
