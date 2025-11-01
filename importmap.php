<?php

/**
 * Returns the importmap for this application.
 *
 * - "path" is a path inside the asset mapper system. Use the
 *     "debug:asset-map" command to see the full list of paths.
 *
 * - "entrypoint" (JavaScript only) set to true for any module that will
 *     be used as an "entrypoint" (and passed to the importmap() Twig function).
 *
 * The "importmap:require" command can be used to add new entries to this file.
 */
return [
    'app' => [
        'path' => './assets/app.js',
        'entrypoint' => true,
    ],
    '@hotwired/stimulus' => [
        'version' => '3.2.2',
    ],
    '@symfony/stimulus-bundle' => [
        'path' => './vendor/symfony/stimulus-bundle/assets/dist/loader.js',
    ],
    '@hotwired/turbo' => [
        'version' => '7.3.0',
    ],
    'codemirror' => [
        'version' => '6.0.2',
    ],
    '@codemirror/view' => [
        'version' => '6.37.1',
    ],
    '@codemirror/state' => [
        'version' => '6.5.2',
    ],
    '@codemirror/language' => [
        'version' => '6.11.1',
    ],
    '@codemirror/commands' => [
        'version' => '6.8.1',
    ],
    '@codemirror/search' => [
        'version' => '6.5.11',
    ],
    '@codemirror/autocomplete' => [
        'version' => '6.19.1',
    ],
    '@codemirror/lint' => [
        'version' => '6.8.5',
    ],
    'style-mod' => [
        'version' => '4.1.2',
    ],
    'w3c-keyname' => [
        'version' => '2.2.8',
    ],
    'crelt' => [
        'version' => '1.0.6',
    ],
    '@marijn/find-cluster-break' => [
        'version' => '1.0.2',
    ],
    '@lezer/common' => [
        'version' => '1.2.3',
    ],
    '@lezer/highlight' => [
        'version' => '1.2.1',
    ],
    '@codemirror/lang-json' => [
        'version' => '6.0.2',
    ],
    '@codemirror/lang-markdown' => [
        'version' => '6.5.0',
    ],
    '@codemirror/lang-html' => [
        'version' => '6.4.11',
    ],
    '@codemirror/stream-parser' => [
        'version' => '0.19.9',
    ],
    '@lezer/json' => [
        'version' => '1.0.3',
    ],
    '@lezer/markdown' => [
        'version' => '1.5.1',
    ],
    '@lezer/html' => [
        'version' => '1.3.12',
    ],
    '@codemirror/lang-css' => [
        'version' => '6.3.1',
    ],
    '@codemirror/lang-javascript' => [
        'version' => '6.2.4',
    ],
    '@codemirror/highlight' => [
        'version' => '0.19.8',
    ],
    '@lezer/lr' => [
        'version' => '1.4.2',
    ],
    '@lezer/css' => [
        'version' => '1.1.9',
    ],
    '@lezer/javascript' => [
        'version' => '1.5.1',
    ],
    '@codemirror/rangeset' => [
        'version' => '0.19.9',
    ],
    '@codemirror/text' => [
        'version' => '0.19.6',
    ],
    'alpinejs' => [
        'path' => './assets/js/alpinejs.esm.js',
    ],
    'apexcharts' => [
        'path' => './assets/js/apexcharts.esm.js',
    ],
];
