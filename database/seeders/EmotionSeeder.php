<?php

namespace Database\Seeders;

use App\Models\Emotion;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;

class EmotionSeeder extends Seeder
{
    public function run(): void
    {
        $emotions = [
            ['name' => 'Happy', 'icon' => 'happy.svg'],
            ['name' => 'Fine', 'icon' => 'fine.svg'],
            ['name' => 'Ok', 'icon' => 'ok.svg'],
            ['name' => 'Sad', 'icon' => 'sad.svg'],
            ['name' => 'Angry', 'icon' => 'angry.svg'],
        ];

        foreach ($emotions as $index => $emotionData) {
            $newEmotion = Emotion::create(['name' => $emotionData['name']]);

            $sourcePath = database_path("seeders/icons/{$emotionData['icon']}");

            if (File::exists($sourcePath)) {
                $fileName = $emotionData['icon'];
                $directory = 'emotion_icons';

                $publicPath = public_path($directory);

                if (!File::isDirectory($publicPath)) {
                    File::makeDirectory($publicPath, 0755, true);
                }

                File::copy($sourcePath, $publicPath . '/' . $fileName);

                $newEmotion->media()->create([
                    'collection' => 'emotion_icons',
                    'file_path'  => "{$directory}/{$fileName}",
                    'mime_type'  => File::mimeType($sourcePath),
                    'size'       => File::size($sourcePath),
                    'file_name'  => $fileName,
                    'sort_order' => $index
                ]);
            }
        }
    }
}