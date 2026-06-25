<?php

namespace Database\Seeders;

use App\Models\Promo;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;

class PromoSeeder extends Seeder
{
    public function run(): void
    {
        $source = resource_path('images/promos/recluta.png');
        $target = storage_path('app/public/promos/recluta.png');

        if (! File::exists(dirname($target))) {
            File::makeDirectory(dirname($target), 0775, true);
        }

        if (File::exists($source) && ! File::exists($target)) {
            File::copy($source, $target);
        }

        Promo::firstOrCreate(
            ['id' => 1],
            [
                'image' => 'promos/recluta.png',
            ]
        );
    }
}