<?php

namespace App\Helpers;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class Helper
{
    // ! File or Image Upload
    public static function fileUpload($file, string $folder, string $name): ?string
    {
        if (! $file->isValid()) {
            return null;
        }

        $imageName = Str::slug($name).'.'.$file->extension();
        $path = public_path('uploads/'.$folder);
        if (! file_exists($path)) {
            mkdir($path, 0755, true);
        }
        $file->move($path, $imageName);

        return 'uploads/'.$folder.'/'.$imageName;
    }

    // ! File or Image Delete
    public static function fileDelete(string $path): void
    {
        if (file_exists($path)) {
            unlink($path);
        }
    }

    // ! Generate Slug
    public static function makeSlug(string $title, $table): string
    {
        $slug = Str::slug($title);
        while (DB::table($table)->where('slug', $slug)->exists()) {
            $randomString = Str::random(5);
            $slug = Str::slug($title).'-'.$randomString;
        }

        return $slug;
    }

    public static function generateUniqueSlug($title, $table, $id = null)
    {
        // Create an initial slug using Laravel's Str::slug
        $slug = Str::slug($title);
        $original_slug = $slug;

        $count = DB::table($table)->where('slug', $slug)
            ->when($id, function ($query) use ($id) {
                return $query->where('id', '!=', $id); // Exclude current record if updating
            })
            ->count();

        // If the slug exists, append a number to make it unique
        $index = 1;
        while ($count > 0) {
            $slug = $original_slug.'-'.$index;
            $count = DB::table($table)->where('slug', $slug)
                ->when($id, function ($query) use ($id) {
                    return $query->where('id', '!=', $id);
                })
                ->count();
            $index++;
        }

        return $slug;
    }

    public static function getFileName($file): string
    {
        return time().'_'.pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
    }
}
