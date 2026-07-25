<?php

namespace App\Http\Controllers\Job\JobPosting;

use App\Http\Controllers\Controller;
use App\Services\Jobs\AiService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class AiJobController extends Controller
{
    protected AiService $aiService;

    public function __construct(AiService $aiService)
    {
        $this->aiService = $aiService;
    }

    /**
     * Get available AI models
     */
    public function getModels()
    {
        return response()->json([
            'success' => true,
            'data' => $this->aiService->getAvailableModels(),
            'default' => config('ai.default', 'gemini'),
        ]);
    }

    /**
     * Extract job data from text or URL
     */
    public function extractJobData(Request $request)
    {
        $request->validate([
            'model' => 'nullable|string',
            'content' => 'required|string',
            'source_type' => 'required|in:text,url',
            'country' => 'nullable|string|size:2',
        ]);

        try {
            $model = $request->model ?? config('ai.default');
            $result = $this->aiService->extractJobData(
                $request->content,
                $request->source_type,
                $model
            );

            return response()->json([
                'success' => true,
                'data' => $result,
            ]);
        } catch (\Exception $e) {
            // Get a clean error message
            $errorMessage = $e->getMessage();
            
            // Clean up the error message
            $errorMessage = preg_replace('/\[[^\]]+\]\s*/', '', $errorMessage);
            $errorMessage = preg_replace('/All AI models failed:\s*/', '', $errorMessage);
            $errorMessage = trim($errorMessage);
            
            Log::error('AI extraction failed', [
                'model' => $model ?? 'unknown',
                'error' => $e->getMessage(),
            ]);
            
            return response()->json([
                'success' => false,
                'error' => $errorMessage ?: 'AI extraction failed. Please check your API keys and try again.',
            ], 500);
        }
    }

    /**
     * Extract job data from image
     */
    public function extractFromImage(Request $request)
    {
        $request->validate([
            'model' => 'nullable|string',
            'image_base64' => 'required|string',
            'country' => 'nullable|string|size:2',
        ]);

        try {
            $model = $request->model ?? config('ai.default');
            $result = $this->aiService->extractFromImage(
                $request->image_base64,
                $model
            );

            return response()->json([
                'success' => true,
                'data' => $result,
            ]);
        } catch (\Exception $e) {
            Log::error('Image extraction failed', [
                'model' => $model ?? 'unknown',
                'error' => $e->getMessage(),
            ]);
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Enhance a field
     */
    public function enhanceField(Request $request)
    {
        $request->validate([
            'model' => 'nullable|string',
            'field_name' => 'required|string',
            'content' => 'required|string',
            'instruction' => 'required|string',
        ]);

        try {
            $model = $request->model ?? config('ai.default');
            $enhanced = $this->aiService->enhanceField(
                $request->field_name,
                $request->content,
                $request->instruction,
                $model
            );

            return response()->json([
                'success' => true,
                'enhanced' => $enhanced,
            ]);
        } catch (\Exception $e) {
            Log::error('AI enhance field failed', [
                'model' => $model ?? 'unknown',
                'field_name' => $request->field_name,
                'error' => $e->getMessage(),
            ]);
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Generate full job post from title
     */
    public function generateFromTitle(Request $request)
    {
        $request->validate([
            'model' => 'nullable|string',
            'title' => 'required|string',
            'company' => 'nullable|string',
            'country' => 'nullable|string|size:2',
        ]);

        try {
            $model = $request->model ?? config('ai.default');
            $result = $this->aiService->generateFromTitle(
                $request->title,
                $request->company,
                $request->country,
                $model
            );

            return response()->json([
                'success' => true,
                'data' => $result,
            ]);
        } catch (\Exception $e) {
            Log::error('AI generate from title failed', [
                'model' => $model ?? 'unknown',
                'title' => $request->title,
                'error' => $e->getMessage(),
            ]);
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
