<?php
$keyMap = [
    'GHB002003' => '鄉鎮市區',
    'GHB011003' => '籌設類型',
    'GHB011006' => '業者名稱',
];
$layers = [
    '電業籌設' => ['GHB002003', 'GHB011003', 'GHB011006'],
    '能源局列管案場' => ['鄉鎮市區', '段小段', '地號', '面積', '案件申請年', '業者名稱', '電廠名稱'],
];


/*
area functions from https://github.com/spinen/laravel-geometry/blob/develop/src/Support/GeometryProxy.php
*/
function determineCoordinateIndices($index, $length)
{
    // i = N-2
    if ($index === ($length - 2)) {
        return [$length - 2, $length - 1, 0];
    }

    // i = N-1
    if ($index === ($length - 1)) {
        return [$length - 1, 0, 1];
    }

    // i = 0 to N-3
    return [$index, $index + 1, $index + 2];
}

function radians($degrees)
{
    return $degrees * M_PI / 180;
}

function ringArea($coordinates)
{
    $area = 0.0;

    $length = count($coordinates);

    if ($length <= 2) {
        return $area;
    }

    for ($i = 0; $i < $length; $i++) {
        list($lower_index, $middle_index, $upper_index) = determineCoordinateIndices($i, $length);

        $point1 = $coordinates[$lower_index];
        $point2 = $coordinates[$middle_index];
        $point3 = $coordinates[$upper_index];

        $area += (radians($point3[0]) - radians($point1[0])) * sin(radians($point2[1]));
    }

    return $area * 6378137 * 6378137 / 2;
}

$area = [];
foreach ($layers as $layer => $keys) {
    $kml = simplexml_load_file(__DIR__ . '/kml/' . $layer . '.kml');
    $json = json_decode(json_encode($kml), true);
    $fc = [
        'type' => 'FeatureCollection',
        'features' => [],
    ];

    foreach ($json['Document']['Folder']['Placemark'] as $p) {
        $f = [
            'type' => 'Feature',
            'properties' => [],
            'geometry' => [],
        ];
        $lines = explode('</li>', substr($p['description'], strpos($p['description'], '<li')));
        $data = [];
        foreach ($lines as $line) {
            $parts = explode(':', trim(strip_tags($line)));
            if (count($parts) === 2) {
                $data[trim($parts[0])] = trim($parts[1]);
            }
        }

        foreach ($keys as $key) {
            if (isset($keyMap[$key])) {
                if (isset($data[$key])) {
                    $f['properties'][$keyMap[$key]] = $data[$key];
                } else {
                    $f['properties'][$keyMap[$key]] = '';
                }
            } else {
                if (isset($data[$key])) {
                    $f['properties'][$key] = $data[$key];
                } else {
                    $f['properties'][$key] = '';
                }
            }
        }
        if (isset($p['MultiGeometry']['MultiGeometry'])) {
            $f['geometry']['type'] = 'MultiPolygon';
            $f['geometry']['coordinates'] = [];
            foreach ($p['MultiGeometry']['MultiGeometry']['Polygon'] as $polygon) {
                $pool = [];
                $points = explode(' ', $polygon['outerBoundaryIs']['LinearRing']['coordinates']);
                foreach ($points as $point) {
                    $point = explode(',', $point);
                    $pool[] = [floatval($point[0]), floatval($point[1])];
                }
                $f['geometry']['coordinates'][] = [$pool];
            }
        } else {
            $pool = [];
            $points = explode(' ', $p['MultiGeometry']['Polygon']['outerBoundaryIs']['LinearRing']['coordinates']);
            foreach ($points as $point) {
                $point = explode(',', $point);
                $pool[] = [floatval($point[0]), floatval($point[1])];
            }
            $f['geometry']['type'] = 'Polygon';
            $f['geometry']['coordinates'] = [$pool];
        }
        if (!isset($area[$f['properties']['業者名稱']])) {
            $area[$f['properties']['業者名稱']] = 0.0;
        }
        $areaSize = 0;
        foreach ($f['geometry']['coordinates'] as $c) {
            $areaSize += round(abs(ringArea($c)), 0);
        }
        $area[$f['properties']['業者名稱']] += $areaSize;

        $fc['features'][] = $f;
    }

    $oFh = fopen(__DIR__ . '/csv/' . $layer . '.csv', 'w');
    fputcsv($oFh, ['業者名稱', '面積(公頃)']);
    foreach ($area as $k => $v) {
        fputcsv($oFh, [$k, round($v / 10000, 2)]);
    }
    file_put_contents(__DIR__ . '/json/' . $layer . '.json', json_encode($fc, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}
