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

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

// Our web handlers
$app->get('/', function() use($app) {
  $app['monolog']->addDebug('logging output.');
  return $app['twig']->render('index.twig');
});

$app->get('/products', function() use($app) {
  $products=$app['db']->fetchAll('SELECT * FROM products');
  return $app->json(json_encode($products), 200);
});

$app->get('/review', function() use($app) {
  return $app['twig']->render('review.twig');
});

$app->post('/review', function() use($app) {
  $product = $app['db']->fetchAssoc('SELECT href FROM products WHERE id = ?', array($_POST['product']));
  //code for handling review submission here
  $app['db']->insert('reviews', array(
    'id' => md5(strtotime(date("Y-m-d h:i:sa")).rand(0, 999)),
    'product_id' => $app->escape($_POST['product']),
    'timestamp' => strtotime(date("Y-m-d h:i:sa")),
    'description' => $app->escape($_POST['description']),
    'rating' => $app->escape($_POST['rating']),
    'href' => $app->escape($product['href']),
    'username' => $app->escape($_POST['name']),
    'email' => $app->escape($_POST['email']),
  ));

  //update product rating
  $product_rating = $app['db']->fetchAssoc('SELECT rating, total FROM product_ratings WHERE id = ?', array($_POST['product']));
  $rating = $product_rating['rating'];
  $total = $product_rating['total'];
  //calculate new average
  $sum = $rating*$total;
  $new_rating = ($sum+$app->escape($_POST['rating']))/($total+1);

  $app['db']->update('product_ratings', array('rating' => $new_rating, 'total' => $total+1), array('id' => $_POST['product']));

  return $app['twig']->render('review.twig', ['showPopup' => true]);
});


$app->get('/view_review/{id}', function($id) use($app) {
  return $app['twig']->render('view_review.twig');
});

$app->get('/get_review/{id}', function($id) use($app) {
  $review = $app['db']->fetchAssoc('SELECT * FROM reviews WHERE id = ?', array("$id"));
  return $app->json(json_encode($review), 200);
});


$app->post('/edit_review', function() use($app) {
  $id = $app->escape($_POST['id']);
  $app['db']->update('reviews', array('description' => $app->escape($_POST['description'])), array('id' => $id));
  return $app->redirect("/view_review/$id");
});

$app->get('/product/{id}', function($id) use($app) {
  $product = $app['db']->fetchAssoc('SELECT name, href FROM products WHERE id = ?', array("$id"));
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
  return $app['twig']->render('report.twig');
});

$app->get('/report/get_data', function() use($app) {
  $product_data = $app['db']->fetchAll('SELECT product_ratings.id, product_ratings.rating, product_ratings.total, products.name, products.href FROM product_ratings INNER JOIN products on products.id=product_ratings.id');
  return $app->json(json_encode($product_data), 200);
});

$app->error(function(\Exception $e) {
  return ("Sorry, but something went terrible wrong<br />$e");
});

$app->run();
