<?php

use Symfony\Component\HttpFoundation\Request;

require_once __DIR__.'/../vendor/autoload.php'; 

$app = new Silex\Application(); 
$app['debug'] = true;

$app->register(new Silex\Provider\TwigServiceProvider(), array(
    'twig.path' => __DIR__.'/views',
));
$twig = $app['twig'];
$twig->addExtension(new \Entea\Twig\Extension\AssetExtension($app));
$app->register(new Silex\Provider\UrlGeneratorServiceProvider());

function ahp($stuff, $profile, $sort) {

    foreach ($stuff as $i => $s) {
        //TODO AHP
        $stuff[$i]['rating'] = rand(1,10);
    }

    //TODO kind of sorting
    usort($stuff, function($a, $b) {
        if ($a['rating'] == $b['rating'])
            return 0;

        return $a['rating'] > $b['rating'] ? -1 : 1;
    });

    return $stuff;
}

$app->get('/', function() use ($app) {

    $profiles = json_decode(file_get_contents('profiles.json'));

    return $app['twig']->render('index.html.twig', array(
        'profiles' => $profiles
    ));
})
->bind('homepage');

$app->get('/wynik', function(Request $request) use ($app) {

    $profiles = json_decode(file_get_contents('profiles.json'), true);
    $stuff = json_decode(file_get_contents('stuff.json'), true);

    $selectedProfile = '';
    foreach ($profiles as $p) {
        if ($p['name'] == $request->get('profile')) {
            $selectedProfile = $p;
        }
    }

    if (!$selectedProfile || $request->get('sort') == '') {
        return $app->redirect('/');
    }

    $sorts = array(
        "cena" => "niska cena",
        "wydajnosc" => "wydajność",
        "prestiz" => "prestiż",
    );
    $sort = $sorts[$request->get('sort')];

    $items = ahp($stuff, $selectedProfile, $sort);

    return $app['twig']->render('result.html.twig', array(
        'selectedProfile' => $selectedProfile,
        'sort' => $sort,
        'items' => $items
    ));
})
->bind('result'); 

$app->run(); 
