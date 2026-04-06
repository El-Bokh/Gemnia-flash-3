<?php

namespace Database\Seeders;

use App\Models\VisualStyle;
use Illuminate\Database\Seeder;

class VisualStyleSeeder extends Seeder
{
    public function run(): void
    {
        $styles = [
            [
                'name'            => 'Realistic',
                'slug'            => 'realistic',
                'description'     => 'Ultra realistic photography style with natural skin textures',
                'thumbnail'       => '/style-gallery/realistic.jpg',
                'prompt_prefix'   => 'ultra realistic, DSLR photography, natural skin texture, sharp focus, photorealistic, high dynamic range',
                'prompt_suffix'   => null,
                'negative_prompt' => 'cartoon, anime, drawing, painting, blurry, low quality',
                'category'        => 'photography',
                'is_active'       => true,
                'is_premium'      => false,
                'sort_order'      => 1,
            ],
            [
                'name'            => 'Anime',
                'slug'            => 'anime',
                'description'     => 'Japanese animation style with expressive features',
                'thumbnail'       => '/style-gallery/anime.jpg',
                'prompt_prefix'   => 'anime style, big expressive eyes, smooth shading, vibrant colors, japanese animation style, highly detailed',
                'prompt_suffix'   => null,
                'negative_prompt' => 'realistic, photograph, 3d render',
                'category'        => 'illustration',
                'is_active'       => true,
                'is_premium'      => false,
                'sort_order'      => 2,
            ],
            [
                'name'            => 'Watercolor',
                'slug'            => 'watercolor',
                'description'     => 'Soft watercolor painting with pastel tones',
                'thumbnail'       => '/style-gallery/watercolor.jpg',
                'prompt_prefix'   => 'watercolor painting, soft brush strokes, pastel colors, artistic paper texture, soft edges, hand painted look',
                'prompt_suffix'   => null,
                'negative_prompt' => 'digital, sharp edges, photograph, 3d',
                'category'        => 'art',
                'is_active'       => true,
                'is_premium'      => false,
                'sort_order'      => 3,
            ],
            [
                'name'            => 'Oil Painting',
                'slug'            => 'oil-painting',
                'description'     => 'Classical oil painting with rich textures',
                'thumbnail'       => '/style-gallery/oil-painting.jpg',
                'prompt_prefix'   => 'oil painting, classical art style, rich textures, visible brush strokes, renaissance style, dramatic lighting',
                'prompt_suffix'   => null,
                'negative_prompt' => 'digital, flat, cartoon, photograph',
                'category'        => 'art',
                'is_active'       => true,
                'is_premium'      => false,
                'sort_order'      => 4,
            ],
            [
                'name'            => 'Digital Art',
                'slug'            => 'digital-art',
                'description'     => 'Modern digital illustration with clean lines',
                'thumbnail'       => '/style-gallery/digital-art.jpg',
                'prompt_prefix'   => 'digital art, modern illustration, clean lines, soft shading, vibrant lighting, high detail, trending on artstation',
                'prompt_suffix'   => null,
                'negative_prompt' => 'blurry, low quality, sketch',
                'category'        => 'digital',
                'is_active'       => true,
                'is_premium'      => false,
                'sort_order'      => 5,
            ],
            [
                'name'            => 'Cyberpunk',
                'slug'            => 'cyberpunk',
                'description'     => 'Futuristic neon-lit cyberpunk atmosphere',
                'thumbnail'       => '/style-gallery/cyberpunk.jpg',
                'prompt_prefix'   => 'cyberpunk style, neon lights, glowing elements, futuristic atmosphere, purple and blue tones, cinematic lighting, high contrast',
                'prompt_suffix'   => null,
                'negative_prompt' => 'natural, daylight, vintage, old',
                'category'        => 'digital',
                'is_active'       => true,
                'is_premium'      => false,
                'sort_order'      => 6,
            ],
            [
                'name'            => 'Minimalist',
                'slug'            => 'minimalist',
                'description'     => 'Clean minimalist design with simple shapes',
                'thumbnail'       => '/style-gallery/minimalist.jpg',
                'prompt_prefix'   => 'minimalist, flat design, simple shapes, limited color palette, clean background, vector style',
                'prompt_suffix'   => null,
                'negative_prompt' => 'complex, detailed, realistic, busy',
                'category'        => 'design',
                'is_active'       => true,
                'is_premium'      => false,
                'sort_order'      => 7,
            ],
            [
                'name'            => 'Pop Art',
                'slug'            => 'pop-art',
                'description'     => 'Bold pop art style inspired by Andy Warhol',
                'thumbnail'       => '/style-gallery/pop-art.jpg',
                'prompt_prefix'   => 'pop art style, bold colors, high contrast, comic style, graphic patterns, vibrant background, andy warhol inspired',
                'prompt_suffix'   => null,
                'negative_prompt' => 'realistic, muted colors, dark, photograph',
                'category'        => 'art',
                'is_active'       => true,
                'is_premium'      => true,
                'sort_order'      => 8,
            ],
        ];

        foreach ($styles as $style) {
            VisualStyle::updateOrCreate(
                ['slug' => $style['slug']],
                $style
            );
        }
    }
}
