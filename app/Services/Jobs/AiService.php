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

        $prompt = $this->buildExtractionPrompt($content, $sourceType);

        $result = $this->callWithFallback($model, $prompt, function ($m, $key) use ($prompt) {
            return $this->callAiApi($m, $key, $prompt);
        }, expectJson: true);

        if (!empty($result['error'])) {
            throw new \Exception($result['message'] ?? 'No valid job posting could be found in the provided content.');
        }

        $result = $this->applySmartDefaults($result, $country);
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

        $prompt = $this->buildImageExtractionPrompt();

        $result = $this->callWithFallback($model, $prompt, function ($m, $key) use ($prompt, $imageBase64) {
            return $this->callAiApi($m, $key, $prompt, $imageBase64);
        }, expectJson: true);

        if (!empty($result['error'])) {
            throw new \Exception($result['message'] ?? 'No job details could be read from the image.');
        }

        $result = $this->applySmartDefaults($result, $country);
        return $this->applyArialFontToContentFields($result);
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
        $prompt = $this->buildGeneratePrompt($title, $company, $country);

        $result = $this->callWithFallback($model, $prompt, function ($m, $key) use ($prompt) {
            return $this->callAiApi($m, $key, $prompt);
        }, expectJson: true);

        $result = $this->applySmartDefaults($result, $country);
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
                $errors[] = "[{$model}] not enabled (missing API key).";
                continue;
            }

            try {
                $config = $this->getModelConfig($model);
                $raw = $callFn($model, $config['api_key']);

                if (!$expectJson) {
                    // Plain text expected (enhanceField) - no JSON coercion.
                    return is_array($raw) ? $this->extractTextFromResult($raw) : (string) $raw;
                }

                return $this->parseJsonOrFail($raw, $model);
            } catch (\Exception $e) {
                $errors[] = "[{$model}] " . $e->getMessage();
                Log::warning("AI call failed for model '{$model}'", ['error' => $e->getMessage()]);
            }
        }

        throw new \Exception('All AI models failed: ' . implode(' | ', $errors));
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

            Log::warning("AI model '{$model}' returned unparseable JSON", ['full_response' => $result]);
            throw new \Exception("returned a response that could not be parsed as JSON.");
        }

        throw new \Exception('returned an empty response.');
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

        $headers = ['Content-Type' => 'application/json'];
        $body = [];

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
                $endpoint = "https://generativelanguage.googleapis.com/v1beta/models/{$modelName}:generateContent?key={$apiKey}";
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
            case 'cohere':
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
            $errorBody = $response->body();
            $errorData = json_decode($errorBody, true);
            $errorMessage = $errorData['error']['message'] ?? $errorData['error'] ?? $errorBody;
            throw new \Exception("API error ({$model}): " . $errorMessage);
        }

        $data = $response->json();
        $text = match ($model) {
            'openai', 'grok', 'cohere', 'mistral' => $data['choices'][0]['message']['content'] ?? '',
            'claude' => $data['content'][0]['text'] ?? '',
            'gemini' => $data['candidates'][0]['content']['parts'][0]['text'] ?? '',
            default => json_encode($data),
        };

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

    /**
     * Build extraction prompt.
     *
     * Deliberately does NOT ask the model to add any inline style attributes -
     * that was the direct cause of broken JSON (see wrapInArialFont above).
     * Arial styling is applied server-side after parsing succeeds instead.
     */
    protected function buildExtractionPrompt(string $content, string $sourceType): string
    {
        $twoWeeksAhead = Carbon::now()->addWeeks(2)->format('Y-m-d');

        $sourceInstruction = $sourceType === 'url'
            ? 'The CONTENT below was fetched directly from the job posting page. Extract the job details from it.'
            : 'The CONTENT below is pasted job description text.';

        return <<<PROMPT
You are an expert job-board data extraction agent.

SOURCE TYPE: {$sourceType}
{$sourceInstruction}

ABSOLUTE RULE - PRESERVE VERBATIM:
If job_description, responsibilities, qualifications, skills, or application_procedure are present in
the content, copy them into the JSON EXACTLY as given - same wording, same order, same structure,
including any tables (as HTML <table> markup). Do NOT summarize, shorten, paraphrase, or "clean up"
anything that is actually present. Only WRITE new content for a field when that field is completely
absent from the source.

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
- experience_level_name: "entry level"
- education_level_name: "Certificate"
- location_type: "on-site"
- is_telephone_call: true (if telephone is present but WhatsApp is not mentioned)
- is_whatsapp_contact: false (unless WhatsApp is explicitly mentioned)
- For job_description/responsibilities/qualifications/skills that are completely absent: write
  professional, role-appropriate content based on the job_title (and company_name if known).

FIELDS TO RETURN:
{
  "job_title": "exact job title",
  "company_name": "exact company name, else null",
  "job_description": "verbatim if present, else generated - plain HTML, no attributes",
  "responsibilities": "verbatim if present, else generated - plain HTML, no attributes",
  "qualifications": "verbatim if present, else generated - plain HTML, no attributes",
  "skills": "verbatim if present, else generated - comma-separated list",
  "application_procedure": "verbatim if present, else generated - plain HTML, no attributes",
  "email": "contact email if mentioned, else null",
  "telephone": "phone number if mentioned, else null",
  "deadline": "YYYY-MM-DD, from content if present else the default above",
  "duty_station": "office/work location if mentioned, else null",
  "location_type": "remote|hybrid|on-site",
  "employment_type": "full-time|part-time|contract|internship|volunteer|temporary",
  "salary_amount": "numeric amount if mentioned, else null",
  "payment_period": "monthly|yearly|weekly|daily|hourly, else null",
  "currency": "currency code if mentioned, else null",
  "meta_description": "155-character SEO description generated from the job title/content",
  "keywords": "comma-separated SEO keywords",
  "experience_level_name": "entry level|junior|mid level|senior|executive",
  "education_level_name": "Certificate|Diploma|Bachelor's Degree|Master's Degree",
  "industry_name": "industry sector if identifiable, else null",
  "category_name": "job category if identifiable, else null",
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

    protected function buildImageExtractionPrompt(): string
    {
        return "Extract all job information visible in this image. Preserve the EXACT text as shown - "
            . "do not summarize or reword anything that is actually visible. Only generate content for "
            . "fields that are completely absent from the image. Return ONLY a single valid JSON object "
            . "with the same fields as a standard job extraction (job_title, company_name, job_description, "
            . "responsibilities, qualifications, skills, application_procedure, email, telephone, deadline, "
            . "duty_station, location_type, employment_type, salary_amount, payment_period, currency, "
            . "meta_description, keywords, experience_level_name, education_level_name, industry_name, "
            . "category_name, country_code, work_hours, and the is_* boolean flags). "
            . "Do NOT include any HTML attributes (no style=\"...\", no class=\"...\") - use plain tags "
            . "only, so nothing can break the JSON. If the image does not show a real job posting, return "
            . "{\"error\": true, \"message\": \"No job posting could be read from this image.\"}";
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

    protected function buildGeneratePrompt(string $title, ?string $company, ?string $country): string
    {
        $companyText = $company ? " at {$company}" : '';
        $countryText = $country ? " in {$country}" : ' in East Africa';
        $deadline = Carbon::now()->addWeeks(2)->format('Y-m-d');

        return <<<PROMPT
Generate a complete, professional job posting for a "{$title}"{$companyText}{$countryText}.

Return ONLY a valid JSON object - no explanation, no markdown, no code blocks. Do not use any HTML
attributes (no style="...") on any tag - plain <p>/<ul>/<li> only, so nothing can break the JSON.

{
  "job_description": "3-4 paragraphs as HTML with <p> tags",
  "responsibilities": "6-8 items as HTML <ul><li> list",
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
     * Apply smart defaults - only fills fields that are genuinely missing.
     */
    protected function applySmartDefaults(array $data, ?string $country = null): array
    {
        $countrySettings = $country ? ($this->countrySettings[$country] ?? null) : null;
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