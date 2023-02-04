<?php
/*
geo server doc - http://docs.geoserver.org/latest/en/user/services/wfs/index.html
layer list - https://reip.tainan.gov.tw/GHD/ProxyPage/proxy.jsp?https://reip.tainan.gov.tw/geoserver/ows?service=wfs&version=1.0.0&request=GetCapabilities
*/

$layerFile = __DIR__ . '/layers.json';
if (!file_exists($layerFile)) {
  $xml = simplexml_load_file('https://reip.tainan.gov.tw/GHD/ProxyPage/proxy.jsp?https://reip.tainan.gov.tw/geoserver/ows?service=wfs&version=1.0.0&request=GetCapabilities');
  file_put_contents($layerFile, json_encode($xml, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}
$layers = json_decode(file_get_contents($layerFile), true);

$jsonPath = __DIR__ . '/kml';
if (!file_exists($jsonPath)) {
  mkdir($jsonPath, 0777, true);
}

$baseUrl = 'https://reip.tainan.gov.tw/GHD/ProxyPage/proxy.jsp?https://reip.tainan.gov.tw/geoserver/GHB/wms?REQUEST=GetMap&SERVICE=WMS&TRANSPARENT=true&FORMAT=kml&VERSION=1.1.1&STYLES=&BBOX=119.7062261962879%2C23.11179752245727%2C120.97377380370807%2C23.444202830058938&WIDTH=1846&HEIGHT=527&SRS=EPSG%3A4326&LAYERS=';
foreach ($layers['FeatureTypeList']['FeatureType'] as $ft) {
  if (is_array($ft['Abstract'])) {
    $ft['Abstract'] = $ft['Title'];
  }
  $ft['Abstract'] = str_replace("\n", '_', $ft['Abstract']);
  $targetFile = $jsonPath . '/' . $ft['Abstract'] . '.kml';

  if (!file_exists($targetFile)) {
    file_put_contents($targetFile, file_get_contents($baseUrl . urlencode($ft['Name'])));
  }
  if (filesize($targetFile) === 0) {
    unlink($targetFile);
  }
}
