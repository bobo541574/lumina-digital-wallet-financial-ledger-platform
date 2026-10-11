<?php

use Illuminate\Support\Facades\Route;
use Symfony\Component\Yaml\Yaml;

// Keeps docs/architecture/api/openapi.yaml in sync with the real route table:
// every documented path must resolve to a registered route and vice-versa.
beforeEach(function () {
    $this->apiBase = '/api/v1';
});

test('openapi specification parses', function () {
    $spec = Yaml::parseFile(base_path('docs/architecture/api/openapi.yaml'));

    expect($spec)->toHaveKey('openapi')
        ->and($spec['openapi'])->toBe('3.1.0')
        ->and($spec['paths'])->toBeArray()
        ->and($spec['components']['responses'])->toBeArray();
});

test('every documented path is registered', function () {
    $spec = Yaml::parseFile(base_path('docs/architecture/api/openapi.yaml'));

    $documented = collect(array_keys($spec['paths']))
        ->map(fn (string $path) => $this->apiBase.($path === '/' ? '' : $path))
        ->unique();

    $registered = collect(Route::getRoutes()->getRoutes())
        ->filter(fn ($route) => str_starts_with($route->uri, 'api/v1'))
        ->map(fn ($route) => '/'.$route->uri)
        ->unique()
        ->values();

    foreach ($documented as $path) {
        expect($registered->contains($path), "OpenAPI path {$path} has no matching route.")->toBeTrue();
    }
});

test('every registered api/v1 route is documented', function () {
    $spec = Yaml::parseFile(base_path('docs/architecture/api/openapi.yaml'));

    $documented = collect(array_keys($spec['paths']))
        ->map(fn (string $path) => $this->apiBase.($path === '/' ? '' : $path))
        ->unique()
        ->values();

    $undocumented = collect(Route::getRoutes()->getRoutes())
        ->filter(fn ($route) => str_starts_with($route->uri, 'api/v1'))
        ->map(fn ($route) => '/'.$route->uri)
        ->unique()
        ->values()
        ->reject(fn (string $path) => $documented->contains($path))
        ->values();

    expect($undocumented)->toBeEmpty('Routes missing from the OpenAPI contract: '.$undocumented->implode(', '));
});
