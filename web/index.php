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


function multiplyMatrix($matrix1, $matrix2)
{
    $result = array();

    for ($row = 0; $row < count($matrix1); $row++) {
        $sum = 0;
        foreach ($matrix1[$row] as $index => $number) {
            $sum += $number * $matrix2[$index];
        }

        $result[] = $sum;
    }

    return $result;
}

function squareMatrix($matrix)
{
    $result = array();

    for ($row = 0; $row < count($matrix); $row++) {
        for ($col = 0; $col < count($matrix); $col++) {
            $sum = 0;
            for ($a = 0; $a < count($matrix); $a++) {
                for ($b = 0; $b < count($matrix); $b++) {
                    $sum += $matrix[$row][$a]*$matrix[$b][$col];
                }
            }

            $result[$row][$col] = $sum;
        }
    }

    return $result;
}

function createEigenvector($matrix)
{
    $matrix = squareMatrix($matrix);

    $vector = array();
    $totalSum = 0;

    foreach ($matrix as $row) {
        $sum = 0;
        foreach ($row as $item) {
            $sum += $item;
        }
        $vector[] = $sum;
        $totalSum += $sum;
    }

    foreach ($vector as $i => $sum) {
        $vector[$i] = $sum / $totalSum;
    }

    return $vector;
}

function ahp($pcs, $preferences, $sort) {
    $criteriaComparison = array();
    $ranking = array();

    foreach ($preferences['criteria'] as $name => $val) {
        $row = array();

        foreach ($preferences['criteria'] as $name2 => $val2) {
            $row[] = $val / $val2;
        }

        $criteriaComparison[] = $row;

        $criteriaRanking = array();
        foreach ($pcs as $pc) {
            $row = array();

            foreach ($pcs as $pc2) {
                $row[] = $pc['criteria'][$name] / $pc2['criteria'][$name];
            }

            $criteriaRanking[] = $row;
        }

        $ranking[] = $criteriaRanking;
    }

    $generalEigenvector = createEigenvector($criteriaComparison);
    $rankingEigenvectors = array();

    foreach ($ranking as $item) {
        $rankingEigenvectors[] = createEigenvector($item);
    }

    $ultimateMatrix = array();

    foreach ($generalEigenvector as $i => $general) {
        $row = array();
        foreach ($rankingEigenvectors as $vector) {
            $row[] = $vector[$i];
        }
        $ultimateMatrix[] = $row;
    }

    $ftw = multiplyMatrix($ultimateMatrix, $generalEigenvector);

    $maxRatio = 0;
    foreach ($ftw as $item) {
        if ($item > $maxRatio) {
              $maxRatio = $item;
        }
    }

    foreach ($pcs as $i => $s) {
        $pcs[$i]['rating'] = round(($ftw[$i]/$maxRatio)*10, 1);
    }

    usort($pcs, function($a, $b) use ($sort) {
        if ($a['rating'] == $b['rating'])
        {
            if ($sort == 'cena') {
                if ($a['price'] == $b['price']) {
                    return 0;
                }

                return $a['price'] < $b['price'] ? -1 : 1;
            } else if ($sort == 'wydajnosc') {
                $af = $a['criteria']['cpu']*$a['criteria']['ram'];
                $bf = $b['criteria']['cpu']*$b['criteria']['ram'];
                
                if ($af == $bf) {
                    return 0;
                }

                return $af < $bf ? -1 : 1;
            } else {
                if ($a['price'] == $b['price']) {
                    return 0;
                }

                return $a['price'] > $b['price'] ? -1 : 1;
            }
        }

        return $a['rating'] > $b['rating'] ? -1 : 1;
    });

    return $pcs;
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
    $pcs = json_decode(file_get_contents('stuff.json'), true);

    $selectedProfile = '';

    if ($request->get('profile') != '') {
        foreach ($profiles as $p) {
            if ($p['name'] == $request->get('profile')) {
                $selectedProfile = $p;
            }
        }
    } else {
        $p = array();

        $p['name'] = 'custom';
        $p['label'] = 'dla twoich preferencji';

        $p['criteria'] = array(
            "price"=> $request->get('price'),
            "disk"=> $request->get('disk'),
            "gpu"=> $request->get('gpu'),
            "cpu" => $request->get('cpu'),
            "ram" => $request->get('ram')
        );

        $selectedProfile = $p;
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

    $items = ahp($pcs, $selectedProfile, $sort);

    return $app['twig']->render('result.html.twig', array(
        'selectedProfile' => $selectedProfile,
        'sort' => $sort,
        'items' => $items
    ));
})
->bind('result'); 

$app->run(); 
