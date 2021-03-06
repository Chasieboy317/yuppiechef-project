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

//fetch all the products from the database and send them back json encoded
$app->get('/products', function() use($app) {
  $products=$app['db']->fetchAll('SELECT * FROM products');
  return $app->json(json_encode($products), 200);
});

//render the submit review page
$app->get('/review', function() use($app) {
  return $app['twig']->render('review.twig');
});

//when a user submits the review, fetch the product data for which the review was submitted
//the review id is md5 hashed using the current date and time and a random appended number from 0-999
//all user input data to be stored in the database is html character escaped
//the ratings for the reviewed product is recalculated and updated
//the review page is returned with the popup flag set.
$app->post('/review', function() use($app) {
  //html escape all user input
  $product_id = $app->escape($_POST['product']);
  $description = $app->escape($_POST['description']);
  $rating = $app->escape($_POST['rating']);
  $username = $app->escape($_POST['name']);
  $email = $app->escape($_POST['email']);

  //get the link for the product that is being reviewed
  $product = $app['db']->fetchAssoc('SELECT href FROM products WHERE id = ?', array($_POST['product']));

  //testing for non existent product
  if (empty($product)) {
    throw new Exception('product not found');
  }

  //testing for malformed input
  $valid = true;
  if (strlen($product_id)!=6) {
    $valid = false;
  }
  else if (strlen($description)==0) {
    $valid = false;
  }
  else if (!($rating>=1 && $rating<=5)) {
    $valid = false;
  }
  else if (strlen($username)==0) {
    $valid = false;
  }
  else if (strlen($email)==0) {
    $valid = false;
  }

  if (!$valid) {
    throw new Exception('user input not valid');
  }

  //code for handling review submission here
  $app['db']->insert('reviews', array(
    'id' => md5(strtotime(date("Y-m-d h:i:sa")).rand(0, 999)),
    'product_id' => $product_id,
    'timestamp' => strtotime(date("Y-m-d h:i:sa")),
    'description' => $description,
    'rating' => $rating,
    'href' => $app->escape($product['href']),
    'username' => $username,
    'email' => $email,
  ));

  //update product rating
  $product_rating = $app['db']->fetchAssoc('SELECT rating, total FROM product_ratings WHERE id = ?', array($product_id));
  $rating = $product_rating['rating'];
  $total = $product_rating['total'];

  //calculate new average
  $sum = $rating*$total;
  $new_rating = ($sum+$app->escape($_POST['rating']))/($total+1);

  $app['db']->update('product_ratings', array('rating' => $new_rating, 'total' => $total+1), array('id' => $product_id));

  return $app['twig']->render('review.twig', ['showPopup' => true]);
});


//render the review page
$app->get('/view_review/{id}', function($id) use($app) {
  return $app['twig']->render('view_review.twig');
});

//api call for retrieving all data for a particular review
$app->get('/get_review/{id}', function($id) use($app) {
  $review = $app['db']->fetchAssoc('SELECT * FROM reviews WHERE id = ?', array("$id"));
  if (empty($review)) {
    throw new Exception("review with $id does not exist");
  }
  return $app->json(json_encode($review), 200);
});

//escape the user input and update the description in the database
$app->post('/edit_review', function() use($app) {
  //test for non existent review
  $id = $app->escape($_POST['id']);
  $review = $app['db']->fetchAssoc('SELECT id FROM reviews WHERE id = ?', array("$id"));
  if (empty($review)) {
    throw new Exception('review does not exist');
  }

  //test for empty description
  $description = $app->escape($_POST['description']);
  if (strlen($description)==0) {
    throw new Exception('description cannot be empty');
  }

  $app['db']->update('reviews', array('description' => $description), array('id' => $id));
  return $app->redirect("/view_all");
});

//api call for getting all data for a particular product
$app->get('/products/{id}', function($id) use($app) {
  $product = $app['db']->fetchAssoc('SELECT name, href FROM products WHERE id = ?', array("$id"));
  if (empty($product)) {
    throw new Exception("product with $id does not exist");
  }
  return $app->json(json_encode($product), 200);
});

//api call for getting all information for every review
$app->get('/get_all_reviews', function() use($app) {
  $reviews = $app['db']->fetchAll('SELECT * FROM reviews');
  return $app->json(json_encode($reviews), 200);
});

//render the review listing page
$app->get('/view_all', function() use($app) {
  return $app['twig']->render('view_all.twig');
});

//render the reports page
$app->get('/report', function() use($app) {
  return $app['twig']->render('report.twig');
});

//return all the information the database has for the products, including the product ratings
$app->get('/report/get_data', function() use($app) {
  $product_data = $app['db']->fetchAll('SELECT product_ratings.id, product_ratings.rating, product_ratings.total, products.name, products.href FROM product_ratings INNER JOIN products on products.id=product_ratings.id');
  return $app->json(json_encode($product_data), 200);
});

//return the depending on the error
$app->error(function (\Exception $e, Request $request, $code) use($app) {
  switch ($code) {
    case 404:
      $message = $app['twig']->render('notfound.twig');
      break;
    default:
      $message = "Sorry, but something went terribly wrong <br />$e";
  }
  return $message;
});

$app->run();
