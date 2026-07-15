<?php

namespace Database\Seeders;

use App\Models\DeviceType;
use Illuminate\Database\Seeder;

class DeviceTypeSeeder extends Seeder
{
    public function run(): void
    {
        $types = [
            ['name' => 'Desktop', 'slug' => 'desktop'],
            ['name' => 'Monitor', 'slug' => 'monitor'],
            ['name' => 'Motherboard', 'slug' => 'motherboard'],
            ['name' => 'Memory', 'slug' => 'memory'],
            ['name' => 'Hard Disk', 'slug' => 'hard-disk'],
            ['name' => 'DVD Drive', 'slug' => 'dvd-drive'],
            ['name' => 'Mouse', 'slug' => 'mouse'],
            ['name' => 'Keyboard', 'slug' => 'keyboard'],
            ['name' => 'Printer', 'slug' => 'printer'],
            ['name' => 'UPS', 'slug' => 'ups'],
            ['name' => 'AVR', 'slug' => 'avr'],
        ];

        foreach ($types as $t) {
            DeviceType::updateOrCreate(['slug' => $t['slug']], $t);
        }
    }
}