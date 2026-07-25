<?php

namespace App\Services\Jobs;

use Illuminate\Support\Facades\{ Http, Log, Cache };
use Illuminate\Support\Str;
use Carbon\Carbon;

class AiService
{
    protected array $config;
    protected array $fallbackModels;
    protected string $defaultModel;
    protected int $timeout;
    protected int $retryAttempts;
    protected int $retryDelay;
    protected array $countrySettings;

    public function __construct()
    {
        $this->config = config('ai.models', []);
        $this->fallbackModels = config('ai.fallback_models', ['openai', 'claude']);
        $this->defaultModel = config('ai.default', 'claude');
        $this->timeout = config('ai.timeout', 90);
        $this->retryAttempts = config('ai.retries.attempts', 2);
        $this->retryDelay = config('ai.retries.delay', 200);
        $this->countrySettings = config('ai.country_settings', []);
    }

    /**
     * Get available models with their configuration
     */
    public function getAvailableModels(): array
    {
        $models = [];
        foreach ($this->config as $key => $model) {
            if ($this->isModelEnabled($key)) {
                $models[$key] = [
                    'name' => $model['name'] ?? ucfirst($key),
                    'icon' => $model['icon'] ?? 'ti-robot',
                    'color' => $model['color'] ?? '#6c757d',
                    'supports' => $model['supports'] ?? ['text'],
                    'is_default' => $key === $this->defaultModel,
                ];
            }
        }
        return $models;
    }

    /**
     * Check if a model is enabled (has API key)
     */
    public function isModelEnabled(string $model): bool
    {
        $config = $this->config[$model] ?? null;
        return $config && !empty($config['api_key']);
    }

    /**
     * Get model configuration
     */
    public function getModelConfig(string $model): ?array
    {
        return $this->config[$model] ?? null;
    }

    /**
     * Extract job data from text or URL
     */
    public function extractJobData(string $content, string $sourceType = 'text', ?string $model = null): array
    {
        $model = $model ?? $this->defaultModel;
        $prompt = $this->buildExtractionPrompt($content, $sourceType);

        return $this->callWithFallback($model, $prompt, function($m, $key) use ($prompt) {
            return $this->callAiApi($m, $key, $prompt);
        });
    }

    /**
     * Extract job data from image
     */
    public function extractFromImage(string $imageBase64, ?string $model = null): array
    {
        $model = $model ?? $this->defaultModel;

        if (!$this->isModelEnabled($model)) {
            throw new \Exception("Model '{$model}' is not enabled or configured.");
        }

        $config = $this->getModelConfig($model);
        if (!in_array('image', $config['supports'] ?? [])) {
            throw new \Exception("Model '{$model}' does not support image processing.");
        }

        $prompt = $this->buildImageExtractionPrompt();

        return $this->callWithFallback($model, $prompt, function($m, $key) use ($prompt, $imageBase64) {
            return $this->callAiApi($m, $key, $prompt, $imageBase64);
        });
    }

    /**
     * Enhance a field using AI
     */
    public function enhanceField(string $fieldName, string $content, string $instruction, ?string $model = null): string
    {
        $model = $model ?? $this->defaultModel;
        $prompt = $this->buildEnhancePrompt($fieldName, $content, $instruction);

        $result = $this->callWithFallback($model, $prompt, function($m, $key) use ($prompt) {
            return $this->callAiApi($m, $key, $prompt);
        });

        return $this->extractTextFromResult($result);
    }

    /**
     * Generate full job post from title
     */
    public function generateFromTitle(string $title, ?string $company = null, ?string $country = null, ?string $model = null): array
    {
        $model = $model ?? $this->defaultModel;
        $prompt = $this->buildGeneratePrompt($title, $company, $country);

        $result = $this->callWithFallback($model, $prompt, function($m, $key) use ($prompt) {
            return $this->callAiApi($m, $key, $prompt);
        });

        $data = $this->ensureArray($result);
        return $this->applySmartDefaults($data, $country);
    }

    /**
     * Call AI API with fallback support - with better error messages
     */
    protected function callWithFallback(string $primaryModel, string $prompt, callable $callFn): array
    {
        $errors = [];
        $models = array_unique(array_merge([$primaryModel], $this->fallbackModels));

        foreach ($models as $model) {
            if (!$this->isModelEnabled($model)) {
                $errors[] = "Model '{$model}' is not enabled (missing API key).";
                continue;
            }

            try {
                $config = $this->getModelConfig($model);
                $result = $callFn($model, $config['api_key']);
                return $this->ensureArray($result);
            } catch (\Exception $e) {
                // Extract a clean error message
                $errorMsg = $e->getMessage();
                // Try to extract just the API error message
                if (strpos($errorMsg, 'API error') !== false) {
                    $parts = explode('API error', $errorMsg);
                    $errorMsg = trim(end($parts));
                    // Try to decode JSON error
                    $jsonStart = strpos($errorMsg, '{');
                    if ($jsonStart !== false) {
                        $jsonPart = substr($errorMsg, $jsonStart);
                        $decoded = json_decode($jsonPart, true);
                        if ($decoded && isset($decoded['error'])) {
                            $errorMsg = $decoded['error']['message'] ?? $decoded['error'] ?? $errorMsg;
                        }
                    }
                }
                $errors[] = "[{$model}] " . $errorMsg;
                Log::warning("AI call failed for model '{$model}'", [
                    'error' => $errorMsg,
                    'fallback' => true,
                ]);
            }
        }

        $errorMessage = "All AI models failed: " . implode(' | ', $errors);
        throw new \Exception($errorMessage);
    }


    /**
     * Call specific AI API
     */
    protected function callAiApi(string $model, string $apiKey, string $prompt, ?string $imageBase64 = null): array|string
    {
        $config = $this->getModelConfig($model);
        if (!$config) {
            throw new \Exception("Model '{$model}' not configured.");
        }

        $endpoint = $config['endpoint'];
        $modelName = $config['model'];
        $maxTokens = $config['max_tokens'] ?? 4096;

        $headers = ['Content-Type' => 'application/json'];
        $body = [];

        // Use the model name directly instead of provider
        switch ($model) {
            case 'openai':
                $headers['Authorization'] = "Bearer {$apiKey}";
                $content = $imageBase64
                    ? [
                        ['type' => 'image_url', 'image_url' => ['url' => "data:image/jpeg;base64,{$imageBase64}"]],
                        ['type' => 'text', 'text' => $prompt],
                    ]
                    : $prompt;
                $body = [
                    'model' => $modelName,
                    'max_tokens' => $maxTokens,
                    'messages' => [['role' => 'user', 'content' => $content]],
                ];
                break;

            case 'claude':
                $headers['x-api-key'] = $apiKey;
                $headers['anthropic-version'] = '2023-06-01';
                $messages = $imageBase64
                    ? [[
                        'role' => 'user',
                        'content' => [
                            ['type' => 'image', 'source' => ['type' => 'base64', 'media_type' => 'image/jpeg', 'data' => $imageBase64]],
                            ['type' => 'text', 'text' => $prompt],
                        ],
                    ]]
                    : [['role' => 'user', 'content' => $prompt]];
                $body = [
                    'model' => $modelName,
                    'max_tokens' => $maxTokens,
                    'messages' => $messages,
                ];
                break;

            case 'gemini':
                $endpoint = "https://generativelanguage.googleapis.com/v1beta/models/gemini-3.5-flash:generateContent?key={$apiKey}";
                $parts = $imageBase64
                    ? [
                        ['inline_data' => ['mime_type' => 'image/jpeg', 'data' => $imageBase64]],
                        ['text' => $prompt],
                    ]
                    : [['text' => $prompt]];
                $body = [
                    'contents' => [['parts' => $parts]],
                    'generationConfig' => ['maxOutputTokens' => $maxTokens],
                ];
                break;

            case 'grok':
                $headers['Authorization'] = "Bearer {$apiKey}";
                $body = [
                    'model' => $modelName,
                    'max_tokens' => $maxTokens,
                    'messages' => [['role' => 'user', 'content' => $prompt]],
                ];
                break;

            case 'cohere':
                $headers['Authorization'] = "Bearer {$apiKey}";
                $body = [
                    'model' => $modelName,
                    'max_tokens' => $maxTokens,
                    'messages' => [['role' => 'user', 'content' => $prompt]],
                ];
                break;

            case 'mistral':
                $headers['Authorization'] = "Bearer {$apiKey}";
                $body = [
                    'model' => $modelName,
                    'max_tokens' => $maxTokens,
                    'messages' => [['role' => 'user', 'content' => $prompt]],
                ];
                break;

            default:
                throw new \Exception("Unsupported model: {$model}");
        }

        $response = Http::timeout($this->timeout)
            ->retry($this->retryAttempts, $this->retryDelay)
            ->withHeaders($headers)
            ->post($endpoint, $body);

        if (!$response->successful()) {
            // Get a cleaner error message
            $errorBody = $response->body();
            $errorData = json_decode($errorBody, true);
            $errorMessage = $errorData['error']['message'] ?? $errorData['error'] ?? $errorBody;
            throw new \Exception("API error ({$model}): " . $errorMessage);
        }

        $data = $response->json();
        $text = '';

        switch ($model) {
            case 'openai':
                $text = $data['choices'][0]['message']['content'] ?? '';
                break;
            case 'claude':
                $text = $data['content'][0]['text'] ?? '';
                break;
            case 'gemini':
                $text = $data['candidates'][0]['content']['parts'][0]['text'] ?? '';
                break;
            case 'grok':
            case 'cohere':
            case 'mistral':
                $text = $data['choices'][0]['message']['content'] ?? '';
                break;
            default:
                $text = json_encode($data);
        }

        // Strip markdown fences
        $clean = preg_replace('/^```(?:json)?\s*/i', '', $text);
        $clean = preg_replace('/\s*```\s*$/i', '', $clean);
        $clean = preg_replace('/```(?:json)?/i', '', $clean);
        $clean = trim($clean);

        $decoded = json_decode($clean, true);
        return $decoded ?? $text;
    }


    /**
     * Extract plain text from various result shapes
     */
    protected function extractTextFromResult(mixed $result): string
    {
        if (is_string($result)) return $result;

        if (is_array($result)) {
            foreach (['text', 'content', 'enhanced', 'job_description'] as $key) {
                if (isset($result[$key]) && is_string($result[$key])) {
                    return $result[$key];
                }
            }
            $first = reset($result);
            if (is_string($first)) return $first;
            return json_encode($result);
        }

        return (string) $result;
    }

    /**
     * Ensure result is an array
     */
    protected function ensureArray(mixed $result): array
    {
        if (is_array($result)) {
            return $result;
        }

        if (is_string($result)) {
            // Strip markdown fences
            $clean = preg_replace('/^```(?:json)?\s*/i', '', $result);
            $clean = preg_replace('/\s*```\s*$/i', '', $clean);
            $clean = preg_replace('/```(?:json)?/i', '', $clean);
            $clean = trim($clean);

            $decoded = json_decode($clean, true);
            if (is_array($decoded)) {
                return $decoded;
            }

            Log::warning('AI returned unparseable string', ['snippet' => mb_substr($result, 0, 300)]);
            return [];
        }

        return [];
    }




    /**
     * Build extraction prompt - MODIFIED to preserve content exactly
     */
    protected function buildExtractionPrompt(string $content, string $sourceType): string
    {
        $twoWeeksAhead = Carbon::now()->addWeeks(2)->format('Y-m-d');
        
        $sourceInstruction = $sourceType === 'url' 
            ? 'The content is a URL. You MUST visit this URL, read the entire page content, and extract the job posting details from it. If the URL does not contain a valid job posting, return an error.'
            : 'The content is pasted job description text. Extract all job details from it exactly as provided.';

        return <<<PROMPT
You are an expert job-board agent. Your task is to extract job posting information.

SOURCE TYPE: {$sourceType}
{$sourceInstruction}

CRITICAL RULES:
1. If this is a URL, you MUST fetch and read the page content to extract job details. If the URL does not contain a valid job posting, return an error response.
2. Return ONLY a valid JSON object — no explanation, no markdown, no code blocks.
3. For pasted content, preserve the EXACT text as provided. DO NOT summarize, rewrite, or modify the content in any way.
4. For HTML fields (job_description, responsibilities, qualifications, application_procedure), preserve the EXACT formatting including tables, lists, and structure.
5. If any field is completely missing from the content, apply the smart defaults below.
6. All text should be in Arial font style.

SMART DEFAULTS (apply ONLY when field is completely missing):
- employment_type: "full-time"
- deadline: "{$twoWeeksAhead}"
- experience_level_name: "entry level"
- education_level_name: "Certificate"
- location_type: "on-site"
- is_telephone_call: true (if telephone is present but WhatsApp is not explicitly mentioned)
- is_whatsapp_contact: false (unless WhatsApp is explicitly mentioned)

FIELDS TO EXTRACT (preserve exact content for each):
{
  "job_title": "exact job title from content",
  "company_name": "exact company name from content",
  "job_description": "EXACT job description as provided — preserve all HTML, tables, formatting, lists exactly as they appear. DO NOT modify or summarize.",
  "responsibilities": "EXACT responsibilities as provided — preserve all HTML, lists, and formatting. DO NOT modify or summarize.",
  "qualifications": "EXACT qualifications as provided — preserve all HTML, lists, and formatting. DO NOT modify or summarize.",
  "skills": "EXACT skills as provided — preserve the exact format. DO NOT modify or summarize.",
  "application_procedure": "EXACT application procedure as provided — preserve all HTML, formatting, and instructions. DO NOT modify or summarize.",
  "email": "contact email if mentioned, else null",
  "telephone": "phone number if mentioned, else null",
  "deadline": "application deadline in YYYY-MM-DD format if mentioned, else use default",
  "duty_station": "office or work location if mentioned, else null",
  "location_type": "remote|hybrid|on-site if mentioned, else default",
  "employment_type": "full-time|part-time|contract|internship|volunteer|temporary if mentioned, else default",
  "salary_amount": "numeric salary amount if mentioned, else null",
  "payment_period": "monthly|yearly|weekly|daily|hourly if mentioned, else null",
  "currency": "currency code if mentioned, else use country default",
  "meta_description": "155-character SEO description generated from job title and content",
  "keywords": "comma-separated SEO keywords generated from job title",
  "experience_level_name": "entry level|junior|mid level|senior|executive if mentioned, else default",
  "education_level_name": "Certificate|Diploma|Bachelor's Degree|Master's Degree if mentioned, else default",
  "industry_name": "industry sector if mentioned, else null",
  "category_name": "job category if mentioned, else null",
  "country_code": "country code from content or context, else null",
  "is_urgent": false,
  "is_featured": false,
  "is_resume_required": true,
  "is_cover_letter_required": false,
  "is_academic_documents_required": false,
  "is_application_required": false,
  "is_whatsapp_contact": false,
  "is_telephone_call": false,
  "work_hours": "work schedule if mentioned, else null"
}

CONTENT TO EXTRACT FROM:
---
{$content}
---

ERROR RESPONSE (if URL contains no job posting):
{
  "error": true,
  "message": "No valid job posting found at the provided URL. Please check the URL and try again."
}
PROMPT;
    }

    /**
     * Build image extraction prompt - MODIFIED to preserve content
     */
    protected function buildImageExtractionPrompt(): string
    {
        return "Extract all job information visible in this image. Preserve the EXACT text as shown. Do NOT summarize or modify. Return a complete JSON object with the same fields as a standard job extraction. Preserve all formatting, tables, and lists exactly as they appear in the image.";
    }

    /**
     * Build enhance field prompt - MODIFIED
     */
    protected function buildEnhancePrompt(string $fieldName, string $content, string $instruction): string
    {
        return <<<PROMPT
You are an expert HR copywriter. Your task: {$instruction}

RULES:
- Preserve the EXACT original content structure and formatting.
- Only improve clarity and professionalism, do not change the meaning or remove content.
- Use Arial font style throughout.
- Return ONLY the improved content as clean HTML.
- Use <p> for paragraphs and <ul><li> for lists.
- Do NOT include explanations, markdown fences, or code blocks.

CURRENT CONTENT (preserve this structure):
{$content}
PROMPT;
    }

    /**
     * Build generate from title prompt - MODIFIED
     */
    protected function buildGeneratePrompt(string $title, ?string $company, ?string $country): string
    {
        $companyText = $company ? " at {$company}" : '';
        $countryText = $country ? " in {$country}" : ' in East Africa';
        $deadline = Carbon::now()->addWeeks(2)->format('Y-m-d');

        return <<<PROMPT
You are an expert HR professional and job board agent. Generate a complete, professional job posting for a "{$title}"{$companyText}{$countryText}.

RULES:
- Use Arial font style throughout.
- Return ONLY a valid JSON object — no explanation, no markdown, no code blocks.

{
  "job_description": "3-4 paragraph description as HTML with <p> tags — include role overview, company culture, and why someone should apply",
  "responsibilities": "6-8 responsibilities as HTML <ul><li> list — be specific and action-oriented",
  "qualifications": "required and preferred qualifications as HTML <ul><li> list with two sections",
  "skills": "comma-separated list of 8-12 relevant skills",
  "meta_description": "155-character SEO meta description",
  "keywords": "comma-separated SEO keywords",
  "experience_level_name": "entry level|junior|mid level|senior|executive",
  "education_level_name": "Certificate|Diploma|Bachelor's Degree|Master's Degree",
  "employment_type": "full-time|part-time|contract|internship|volunteer|temporary",
  "location_type": "on-site|remote|hybrid",
  "deadline": "{$deadline}"
}
PROMPT;
    }

    /**
     * Apply smart defaults to extracted data
     */
    protected function applySmartDefaults(array $data, ?string $country = null): array
    {
        $countrySettings = $country ? ($this->countrySettings[$country] ?? null) : null;
        $twoWeeksAhead = Carbon::now()->addWeeks(2)->format('Y-m-d');

        // Ensure all fields exist with proper structure
        $defaults = [
            'job_title' => $data['job_title'] ?? '',
            'company_name' => $data['company_name'] ?? '',
            'job_description' => $data['job_description'] ?? '',
            'responsibilities' => $data['responsibilities'] ?? '',
            'qualifications' => $data['qualifications'] ?? '',
            'skills' => $data['skills'] ?? '',
            'application_procedure' => $data['application_procedure'] ?? '',
            'email' => $data['email'] ?? null,
            'telephone' => $data['telephone'] ?? null,
            'deadline' => $data['deadline'] ?? $twoWeeksAhead,
            'duty_station' => $data['duty_station'] ?? null,
            'location_type' => $data['location_type'] ?? 'on-site',
            'employment_type' => $data['employment_type'] ?? 'full-time',
            'salary_amount' => $data['salary_amount'] ?? null,
            'payment_period' => $data['payment_period'] ?? null,
            'currency' => $data['currency'] ?? ($countrySettings['currency'] ?? 'AUD'),
            'meta_description' => $data['meta_description'] ?? '',
            'keywords' => $data['keywords'] ?? '',
            'experience_level_name' => $data['experience_level_name'] ?? 'entry level',
            'education_level_name' => $data['education_level_name'] ?? 'Certificate',
            'industry_name' => $data['industry_name'] ?? null,
            'category_name' => $data['category_name'] ?? null,
            'country_code' => $data['country_code'] ?? $country,
            'is_urgent' => $data['is_urgent'] ?? false,
            'is_featured' => $data['is_featured'] ?? false,
            'is_resume_required' => $data['is_resume_required'] ?? true,
            'is_cover_letter_required' => $data['is_cover_letter_required'] ?? false,
            'is_academic_documents_required' => $data['is_academic_documents_required'] ?? false,
            'is_application_required' => $data['is_application_required'] ?? false,
            'is_whatsapp_contact' => $data['is_whatsapp_contact'] ?? false,
            'is_telephone_call' => $data['is_telephone_call'] ?? false,
            'work_hours' => $data['work_hours'] ?? null,
        ];

        // Only generate fallbacks if fields are completely empty
        if (empty($defaults['job_description']) && !empty($defaults['job_title'])) {
            $defaults['job_description'] = $this->generateFallbackDescription(
                $defaults['job_title'], 
                $defaults['company_name'] ?? null, 
                $defaults['duty_station'] ?? null
            );
        }
        
        if (empty($defaults['responsibilities']) && !empty($defaults['job_title'])) {
            $defaults['responsibilities'] = $this->generateFallbackResponsibilities(
                $defaults['job_title'], 
                $defaults['company_name'] ?? null
            );
        }
        
        if (empty($defaults['qualifications']) && !empty($defaults['job_title'])) {
            $defaults['qualifications'] = $this->generateFallbackQualifications($defaults['job_title']);
        }
        
        if (empty($defaults['skills']) && !empty($defaults['job_title'])) {
            $defaults['skills'] = $this->generateFallbackSkills($defaults['job_title']);
        }
        
        if (empty($defaults['meta_description']) && !empty($defaults['job_title'])) {
            $defaults['meta_description'] = $this->generateFallbackMetaDescription(
                $defaults['job_title'], 
                $defaults['company_name'] ?? null, 
                $defaults['duty_station'] ?? null
            );
        }
        
        if (empty($defaults['keywords']) && !empty($defaults['job_title'])) {
            $defaults['keywords'] = $this->generateFallbackKeywords($defaults['job_title']);
        }

        return $defaults;
    }

    /**
     * Wrap content in Arial font style
     */
    protected function wrapInArialFont(string $content): string
    {
        // If content already has font-family, add Arial
        if (strpos($content, 'font-family') !== false) {
            $content = preg_replace('/font-family\s*:\s*[^;]+;/i', 'font-family: Arial, sans-serif;', $content);
        } else {
            // Add Arial font style to the content
            $content = '<div style="font-family: Arial, sans-serif; font-size: 14px; line-height: 1.6;">' . $content . '</div>';
        }
        return $content;
    }

    /**
     * Extract text from API response with better error handling
     */
    protected function extractTextFromApiResponse(array $data, string $model): string
    {
        $text = '';
        
        switch ($model) {
            case 'openai':
                $text = $data['choices'][0]['message']['content'] ?? '';
                break;
            case 'claude':
                $text = $data['content'][0]['text'] ?? '';
                break;
            case 'gemini':
                $text = $data['candidates'][0]['content']['parts'][0]['text'] ?? '';
                break;
            case 'cohere':
                $text = $data['message']['content'][0]['text'] ?? '';
                break;
            case 'grok':
            case 'mistral':
                $text = $data['choices'][0]['message']['content'] ?? '';
                break;
            default:
                $text = json_encode($data);
        }

        return $text;
    }

}

