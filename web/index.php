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


    /*
      * tworzy matrix MxM z listy
      */
    function matrixFromCriteriaList($criteria) {
            $result = array();

            foreach ($criteria as $c) {
                    $column = array();
                    foreach ($criteria as $c2) {
                        if (isset($criteria['ram'])) {
                            $column[] = (double) ($c / $c2);
                        } else {
                            $column[] = (double) ($c['value'] / $c2['value']);
                        }
                    }

                    $result[] = $column;
            }

            return $result;
    }

    /*
      * wylicza liste parametrow c np c1 = suma elementów w 1 kolumnie
      */
    function getCValues($matrix) {
        $cValues = array();

        foreach ($matrix as $column) {
                $sum = 0;
                foreach ($column as $value) {
                        $sum += $value;
                }
                $cValues[] = $sum;
        }

        return $cValues;
    }

    /*
      * Normalizacja maciezy polega na podizeleniu kazdego elementu w maciezy
      * przez cValue dla odpowiedniej kolumny
      * 
      * robie to na zasadzie zwyklej podmiany, tylko nie pamietam czy przez
      * referencje by sie to podmieni³o wstawienie nowej wartosci dzieje sie w
      * linii: value = value/cValues.get(i); reszta to odpowiednia iteracja po
      * tablicy
      */
    function normalizeMatrix(&$matrix, $cValues) {

        for ($i = 0; $i < count($matrix); $i++) {
            $column = $matrix[$i];
            foreach ($column as $j => $value) {
                    $matrix[$i][$j] = $value / $cValues[$i];
            }
        }
    }

    /*
      * Tutaj przekazujemy juz znormalizowana tablice!
      * 
      * s1 to suma elementow w pierwszym rzedzie * (1/M)(M ilosc elementow w
      * rzedzie)
      */
    function /*List<Double>*/ getSValues(/*List<List<Double>>*/ $matrix) {
        $sValues = array();

        for ($y = 0; $y < count($matrix); $y++) {
                $sum = 0;
                for ($x = 0; $x < count($matrix); $x++) {
                        $sum += $matrix[$x][$y]; 
                }
                $sValues[] = $sum/count($matrix);
        }
        return $sValues;
    }

    function getSValuesFromNotNormalized($matrix) {
            $cValues = getCValues($matrix);
            normalizeMatrix($matrix, $cValues);

            return getSValues($matrix);
    }
        
    /*
      * obliczenie lambda max
      * 
      * cValues i sValues oczywiscie wyliczone z tej samej maciezy
      */
    function getLambdaMax($cValues, $sValues){

        $lambdaMax = 0;
        
        for ($i = 0 ; $i < count($cValues); $i++){
                $lambdaMax += $cValues[$i] * $sValues[$i];
        }
        
        return $lambdaMax;
    }
        
    /*
      * jako parametr podajemy rzad maciezy
      */
    function getCRCheckValue($matrixDim){
        switch($matrixDim){
            case 1: 
                    return 0.0;
            case 2:
                    return 0.0;
            case 3:
                    return 0.52;
            case 4:
                    return 0.89;
            case 5:
                    return 1.11;
            case 6:
                    return 1.25;
            default:
                    return 0.0;
        }
    }
        
    function checkMatrixConsistency(/*List<List<Double>> */$matrix){
        $cValues = getCValues($matrix);
        $sValues = getSValues($matrix);
        $lambdaMax = getLambdaMax($cValues, $sValues);
        
        $ci = ($lambdaMax - count($matrix))/(count($matrix)-1);
        $cr = $ci/getCRCheckValue(count($matrix));
        
        return $cr < 0.1;
    }
        
    /*
      * tutaj List<List<Double>> to list sValues(a to samo w sobie jest lista, a dokladniej wektorem)
      */
    function getRanking($m0sValues, $paramsSValues){
        $ranking = array();
        
        for($i = 0 ; $i < count($paramsSValues); $i++){
                $tempPair = array();
                for($j = 0 ; $j < count($m0sValues); $j++){
                        $ratio = $m0sValues[$j] * $paramsSValues[$j][$i];
                        $tempPair['index'] = $i;
                        $tempPair['ratio'] = $ratio;
                }
                $ranking[] = $tempPair;
        }
        
        return $ranking;
    }


function ahp($pcs, $preferences, $sort) {

    $groupedCriterias = array();
    foreach ($preferences['criteria'] as $name => $val) {
        $criteriaVector = array();
        foreach ($pcs as $pc) {
            $criteriaVector[] = array('name' => $name, 'value' => $pc['criteria'][$name]);
        }
        $groupedCriterias[] = $criteriaVector;
    }

    $m0 = matrixFromCriteriaList($preferences['criteria']);
    $valuesMatrixList = array();

    foreach ($groupedCriterias as $paramVector) {
        $valuesMatrixList[] = matrixFromCriteriaList($paramVector);
    }

    $m0Consistent = checkMatrixConsistency($m0);
    $paramsConsistency = true;

    foreach ($valuesMatrixList as $paramMatrix) {
        $paramsConsistency = $paramsConsistency && checkMatrixConsistency($paramMatrix);
    }

    if (!$m0Consistent || !$paramsConsistency) {
        print_r('not consistent');
    }

    $m0SValues = getSValuesFromNotNormalized($m0);

    $paramsSValues = array();
    foreach ($valuesMatrixList as $paramMatrix) {
        $paramsSValues[] = getSValuesFromNotNormalized($paramMatrix);
    }

    $ranking = getRanking($m0SValues, $paramsSValues);

    var_dump($ranking);

    $maxRatio = 0;
    foreach ($ranking as $item) {
        if ($item['ratio'] > $maxRatio) {
              $maxRatio = $item['ratio'];
        }
    }

    foreach ($pcs as $i => $s) {
        $pcs[$i]['rating'] = ($ranking[$i]['ratio']/$maxRatio)*10;
    }

    usort($pcs, function($a, $b) {
        if ($a['rating'] == $b['rating'])
        {
            //TODO sort other ways
            if ($a['price'] == $b['price']) {
                return 0;
            }

            return $a['price'] > $b['price'] ? -1 : 1;

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

    $items = ahp($pcs, $selectedProfile, $sort);

    return $app['twig']->render('result.html.twig', array(
        'selectedProfile' => $selectedProfile,
        'sort' => $sort,
        'items' => $items
    ));
})
->bind('result'); 

$app->run(); 
