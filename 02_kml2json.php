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
        $fc['features'][] = $f;
    }
    file_put_contents(__DIR__ . '/json/' . $layer . '.json', json_encode($fc, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}
