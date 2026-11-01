<?php

namespace TomatoPHP\FilamentMediaManager\Tests;

use Illuminate\Support\Arr;

it('translates every English text into German', function () {
    $keys = fn (string $locale): array => array_keys(Arr::dot(require __DIR__ . "/../../resources/lang/{$locale}/messages.php"));

    expect($keys('de'))->toEqualCanonicalizing($keys('en'));
});

it('uses the German texts in a German panel', function () {
    app()->setLocale('de');

    expect(trans('filament-media-manager::messages.media.meta.edit-media'))->toBe('Medium bearbeiten');
});
