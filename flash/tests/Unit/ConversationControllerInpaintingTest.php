<?php

namespace Tests\Unit;

use App\Http\Controllers\Api\ConversationController;
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
}