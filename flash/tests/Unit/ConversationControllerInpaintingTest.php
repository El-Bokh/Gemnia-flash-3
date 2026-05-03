<?php

namespace Tests\Unit;

use App\Http\Controllers\Api\ConversationController;
use App\Jobs\StartVeoVideoGeneration;
use App\Models\AiRequest;
use App\Models\Product;
use ReflectionMethod;
use Tests\TestCase;

class ConversationControllerInpaintingTest extends TestCase
{
    public function test_arabic_recolor_prompt_uses_object_aware_recolor_strategy(): void
    {
        $controller = new ConversationController();

        $detectMethod = new ReflectionMethod($controller, 'isObjectAwareRecolorPrompt');
        $detectMethod->setAccessible(true);

        $promptMethod = new ReflectionMethod($controller, 'buildObjectAwareRecolorPrompt');
        $promptMethod->setAccessible(true);

        $userPrompt = 'غيّر لون الجزء المحدد إلى اللون الأحمر مع الحفاظ على باقي الصورة كما هي';

        $this->assertTrue($detectMethod->invoke($controller, $userPrompt));

        $processedPrompt = $promptMethod->invoke($controller, $userPrompt);

        $this->assertStringContainsString('Recolor the chair frame, chair seat, chair back, chair spindles, and the draped shawl touched by the mask to red', $processedPrompt);
        $this->assertStringContainsString('Preserve the original chair geometry, wood grain, slats, fabric folds, contours, texture, lighting, shadows, perspective, and all details', $processedPrompt);
        $this->assertStringContainsString('Do not fill the mask shape and do not replace the objects', $processedPrompt);
    }

    public function test_direct_arabic_recolor_command_is_detected(): void
    {
        $controller = new ConversationController();

        $detectMethod = new ReflectionMethod($controller, 'isObjectAwareRecolorPrompt');
        $detectMethod->setAccessible(true);

        $this->assertTrue($detectMethod->invoke($controller, 'لون الكرسي والشال احمر'));
    }

    public function test_inpainting_prompt_preserves_background_for_insertions(): void
    {
        $controller = new ConversationController();

        $promptMethod = new ReflectionMethod($controller, 'buildInpaintingPrompt');
        $promptMethod->setAccessible(true);

        $processedPrompt = $promptMethod->invoke($controller, 'شيل الكتب المحدده وحط دب لعبه صغير');

        $this->assertStringContainsString('Requested edit: شيل الكتب المحدده وحط دب لعبه صغير', $processedPrompt);
        $this->assertStringContainsString('place the requested object inside the same selected footprint, not larger', $processedPrompt);
        $this->assertStringContainsString('the final white-mask area must not contain the removed selected object', $processedPrompt);
        $this->assertStringContainsString('reconstruct the underlying surface only inside the white mask', $processedPrompt);
        $this->assertStringContainsString('Do not alter, blur, repaint, relight, or reinterpret any background outside the white mask', $processedPrompt);
        $this->assertStringContainsString('windows, shelves, desk edges, walls, books outside the mask, and surrounding objects', $processedPrompt);
    }

    public function test_arabic_video_follow_up_is_detected_and_keeps_previous_context(): void
    {
        $controller = new ConversationController();

        $detectMethod = new ReflectionMethod($controller, 'isVideoFollowUpPrompt');
        $detectMethod->setAccessible(true);

        $mergeMethod = new ReflectionMethod($controller, 'mergeVideoFollowUpPrompt');
        $mergeMethod->setAccessible(true);

        $basePrompt = 'Create a 4-second cinematic product video of a premium leather bag on a clean studio table.';
        $followUpPrompt = 'خليلي الشنطه الي في الاعلان حمراء';

        $this->assertTrue($detectMethod->invoke($controller, $followUpPrompt));

        $processedPrompt = $mergeMethod->invoke($controller, $basePrompt, $followUpPrompt, false, false);

        $this->assertStringContainsString($basePrompt, $processedPrompt);
        $this->assertStringContainsString('Follow-up edit: ' . $followUpPrompt, $processedPrompt);
        $this->assertStringContainsString('Use the previous video prompt as the visual anchor', $processedPrompt);
        $this->assertStringContainsString('Do not introduce unrelated people', $processedPrompt);
    }

    public function test_standalone_arabic_video_prompt_is_not_forced_to_follow_up(): void
    {
        $controller = new ConversationController();

        $detectMethod = new ReflectionMethod($controller, 'isVideoFollowUpPrompt');
        $detectMethod->setAccessible(true);

        $this->assertFalse($detectMethod->invoke($controller, 'اعلان لشنطه في وضع سينمائي'));
    }

    public function test_video_prompt_for_inpainting_reference_uses_user_prompt_not_internal_mask_prompt(): void
    {
        $controller = new ConversationController();

        $method = new ReflectionMethod($controller, 'videoBasePromptForReferencedRequest');
        $method->setAccessible(true);

        $request = new AiRequest([
            'type' => 'inpainting',
            'user_prompt' => 'شيل الكرسي وخلي ولد واقف وساند على الكتاب المفتوح',
            'processed_prompt' => 'Requested edit: شيل الكرسي... Use image 1 as the source and image 2 as the black-and-white mask.',
        ]);

        $this->assertSame(
            'شيل الكرسي وخلي ولد واقف وساند على الكتاب المفتوح',
            $method->invoke($controller, $request),
        );
    }

    public function test_veo_prompt_only_fallback_neutralizes_minor_age_terms(): void
    {
        $job = new StartVeoVideoGeneration(1, 1);

        $method = new ReflectionMethod($job, 'buildPromptOnlyReferenceFallback');
        $method->setAccessible(true);

        $processedPrompt = $method->invoke(
            $job,
            'Requested edit: شيل الكرسي وخلي ولد واقف وساند على الكتاب المفتوح',
        );

        $this->assertStringContainsString('طالب واقف', $processedPrompt);
        $this->assertStringNotContainsString('ولد', $processedPrompt);
        $this->assertStringContainsString('Generate the video from this textual visual context only', $processedPrompt);
    }

    public function test_product_template_prompt_preserves_uploaded_product_and_blocks_fake_branding(): void
    {
        $controller = new ConversationController();

        $promptMethod = new ReflectionMethod($controller, 'buildProductTemplatePrompt');
        $promptMethod->setAccessible(true);

        $modeMethod = new ReflectionMethod($controller, 'normalizeModeForProductTemplate');
        $modeMethod->setAccessible(true);

        $urlMethod = new ReflectionMethod($controller, 'productTemplateImageUrl');
        $urlMethod->setAccessible(true);

        $product = new Product([
            'slug' => 'bag-template',
            'thumbnail' => '/storage/product-thumbnails/bag.png',
            'hidden_prompt' => 'Create a premium handbag product showcase on a marble platform.',
        ]);

        $processedPrompt = $promptMethod->invoke($controller, $product, 'خلي المنتج بتاعي زي القالب', true, true);

        $this->assertSame('image', $modeMethod->invoke($controller, 'text', $product));
        $this->assertSame('/storage/product-thumbnails/bag.png', $urlMethod->invoke($controller, $product));
        $this->assertStringContainsString('Use the uploaded product reference image as the exact product to advertise', $processedPrompt);
        $this->assertStringContainsString('Use the selected template reference image only for scene layout', $processedPrompt);
        $this->assertStringContainsString('Do not invent, add, or alter brand names', $processedPrompt);
        $this->assertStringContainsString('books, boxes, cards, labels, tags, packaging, posters, or papers', $processedPrompt);
        $this->assertStringContainsString('Template prompt: Create a premium handbag product showcase on a marble platform.', $processedPrompt);
    }
}
