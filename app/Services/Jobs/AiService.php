<?php

namespace App\Services\Jobs;

use Illuminate\Support\Facades\{ Http, Log, Cache };
use Illuminate\Support\Str;
use Carbon\Carbon;
use App\Models\Job\{Industry, JobCategory, JobLocation, JobType, ExperienceLevel, EducationLevel, SalaryRange};

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
     * Pull the real option lists so the AI selects an EXACT existing name
     * instead of inventing text that will never match a dropdown.
     * Industry is global (not country-scoped); everything else is
     * scoped to the given country where the model supports it.
     */
    protected function getReferenceData(?string $country): array
    {
        $country = $country ? strtoupper($country) : null;

        return [
            'industries' => Industry::where('is_active', true)
                ->orderBy('name')->pluck('name')->all(),

            'categories' => JobCategory::where('is_active', true)
                ->when($country, fn($q) => $q->where('country_code', $country))
                ->orderBy('name')->pluck('name')->all(),

            'locations' => JobLocation::when($country, fn($q) => $q->where('country_code', $country))
                ->orderBy('district')->pluck('district')->unique()->values()->all(),

            'job_types' => JobType::where('is_active', true)
                ->orderBy('name')->pluck('name')->all(),

            'experience_levels' => ExperienceLevel::where('is_active', true)
                ->orderBy('sort_order')->pluck('name')->all(),

            'education_levels' => EducationLevel::where('is_active', true)
                ->when($country, fn($q) => $q->where(fn($q2) => $q2->where('country_code', $country)->orWhereNull('country_code')))
                ->orderBy('sort_order')->pluck('name')->all(),

            'salary_ranges' => SalaryRange::when($country, fn($q) => $q->where('country_code', $country))
                ->orderBy('min_salary')->pluck('name')->all(),
        ];
    }

    protected function formatReferenceListsForPrompt(array $ref): string
    {
        $line = fn(string $label, array $items) => empty($items)
            ? "{$label}: (none available)"
            : "{$label}: " . implode(' | ', $items);

        return <<<TXT
        AVAILABLE OPTIONS - you MUST choose an exact value from these lists for the corresponding *_name
        fields below (copy the text exactly, including capitalization). If nothing in a list is a reasonable
        match, return null for that field instead of inventing a new value.

        {$line('Industries', $ref['industries'])}
        {$line('Job Categories', $ref['categories'])}
        {$line('Locations (district)', $ref['locations'])}
        {$line('Job Types', $ref['job_types'])}
        {$line('Experience Levels', $ref['experience_levels'])}
        {$line('Education Levels', $ref['education_levels'])}
        {$line('Salary Ranges', $ref['salary_ranges'])}

        For "company_name": this is NOT selected from a list - just write the company's name exactly as
        found in the content (it will be added as a new record afterward).
        TXT;
    }

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

    public function isModelEnabled(string $model): bool
    {
        $config = $this->config[$model] ?? null;
        return $config && !empty($config['api_key']);
    }

    public function getModelConfig(string $model): ?array
    {
        return $this->config[$model] ?? null;
    }

    /**
     * Extract job data from text or URL.
     *
     * For URL sources this now actually fetches the page (previously the raw
     * URL string itself was sent to the model as "content", which no plain
     * chat-completion endpoint can browse - it was just guessing/hallucinating).
     */
    public function extractJobData(string $content, string $sourceType = 'text', ?string $model = null, ?string $country = null): array
    {
        $model = $model ?? $this->defaultModel;

        if ($sourceType === 'url') {
            $content = $this->fetchUrlContent($content);
        }

        $referenceData = $this->getReferenceData($country);
        $prompt = $this->buildExtractionPrompt($content, $sourceType, $referenceData);

        $result = $this->callWithFallback($model, $prompt, function ($m, $key) use ($prompt) {
            return $this->callAiApi($m, $key, $prompt);
        }, expectJson: true);

        if (!empty($result['error'])) {
            throw new \Exception($result['message'] ?? 'No valid job posting could be found in the provided content.');
        }

        $result = $this->applySmartDefaults($result, $country);
        $result = $this->applyApplicationLinkHandling($result);
        return $this->applyArialFontToContentFields($result);
    }

    /**
     * Fetch and clean a URL's page content so the AI actually has real
     * page text to extract from, instead of just the URL string.
     */
    protected function fetchUrlContent(string $url): string
    {
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            throw new \Exception('The provided URL is not valid.');
        }

        try {
            $response = Http::withHeaders([
                'User-Agent' => 'Mozilla/5.0 (compatible; JobBoardBot/1.0)',
            ])->timeout(20)->get($url);
        } catch (\Exception $e) {
            throw new \Exception('This job posting does not exist - the URL could not be reached: ' . $e->getMessage());
        }

        if (!$response->successful()) {
            throw new \Exception("This job posting does not exist - the page returned HTTP {$response->status()}.");
        }

        $html = $response->body();
        $text = preg_replace('#<script\b[^>]*>.*?</script>#is', ' ', $html);
        $text = preg_replace('#<style\b[^>]*>.*?</style>#is', ' ', $text);
        $text = strip_tags($text);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5);
        $text = preg_replace('/\s+/', ' ', $text);
        $text = trim($text);

        if (mb_strlen($text) < 200) {
            throw new \Exception('This job posting does not exist - no readable job content was found at that URL.');
        }

        return mb_substr($text, 0, 18000);
    }

    public function extractFromImage(string $imageBase64, ?string $model = null, ?string $country = null): array
    {
        $model = $model ?? $this->defaultModel;

        if (!$this->isModelEnabled($model)) {
            throw new \Exception("Model '{$model}' is not enabled or configured.");
        }

        $config = $this->getModelConfig($model);
        if (!in_array('image', $config['supports'] ?? [])) {
            throw new \Exception("Model '{$model}' does not support image processing.");
        }

        $referenceData = $this->getReferenceData($country);
        $prompt = $this->buildImageExtractionPrompt($referenceData);

        $result = $this->callWithFallback($model, $prompt, function ($m, $key) use ($prompt, $imageBase64) {
            return $this->callAiApi($m, $key, $prompt, $imageBase64);
        }, expectJson: true);

        if (!empty($result['error'])) {
            throw new \Exception($result['message'] ?? 'No job details could be read from the image.');
        }

        $result = $this->applySmartDefaults($result, $country);
        $result = $this->applyApplicationLinkHandling($result);
        return $this->applyArialFontToContentFields($result);
    }

    /**
     * Turns a raw application_link into a "To apply, click here" hyperlink inside
     * application_procedure, and makes sure no raw link/email/phone leaked into
     * the wrong fields (belt-and-braces on top of the prompt instructions).
     */
    protected function applyApplicationLinkHandling(array $data): array
    {
        $link = $data['application_link'] ?? null;

        if (!empty($link) && filter_var($link, FILTER_VALIDATE_URL)) {
            $safeLink = htmlspecialchars($link, ENT_QUOTES, 'UTF-8');
            $data['application_procedure'] = '<p>To apply, <a href="' . $safeLink . '" target="_blank" rel="noopener noreferrer">click here</a>.</p>';
            $data['is_application_required'] = true;
        }

        // Belt-and-braces: strip any raw URLs that slipped into body content fields.
        foreach (['job_description', 'responsibilities', 'qualifications'] as $field) {
            if (!empty($data[$field]) && is_string($data[$field])) {
                $data[$field] = preg_replace('#https?://\S+#i', '', $data[$field]);
            }
        }

        unset($data['application_link']); // not a real form field - already folded into application_procedure
        return $data;
    }

    /**
     * Enhance a field using AI. This is plain HTML text, NOT JSON - it must
     * NOT be routed through the JSON parser (that was the reason every
     * "AI Enhance" call previously returned the literal string "[]").
     */
    public function enhanceField(string $fieldName, string $content, string $instruction, ?string $model = null): string
    {
        $model = $model ?? $this->defaultModel;
        $prompt = $this->buildEnhancePrompt($fieldName, $content, $instruction);

        $text = $this->callWithFallback($model, $prompt, function ($m, $key) use ($prompt) {
            return $this->callAiApi($m, $key, $prompt);
        }, expectJson: false);

        return is_string($text) ? trim($text) : $this->extractTextFromResult($text);
    }

    public function generateFromTitle(string $title, ?string $company = null, ?string $country = null, ?string $model = null): array
    {
        $model = $model ?? $this->defaultModel;
        $referenceData = $this->getReferenceData($country);
        $prompt = $this->buildGeneratePrompt($title, $company, $country, $referenceData);

        $result = $this->callWithFallback($model, $prompt, function ($m, $key) use ($prompt) {
            return $this->callAiApi($m, $key, $prompt);
        }, expectJson: true);

        $result = $this->applySmartDefaults($result, $country);
        $result = $this->applyApplicationLinkHandling($result);
        return $this->applyArialFontToContentFields($result);
    }

    /**
     * Call AI API with fallback support.
     *
     * When $expectJson is true, a malformed/empty response from one model is
     * treated as that model's failure and the NEXT model in the fallback
     * chain is tried automatically - previously a parse failure just quietly
     * returned an empty array disguised as a success.
     */
    protected function callWithFallback(string $primaryModel, string $prompt, callable $callFn, bool $expectJson = true): array|string
    {
        $errors = [];
        $models = array_unique(array_merge([$primaryModel], $this->fallbackModels));

        foreach ($models as $model) {
            if (!$this->isModelEnabled($model)) {
                $errors[$model] = 'not enabled (missing API key)';
                continue;
            }

            try {
                $config = $this->getModelConfig($model);
                $raw = $callFn($model, $config['api_key']);

                if (!$expectJson) {
                    return is_array($raw) ? $this->extractTextFromResult($raw) : (string) $raw;
                }

                return $this->parseJsonOrFail($raw, $model);
            } catch (\Exception $e) {
                // Store the error message with the model name as key
                $errorMessage = $e->getMessage();
                
                // Clean up the error message - remove "API error (model): " prefix if present
                $cleanError = preg_replace('/^API error \([^)]+\):\s*/', '', $errorMessage);
                $errors[$model] = $cleanError ?: $errorMessage;
                
                Log::warning("AI call failed for model '{$model}'", ['error' => $e->getMessage()]);
            }
        }

        // Return errors as a structured array
        throw new \Exception(json_encode([
            'type' => 'model_errors',
            'errors' => $errors
        ]));
    }

    /**
     * Strictly parse a model's output into a non-empty array, or throw.
     * Throwing here (rather than returning []) is what lets callWithFallback
     * fall through to the next model instead of silently "succeeding" with
     * nothing to show on the form.
     */
    protected function parseJsonOrFail(mixed $result, string $model): array
    {
        if (is_array($result) && !empty($result)) {
            return $result;
        }

        if (is_string($result)) {
            $clean = preg_replace('/^```(?:json)?\s*/i', '', $result);
            $clean = preg_replace('/\s*```\s*$/i', '', $clean);
            $clean = trim($clean);

            $decoded = json_decode($clean, true);
            if (is_array($decoded) && !empty($decoded)) {
                return $decoded;
            }

            // Throw with the raw response
            throw new \Exception("Response could not be parsed as JSON. Raw response: " . substr($result, 0, 500));
        }

        throw new \Exception('Empty or invalid response.');
    }

    protected function callAiApi(string $model, string $apiKey, string $prompt, ?string $imageBase64 = null): array|string
    {
        $config = $this->getModelConfig($model);
        if (!$config) {
            throw new \Exception("Model '{$model}' not configured.");
        }

        $endpoint = $config['endpoint'];
        $modelName = $config['model'];
        $maxTokens = $config['max_tokens'] ?? 4096;

        // ✅ FIX: Set headers based on model
        $headers = ['Content-Type' => 'application/json'];
        
        if ($model === 'openai' || $model === 'grok' || $model === 'mistral' || $model === 'cohere') {
            $headers['Authorization'] = "Bearer {$apiKey}";
        } elseif ($model === 'claude') {
            $headers['x-api-key'] = $apiKey;
            $headers['anthropic-version'] = '2023-06-01';
        }
        // Gemini uses URL param, not header

        $body = $this->buildRequestBody($model, $apiKey, $modelName, $maxTokens, $prompt, $imageBase64);

        if ($model === 'gemini') {
            $endpoint = "https://generativelanguage.googleapis.com/v1beta/models/{$modelName}:generateContent?key={$apiKey}";
        }

        $response = Http::timeout($this->timeout)
            ->retry($this->retryAttempts, $this->retryDelay, function ($exception, $request) {
                return $exception instanceof \Illuminate\Http\Client\ConnectionException;
            })
            ->withHeaders($headers)
            ->post($endpoint, $body);

        if (!$response->successful()) {
            throw $this->buildApiException($model, $response);
        }

        return $this->extractResponseText($model, $response->json());
    }

    /**
     * Build the request body for each AI model
     */
    private function buildRequestBody(string $model, string $apiKey, string $modelName, int $maxTokens, string $prompt, ?string $imageBase64): array
    {
        switch ($model) {
            case 'openai':
                return [
                    'model' => $modelName,
                    'max_tokens' => $maxTokens,
                    'messages' => [
                        [
                            'role' => 'user',
                            'content' => $imageBase64
                                ? [
                                    ['type' => 'image_url', 'image_url' => ['url' => "data:image/jpeg;base64,{$imageBase64}"]],
                                    ['type' => 'text', 'text' => $prompt],
                                ]
                                : $prompt
                        ]
                    ],
                ];

            case 'claude':
                $content = $imageBase64
                    ? [
                        ['type' => 'image', 'source' => ['type' => 'base64', 'media_type' => 'image/jpeg', 'data' => $imageBase64]],
                        ['type' => 'text', 'text' => $prompt],
                    ]
                    : $prompt;

                return [
                    'model' => $modelName,
                    'max_tokens' => $maxTokens,
                    'messages' => [['role' => 'user', 'content' => $content]],
                ];

            case 'gemini':
                $parts = $imageBase64
                    ? [
                        ['inline_data' => ['mime_type' => 'image/jpeg', 'data' => $imageBase64]],
                        ['text' => $prompt],
                    ]
                    : [['text' => $prompt]];

                return [
                    'contents' => [['parts' => $parts]],
                    'generationConfig' => ['maxOutputTokens' => $maxTokens],
                ];

            case 'grok':
            case 'cohere':
            case 'mistral':
                return [
                    'model' => $modelName,
                    'max_tokens' => $maxTokens,
                    'messages' => [['role' => 'user', 'content' => $prompt]],
                ];

            default:
                throw new \Exception("Unsupported model: {$model}");
        }
    }

    /**
     * Build a clean API exception with the error message from the response
     */
    private function buildApiException(string $model, $response): \Exception
    {
        $errorBody = $response->body();
        $errorData = json_decode($errorBody, true);

        // Extract the actual error message from various response formats
        $errorMessage = $this->extractErrorMessage($errorData, $errorBody);

        return new \Exception("API error ({$model}): " . $errorMessage);
    }

    /**
     * Extract error message from API response data
     */
    private function extractErrorMessage(array $errorData, string $fallback): string
    {
        // Common error message paths in different API responses
        $paths = [
            'error.message',
            'error.error.message',
            'error.error',
            'message',
            'error'
        ];

        foreach ($paths as $path) {
            $value = $this->getNestedValue($errorData, $path);
            if ($value && is_string($value)) {
                return $value;
            }
        }

        // If no message found in the JSON, try to clean the raw body
        $cleaned = preg_replace('/\{"error":\{"message":"([^"]+)"[^}]*\}/', '$1', $fallback);
        if ($cleaned !== $fallback) {
            return $cleaned;
        }

        // Fallback: clean up the raw response
        return preg_replace('/\s+/', ' ', trim($fallback));
    }

    /**
     * Get nested array value using dot notation
     */
    private function getNestedValue(array $data, string $path)
    {
        $keys = explode('.', $path);
        $current = $data;

        foreach ($keys as $key) {
            if (!isset($current[$key])) {
                return null;
            }
            $current = $current[$key];
        }

        return $current;
    }

    /**
     * Extract the text content from the API response
     */
    private function extractResponseText(string $model, array $data): string
    {
        $text = match ($model) {
            'openai', 'grok', 'mistral' => $data['choices'][0]['message']['content'] ?? '',
            'claude' => $data['content'][0]['text'] ?? '',
            'gemini' => $data['candidates'][0]['content']['parts'][0]['text'] ?? '',
            'cohere' => $data['message']['content'][0]['text'] ?? 
                        $data['text'] ?? 
                        $data['response'] ?? 
                        $data['generation'] ?? 
                        '',
            default => json_encode($data),
        };

        // Log empty responses for debugging
        if (empty($text)) {
            Log::warning("Empty response from AI model '{$model}'", ['full_response' => json_encode($data)]);
        }

        return $text;
    }

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
            return '';
        }

        return (string) $result;
    }

    /**
     * Wrap the rich-text content fields in an Arial font style, applied here
     * in PHP (so it's guaranteed to produce valid, correctly-escaped HTML)
     * rather than asking the AI to embed style="..." attributes itself -
     * that was producing unescaped quotes inside the JSON string values,
     * which is exactly what was breaking json_decode() and causing
     * extraction results to silently come back empty.
     */
    protected function applyArialFontToContentFields(array $data): array
    {
        foreach (['job_description', 'responsibilities', 'qualifications', 'application_procedure'] as $field) {
            if (!empty($data[$field]) && is_string($data[$field])) {
                $data[$field] = $this->wrapInArialFont($data[$field]);
            }
        }
        return $data;
    }

    protected function wrapInArialFont(string $content): string
    {
        if (str_contains($content, 'rich-editor-arial-wrap')) {
            return $content; // already wrapped
        }
        return '<div class="rich-editor-arial-wrap" style="font-family: Arial, Helvetica, sans-serif; font-size: 14px; line-height: 1.6;">' . $content . '</div>';
    }

    protected function buildExtractionPrompt(string $content, string $sourceType, array $referenceData = []): string
    {
        $twoWeeksAhead = Carbon::now()->addWeeks(2)->format('Y-m-d');
        $referenceBlock = $this->formatReferenceListsForPrompt($referenceData);

        $sourceInstruction = $sourceType === 'url'
            ? 'The CONTENT below was fetched directly from the job posting page. Extract the job details from it.'
            : 'The CONTENT below is pasted job description text.';

        return <<<PROMPT
            You are an expert job-board data extraction agent.

            SOURCE TYPE: {$sourceType}
            {$sourceInstruction}

            {$referenceBlock}

            ABSOLUTE RULE - PRESERVE VERBATIM:
            If job_description, responsibilities, qualifications, skills, or application_procedure are present in
            the content, copy them into the JSON EXACTLY as given - same wording, same order, same structure,
            including any tables (as HTML <table> markup). Do NOT summarize, shorten, paraphrase, or "clean up"
            anything that is actually present. Only WRITE new content for a field when that field is completely
            absent from the source.

            CONTACT INFO MUST NOT APPEAR IN BODY CONTENT:
            Emails, phone numbers, WhatsApp numbers, and application links/URLs must NEVER appear inside
            job_description, responsibilities, qualifications, or skills - even if they appear that way in the
            source. Strip them out of those fields entirely and instead put them ONLY in the dedicated fields
            below (email, telephone, application_link).

            APPLICATION LINK HANDLING:
            - If an application URL/link is present anywhere in the content, put the raw URL in "application_link"
            and leave application_procedure to describe the general process in plain text (no raw link inside it -
            the raw link will be turned into a "click here" hyperlink automatically afterward).
            - If no link is present but there is a described procedure (email a CV, visit an office, etc.), put that
            in application_procedure as normal and leave application_link null.

            PHONE / CONTACT HANDLING:
            - If a phone number is present, put it in "telephone".
            - Set is_whatsapp_contact = true if the number is explicitly described as WhatsApp.
            - Set is_telephone_call = true if the number is described as callable, or if no qualifier is given at all
            (default to true in that case).
            - Both can be true if the content indicates the number works for both.

            JSON FORMATTING RULE (important - broken JSON here means the whole extraction fails):
            - Return ONLY a single valid JSON object. No markdown fences, no commentary before or after.
            - Do NOT add any HTML style="..." attributes or any other attributes containing double quotes.
            Use plain HTML tags only (<p>, <ul>, <li>, <strong>, <table>, <tr>, <td>, etc.) with no attributes,
            so there is no risk of an unescaped quote breaking the JSON.
            - If you must quote something inside a string value, escape it as \\" - never leave a bare " inside
            a JSON string.

            IF THE CONTENT DOES NOT DESCRIBE A REAL JOB POSTING (e.g. an error page, login wall, empty page, or
            unrelated content), respond with ONLY this JSON and nothing else:
            {"error": true, "message": "No valid job posting was found in the provided content."}

            SMART DEFAULTS (apply ONLY when a field is completely missing from the source):
            - employment_type: "full-time"
            - deadline: "{$twoWeeksAhead}"
            - experience_level_name: "entry level" (only if that exact string exists in the Experience Levels list above, else null)
            - education_level_name: "Certificate" (only if that exact string exists in the Education Levels list above, else null)
            - location_type: "on-site"
            - For job_description/responsibilities/qualifications/skills that are completely absent: write
            professional, role-appropriate content based on the job_title (and company_name if known).

            FIELDS TO RETURN:
            {
            "job_title": "exact job title",
            "company_name": "exact company name as written in the content, else null - do NOT pick from a list",
            "job_description": "verbatim if present, else generated - plain HTML, no attributes, no contact info",
            "responsibilities": "verbatim if present, else generated - plain HTML, no attributes, no contact info",
            "qualifications": "verbatim if present, else generated - plain HTML, no attributes, no contact info",
            "skills": "verbatim if present, else generated - comma-separated list",
            "application_procedure": "verbatim if present, else generated - plain HTML, no attributes, no raw link",
            "application_link": "raw application URL if one is present anywhere in the content, else null",
            "email": "contact email if mentioned, else null",
            "telephone": "phone number if mentioned, else null",
            "deadline": "YYYY-MM-DD, from content if present else the default above",
            "duty_station": "office/work location address if mentioned, else null",
            "location_type": "remote|hybrid|on-site",
            "employment_type": "full-time|part-time|contract|internship|volunteer|temporary",
            "salary_amount": "numeric amount if mentioned, else null",
            "payment_period": "monthly|yearly|weekly|daily|hourly, else null",
            "currency": "currency code if mentioned, else null",
            "meta_description": "155-character SEO description generated from the job title/content",
            "keywords": "comma-separated SEO keywords",
            "experience_level_name": "must be an EXACT value from the Experience Levels list above, else null",
            "education_level_name": "must be an EXACT value from the Education Levels list above, else null",
            "industry_name": "must be an EXACT value from the Industries list above, else null",
            "category_name": "must be an EXACT value from the Job Categories list above, else null",
            "job_type_name": "must be an EXACT value from the Job Types list above, else null",
            "job_location_name": "must be an EXACT value from the Locations list above (match by district), else null",
            "salary_range_name": "must be an EXACT value from the Salary Ranges list above, else null",
            "country_code": "one of AU, UG, KE, TZ, RW, MW, ZM, SG if the job's country matches one of these, else null",
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

            CONTENT:
            ---
            {$content}
            ---
            PROMPT;
    }

    protected function buildImageExtractionPrompt(array $referenceData = []): string
    {
        $referenceBlock = $this->formatReferenceListsForPrompt($referenceData);

        return "Extract all job information visible in this image. Preserve the EXACT text as shown - "
            . "do not summarize or reword anything that is actually visible. Only generate content for "
            . "fields that are completely absent from the image.\n\n{$referenceBlock}\n\n"
            . "Do NOT include emails, phone numbers, WhatsApp numbers, or application links inside "
            . "job_description, responsibilities, qualifications, or skills - put them only in the "
            . "dedicated email/telephone/application_link fields. If an application URL is visible, put it "
            . "in application_link (do not embed the raw link in application_procedure). "
            . "Return ONLY a single valid JSON object with the same fields as a standard job extraction "
            . "(job_title, company_name, job_description, responsibilities, qualifications, skills, "
            . "application_procedure, application_link, email, telephone, deadline, duty_station, "
            . "location_type, employment_type, salary_amount, payment_period, currency, meta_description, "
            . "keywords, experience_level_name, education_level_name, industry_name, category_name, "
            . "job_type_name, job_location_name, salary_range_name, country_code, work_hours, and the is_* "
            . "boolean flags). For company_name, write it exactly as shown - it is NOT chosen from a list. "
            . "For every other *_name field, it MUST be an exact value from the lists above, else null. "
            . "Do NOT include any HTML attributes (no style=\"...\", no class=\"...\") - use plain tags "
            . "only, so nothing can break the JSON. If the image does not show a real job posting, return "
            . "{\"error\": true, \"message\": \"No job posting could be read from this image.\"}";
    }

    protected function buildGeneratePrompt(string $title, ?string $company, ?string $country, array $referenceData = []): string
    {
        $companyText = $company ? " at {$company}" : '';
        $countryText = $country ? " in {$country}" : ' in East Africa';
        $deadline = Carbon::now()->addWeeks(2)->format('Y-m-d');
        $referenceBlock = $this->formatReferenceListsForPrompt($referenceData);

        return <<<PROMPT
        Generate a complete, professional job posting for a "{$title}"{$companyText}{$countryText}.

        {$referenceBlock}

        Return ONLY a valid JSON object - no explanation, no markdown, no code blocks. Do not use any HTML
        attributes (no style="...") on any tag - plain <p>/<ul>/<li> only, so nothing can break the JSON. Do not
        include any contact info or application links anywhere - this is a fresh generated posting.

        {
        "job_description": "3-4 paragraphs as HTML with <p> tags",
        "responsibilities": "6-8 items as HTML <ul><li> list",
        "qualifications": "required and preferred qualifications as HTML <ul><li> list with two sections",
        "skills": "comma-separated list of 8-12 relevant skills",
        "meta_description": "155-character SEO meta description",
        "keywords": "comma-separated SEO keywords",
        "experience_level_name": "must be an EXACT value from the Experience Levels list above, else null",
        "education_level_name": "must be an EXACT value from the Education Levels list above, else null",
        "industry_name": "must be an EXACT value from the Industries list above, else null",
        "category_name": "must be an EXACT value from the Job Categories list above, else null",
        "job_type_name": "must be an EXACT value from the Job Types list above, else null",
        "employment_type": "full-time|part-time|contract|internship|volunteer|temporary",
        "location_type": "on-site|remote|hybrid",
        "deadline": "{$deadline}"
        }
        PROMPT;
    }

    protected function buildEnhancePrompt(string $fieldName, string $content, string $instruction): string
    {
        return <<<PROMPT
        You are an expert HR copywriter. Your task: {$instruction}

        RULES:
        - Preserve the original meaning and any facts present; improve clarity and professionalism only.
        - Return ONLY the improved content as clean HTML using <p> and <ul><li> - no attributes on any tag.
        - Do NOT include explanations, markdown fences, or code blocks.
        - Do NOT wrap the response in JSON - return plain HTML text only.

        CURRENT CONTENT:
        {$content}
        PROMPT;
    }

    

    /**
     * Apply smart defaults - only fills fields that are genuinely missing.
     */
    protected function applySmartDefaults(array $data, ?string $country = null): array
    {
        $countrySettings = $country ? ($this->countrySettings[$country] ?? null) : null;
        $referenceData = $this->getReferenceData($country);

        foreach ([
            'industry_name'        => 'industries',
            'category_name'        => 'categories',
            'job_type_name'        => 'job_types',
            'job_location_name'    => 'locations',
            'experience_level_name'=> 'experience_levels',
            'education_level_name' => 'education_levels',
            'salary_range_name'    => 'salary_ranges',
        ] as $field => $refKey) {
            if (!empty($data[$field]) && !in_array($data[$field], $referenceData[$refKey], true)) {
                Log::info("AI returned unmatched {$field}", ['value' => $data[$field]]);
                $data[$field] = null; // don't let a hallucinated name reach the dropdown-matcher
            }
        }

        $twoWeeksAhead = Carbon::now()->addWeeks(2)->format('Y-m-d');

        $data['job_title'] = $data['job_title'] ?? '';
        $data['company_name'] = $data['company_name'] ?? '';
        $data['deadline'] = $data['deadline'] ?: $twoWeeksAhead;
        $data['location_type'] = $data['location_type'] ?: 'on-site';
        $data['employment_type'] = $data['employment_type'] ?: 'full-time';
        $data['experience_level_name'] = $data['experience_level_name'] ?: 'entry level';
        $data['education_level_name'] = $data['education_level_name'] ?: 'Certificate';
        $data['currency'] = $data['currency'] ?: ($countrySettings['currency'] ?? 'AUD');
        $data['country_code'] = $data['country_code'] ?? $country;
        $data['is_resume_required'] = $data['is_resume_required'] ?? true;

        foreach ([
            'email', 'telephone', 'duty_station', 'salary_amount', 'payment_period',
            'industry_name', 'category_name', 'work_hours',
        ] as $optional) {
            $data[$optional] = $data[$optional] ?? null;
        }

        foreach ([
            'is_urgent', 'is_featured', 'is_cover_letter_required', 'is_academic_documents_required',
            'is_application_required', 'is_whatsapp_contact', 'is_telephone_call',
        ] as $flag) {
            $data[$flag] = $data[$flag] ?? false;
        }

        // Only generate fallback content when the field is genuinely empty AND we have a title to work from.
        if (empty(trim(strip_tags($data['job_description'] ?? ''))) && !empty($data['job_title'])) {
            $data['job_description'] = $this->generateFallbackDescription($data['job_title'], $data['company_name'] ?: null, $data['duty_station']);
        }
        if (empty(trim(strip_tags($data['responsibilities'] ?? ''))) && !empty($data['job_title'])) {
            $data['responsibilities'] = $this->generateFallbackResponsibilities($data['job_title'], $data['company_name'] ?: null);
        }
        if (empty(trim(strip_tags($data['qualifications'] ?? ''))) && !empty($data['job_title'])) {
            $data['qualifications'] = $this->generateFallbackQualifications($data['job_title']);
        }
        if (empty(trim($data['skills'] ?? '')) && !empty($data['job_title'])) {
            $data['skills'] = $this->generateFallbackSkills($data['job_title']);
        }
        if (empty(trim($data['meta_description'] ?? '')) && !empty($data['job_title'])) {
            $data['meta_description'] = $this->generateFallbackMetaDescription($data['job_title'], $data['company_name'] ?: null, $data['duty_station']);
        }
        if (empty(trim($data['keywords'] ?? '')) && !empty($data['job_title'])) {
            $data['keywords'] = $this->generateFallbackKeywords($data['job_title']);
        }
        $data['application_procedure'] = $data['application_procedure'] ?? '';

        return $data;
    }

    protected function generateFallbackDescription(string $title, ?string $company, ?string $location): string
    {
        $companyText = $company ? " at {$company}" : '';
        $locationText = $location ? " based in {$location}" : '';
        return "<p>We are recruiting a <strong>{$title}</strong>{$companyText}{$locationText}.</p>
            <p>This is an exciting opportunity for a qualified professional to join our team and make a significant impact.</p>
            <p>If you have the right qualifications and experience, we encourage you to apply for this position.</p>";
                }

                protected function generateFallbackResponsibilities(string $title, ?string $company): string
                {
                    $companyText = $company ? " at {$company}" : '';
                    return "<ul>
            <li>Perform all duties related to the <strong>{$title}</strong> role{$companyText}.</li>
            <li>Collaborate with team members to achieve organizational goals.</li>
            <li>Ensure timely delivery of assigned tasks and projects.</li>
            <li>Maintain high standards of quality and professionalism.</li>
            <li>Communicate effectively with stakeholders and team members.</li>
            <li>Contribute to the continuous improvement of processes.</li>
            </ul>";
    }

    protected function generateFallbackQualifications(string $title): string
    {
        return "<p><strong>Required Qualifications</strong></p>
        <ul>
        <li>Relevant qualification or experience for the {$title} role.</li>
        <li>Strong communication and interpersonal skills.</li>
        <li>Ability to work independently and as part of a team.</li>
        </ul>
        <p><strong>Preferred Qualifications</strong></p>
        <ul>
        <li>Experience in a similar role.</li>
        <li>Knowledge of industry best practices.</li>
        </ul>";
    }

    protected function generateFallbackSkills(string $title): string
    {
        return "Communication, Teamwork, Problem Solving, Time Management, Report Writing, Attention to Detail, Leadership, Adaptability";
    }

    protected function generateFallbackMetaDescription(string $title, ?string $company, ?string $location): string
    {
        $companyText = $company ? " at {$company}" : '';
        $locationText = $location ? " in {$location}" : ' in East Africa';
        $desc = "Apply for the {$title} position{$companyText}{$locationText}. Join our team and advance your career.";
        return mb_substr($desc, 0, 155);
    }

    protected function generateFallbackKeywords(string $title): string
    {
        return "{$title}, jobs, career, employment, {$title} jobs, {$title} career, hiring";
    }

    public function getCountrySettings(string $countryCode): array
    {
        return $this->countrySettings[strtoupper($countryCode)] ?? [
            'currency' => 'AUD',
            'locale' => 'en_US',
            'timezone' => 'UTC',
        ];
    }
}