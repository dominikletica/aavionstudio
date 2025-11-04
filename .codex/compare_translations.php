<?php
require dirname(__DIR__).'/vendor/autoload.php';

use Symfony\Component\Yaml\Yaml;

function flatten(array $data, string $prefix = ''): array
{
    $flat = [];
    foreach ($data as $key => $value) {
        $path = $prefix === '' ? (string) $key : $prefix.'.'.$key;
        if (is_array($value)) {
            $flat += flatten($value, $path);
        } else {
            $flat[$path] = $value;
        }
    }

    return $flat;
}

$root = dirname(__DIR__);
$en = Yaml::parseFile($root.'/translations/messages.en.yaml');
$de = Yaml::parseFile($root.'/translations/messages.de.yaml');

$missing = array_diff_key(flatten($en), flatten($de));

foreach (array_keys($missing) as $key) {
    echo $key, "\n";
}
