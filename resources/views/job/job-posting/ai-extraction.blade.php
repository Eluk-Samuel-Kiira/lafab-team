<script>

    // ================================================================
    // EXTRACT JOB DATA - FIXED
    // ================================================================
    async function extractJobData() {
        const model = document.getElementById('selectedModel').value;
        const sourceType = document.querySelector('input[name="sourceType"]:checked')?.value || 'text';
        let content = '';

        if (sourceType === 'text') {
            content = document.getElementById('aiSourceText')?.value?.trim();
            if (!content) {
                toast('Please paste some job content first.', 'warning');
                return;
            }
        } else {
            const url = document.getElementById('aiSourceUrl')?.value?.trim();
            if (!url) {
                toast('Please enter a job URL.', 'warning');
                return;
            }
            content = url;
        }

        const btn = document.getElementById('extractBtn');
        const spinner = document.getElementById('extractBtnSpinner');
        const btnText = document.getElementById('extractBtnText');
        const preview = document.getElementById('aiPreviewPanel');
        const status = document.getElementById('extractStatus');

        // Disable button and show spinner
        if (btn) btn.disabled = true;
        if (spinner) spinner.classList.remove('d-none');
        if (btnText) btnText.innerHTML = '<i class="ki-duotone ki-loader fs-3 me-1"></i>Extracting...';
        if (status) {
            status.textContent = 'Processing...';
            status.className = 'badge badge-warning';
        }

        if (preview) {
            preview.innerHTML = `
                <div class="text-center py-5">
                    <div class="spinner-border text-primary mb-3" role="status"></div>
                    <p class="text-muted">AI agent extracting job data...</p>
                </div>`;
        }

        try {
            const result = await apiFetch(`${AI_API_BASE}/extract-job`, {
                method: 'POST',
                body: JSON.stringify({
                    model,
                    content,
                    source_type: sourceType,
                    country: document.getElementById('f_country_code')?.value || null,
                }),

            });

            // Check for error response
            if (result.success === false) {
                throw new Error(result.error || result.message || 'Extraction failed');
            }

            extractedData = result.data;
            // console.log('Extracted data received:', extractedData);
            
            // Render preview
            renderExtractedPreview(result.data);

            const applyBtn = document.getElementById('applyExtractedBtn');
            if (applyBtn) applyBtn.style.display = 'inline-flex';

            const tokenInfo = document.getElementById('aiTokenInfo');
            if (tokenInfo) tokenInfo.textContent = `${model.toUpperCase()} — extraction complete`;

            if (status) {
                status.textContent = 'Complete';
                status.className = 'badge badge-success';
            }

            // AUTO-APPLY TO FORM
            if (document.getElementById('autoApplyToggle')?.checked) {
                // Delay slightly to ensure DOM is ready
                setTimeout(() => {
                    applyExtractedData();
                    const modal = bsModal('aiExtractModal');
                    if (modal) modal.hide();
                }, 500);
            } else {
                toast('Data extracted. Click "Apply to Form" to fill the form.', 'info');
            }

        } catch (e) {
            // Error handling...
            let msg = e.message || 'Extraction failed';
            if (e.error) msg = e.error;
            msg = msg.replace(/\[[^\]]+\]\s*/g, '').replace(/All AI models failed:\s*/g, '');
            msg = msg.replace(/API error \([^)]+\):\s*/g, '').trim();
            
            if (!msg || msg === '' || msg === 'Extraction failed') {
                msg = 'AI extraction failed. Please check your API keys and try again.';
            }
            
            if (msg.includes('API key') || msg.includes('api_key') || msg.includes('authentication') || msg.includes('auth')) {
                msg = 'API key error: Please check your AI model API keys in the configuration.';
            }
            
            if (status) {
                status.textContent = 'Failed';
                status.className = 'badge badge-danger';
            }
            
            if (preview) {
                preview.innerHTML = `
                    <div class="alert alert-danger m-2">
                        <i class="ki-duotone ki-danger fs-3 me-2"></i>
                        <strong>Extraction failed:</strong>
                        <div class="mt-1 small text-break">${escapeHtml(msg)}</div>
                        ${msg.includes('API key') ? `
                            <div class="mt-2 pt-2 border-top border-danger border-opacity-25">
                                <small class="text-muted">
                                    <i class="ki-duotone ki-information-5 fs-7 me-1"></i>
                                    Please ensure your AI model API keys are properly configured in the .env file.
                                </small>
                            </div>
                        ` : ''}
                    </div>
                `;
            }
            toast(msg, 'danger');
        } finally {
            // ALWAYS re-enable button and hide spinner
            if (btn) btn.disabled = false;
            if (spinner) spinner.classList.add('d-none');
            if (btnText) btnText.innerHTML = '<i class="ki-duotone ki-sparkle fs-3 me-1"></i>Extract Data';
        }
    }


    // Update extractFromMultipleImages to use image model selector
    async function extractFromMultipleImages() {
        if (uploadedImages.length === 0) {
            toast('Please upload at least one image first.', 'error');
            return;
        }

        // Get the selected image model
        const model = document.getElementById('imgSelectedModel').value || document.getElementById('selectedModel').value || 'gemini';
        const preview = document.getElementById('imgPreviewPanel');
        const btn = document.getElementById('multiImgExtractBtn');
        const spinner = document.getElementById('multiImgExtractBtnSpinner');
        const btnText = document.getElementById('multiImgExtractBtnText');
        const status = document.getElementById('imgExtractStatus');

        // Disable button and show spinner
        btn.disabled = true;
        spinner.classList.remove('d-none');
        btnText.innerHTML = 'Extracting...';
        if (status) {
            status.textContent = 'Processing...';
            status.className = 'badge badge-warning';
        }
        preview.innerHTML = `
            <div class="text-center py-5">
                <div class="spinner-border text-primary mb-3" role="status"></div>
                <p class="text-muted">Analyzing ${uploadedImages.length} image(s)...</p>
            </div>`;

        try {
            let combinedData = {};
            for (let i = 0; i < uploadedImages.length; i++) {
                const img = uploadedImages[i];
                preview.innerHTML = `
                    <div class="text-center py-5">
                        <div class="spinner-border text-primary mb-3" role="status"></div>
                        <p class="text-muted">Processing image ${i + 1} of ${uploadedImages.length}...</p>
                    </div>`;
                const result = await apiFetch(`/ai/extract-image`, {
                    method: 'POST',
                    body: JSON.stringify({ model, image_base64: img.base64 }),
                });
                img.extractedData = result.data;
                combinedData = { ...combinedData, ...result.data };
            }
            
            extractedData = combinedData;
            renderExtractedPreview(combinedData);
            
            // Copy to image preview panel
            const sourcePanel = document.getElementById('aiPreviewPanel');
            const targetPanel = document.getElementById('imgPreviewPanel');
            if (sourcePanel && targetPanel) {
                targetPanel.innerHTML = sourcePanel.innerHTML;
            }
            
            document.getElementById('applyImgBtn')?.classList.remove('d-none');
            document.getElementById('imgTokenInfo').textContent = `${model.toUpperCase()} — extracted from ${uploadedImages.length} image(s)`;
            
            if (status) {
                status.textContent = 'Complete';
                status.className = 'badge badge-success';
            }
            
            renderImageGallery();
            
            if (document.getElementById('imgAutoApplyToggle')?.checked) {
                applyImageData();
                bsModal('imageExtractModal').hide();
                toast('Job data extracted and applied to form!', 'success');
            } else {
                toast(`Extracted data from ${uploadedImages.length} image(s). Review then apply.`, 'success');
            }
        } catch (e) {
            // Get clean error message
            let msg = e.message || 'Extraction failed';
            if (e.error) msg = e.error;
            msg = msg.replace(/\[[^\]]+\]\s*/g, '').replace(/All AI models failed:\s*/g, '').trim();
            
            if (msg.includes('API key') || msg.includes('api_key') || msg.includes('authentication')) {
                msg = 'API key error: Please check your AI model API keys in the configuration.';
            }
            
            preview.innerHTML = `
                <div class="alert alert-danger m-2">
                    <i class="ki-duotone ki-danger fs-3 me-2"></i>
                    <strong>Extraction failed:</strong>
                    <div class="mt-1 small">${escapeHtml(msg)}</div>
                </div>`;
            if (status) {
                status.textContent = 'Failed';
                status.className = 'badge badge-danger';
            }
            toast(msg, 'danger');
        } finally {
            // ALWAYS re-enable button and hide spinner
            btn.disabled = false;
            spinner.classList.add('d-none');
            btnText.innerHTML = '<i class="ki-duotone ki-picture fs-3 me-1"></i>Extract from All Images';
        }
    }


    // ================================================================
    // APPLY EXTRACTED DATA - FIXED
    // ================================================================
    function applyExtractedData() {
        if (!extractedData) {
            toast('No extracted data. Please extract first.', 'warning');
            return;
        }
    
        const d = extractedData;
    
        // ---- Plain text fields ----
        const fieldMap = {
            job_title: 'f_job_title',
            duty_station: 'f_duty_station',
            email: 'f_email',
            telephone: 'f_telephone',
            salary_amount: 'f_salary_amount',
            currency: 'f_currency',
            meta_description: 'f_meta_description',
            keywords: 'f_keywords',
            work_hours: 'f_work_hours',
        };
        Object.entries(fieldMap).forEach(([key, id]) => {
            if (d[key] !== undefined && d[key] !== null && d[key] !== '') {
                const el = document.getElementById(id);
                if (el) el.value = d[key];
            }
        });
    
        // ---- Deadline ----
        if (d.deadline) {
            const el = document.getElementById('f_deadline');
            if (el) {
                el.value = /^\d{4}-\d{2}-\d{2}$/.test(d.deadline)
                    ? d.deadline
                    : (() => { const dt = new Date(d.deadline); return isNaN(dt) ? d.deadline : dt.toISOString().split('T')[0]; })();
            }
        }
    
        // ---- Native <select> dropdowns ----
        [['f_employment_type', d.employment_type], ['f_location_type', d.location_type], ['f_payment_period', d.payment_period]]
            .forEach(([id, value]) => {
                if (!value) return;
                const el = document.getElementById(id);
                if (!el) return;
                const match = Array.from(el.options).find(opt => opt.value.toLowerCase() === String(value).toLowerCase());
                if (match) el.value = match.value;
            });
    
        // ---- Rich text editors (Arial styling already applied server-side - don't re-wrap here) ----
        const richMap = {
            job_description: 'f_job_description_editor',
            responsibilities: 'f_responsibilities_editor',
            qualifications: 'f_qualifications_editor',
            skills: 'f_skills_editor',
            application_procedure: 'f_application_procedure_editor',
        };
        Object.entries(richMap).forEach(([key, editorId]) => {
            if (d[key] && typeof richEditorSet === 'function') {
                richEditorSet(editorId, d[key]);
            }
        });
    
        // ---- Country: f_country_code is a hidden input, not a <select> - no .options here.
        // Don't force-navigate to a different country (that would reload the page and lose
        // everything just filled in) - just tell the user if the AI thinks it's a different one.
        if (d.country_code) {
            const currentCountry = document.getElementById('f_country_code')?.value;
            if (currentCountry && d.country_code !== currentCountry) {
                toast(`Note: this content looks like it's for ${d.country_code}, but you're posting under ${currentCountry}. Switch country above if that's wrong.`, 'info');
            }
        }
    
        // ---- Searchable dropdowns (company, category, industry, location, job type, etc.) ----
        if (typeof autoSelectDropdowns === 'function') {
            autoSelectDropdowns(d);
        }
    
        // ---- Checkboxes ----
        const checkMap = {
            is_urgent: 'f_urgent', is_featured: 'f_featured', is_verified: 'f_verified',
            is_quick_gig: 'f_quickgig', is_resume_required: 'f_resume', is_cover_letter_required: 'f_cover',
            is_academic_documents_required: 'f_academic', is_application_required: 'f_appletter',
            is_whatsapp_contact: 'f_whatsapp', is_telephone_call: 'f_telcall',
        };
        Object.entries(checkMap).forEach(([key, id]) => {
            if (d[key] !== undefined) {
                const el = document.getElementById(id);
                if (el) el.checked = !!d[key];
            }
        });
    
        // ---- Draw attention to the top of the form ----
        const titleField = document.getElementById('f_job_title');
        if (titleField) {
            titleField.scrollIntoView({ behavior: 'smooth', block: 'center' });
            titleField.style.transition = 'background 0.5s';
            titleField.style.background = '#e8f5e9';
            setTimeout(() => { titleField.style.background = ''; }, 2000);
        }
    
        toast('✅ Data applied to form successfully!', 'success');
    }


    // ================================================================
    // AUTO-SELECT DROPDOWNS - FIXED
    // ================================================================
    function autoSelectDropdowns(d) {
        console.log('Auto-selecting dropdowns with data:', d);
        
        // Map data fields to dropdown prefixes
        const dropdownMap = {
            jobtype: { dataKey: 'employment_type', fallback: 'full-time' },
            experience: { dataKey: 'experience_level_name', fallback: 'entry level' },
            education: { dataKey: 'education_level_name', fallback: 'Certificate' },
            company: { dataKey: 'company_name', fallback: null },
            category: { dataKey: 'category_name', fallback: null },
            industry: { dataKey: 'industry_name', fallback: null },
            location: { dataKey: 'duty_station', fallback: null },
            salaryrange: { dataKey: 'salary_range_name', fallback: null },
        };

        Object.entries(dropdownMap).forEach(([prefix, config]) => {
            const drop = drops[prefix];
            if (!drop) return;
            
            const value = d[config.dataKey];
            if (value && value !== '') {
                // Try to find exact match
                const matched = drop.setByName(value, false);
                if (matched) {
                    console.log(`✅ Selected ${prefix}:`, value);
                } else {
                    // If no exact match, try to find partial match or use fallback
                    if (config.fallback) {
                        const fallbackMatched = drop.setByName(config.fallback, false);
                        if (fallbackMatched) {
                            console.log(`⚠️ Used fallback for ${prefix}:`, config.fallback);
                        } else {
                            drop.reset();
                            console.log(`❌ No match for ${prefix}:`, value);
                        }
                    } else {
                        drop.reset();
                        console.log(`❌ No match for ${prefix}:`, value);
                    }
                }
            } else if (config.fallback) {
                // Use fallback if value is empty
                const fallbackMatched = drop.setByName(config.fallback, false);
                if (fallbackMatched) {
                    console.log(`⚠️ Used default fallback for ${prefix}:`, config.fallback);
                }
            } else {
                drop.reset();
            }
        });
    }

    // ================================================================
    // RENDER EXTRACTED PREVIEW
    // ================================================================
    function renderExtractedPreview(data) {
        const panel = document.getElementById('aiPreviewPanel');
        if (!panel) {
            console.warn('aiPreviewPanel not found');
            return;
        }

        const fields = [
            { key: 'job_title', label: 'Job Title', icon: 'ki-briefcase' },
            { key: 'company_name', label: 'Company', icon: 'ki-building' },
            { key: 'employment_type', label: 'Employment Type', icon: 'ki-clock' },
            { key: 'location_type', label: 'Location Type', icon: 'ki-geolocation' },
            { key: 'duty_station', label: 'Duty Station', icon: 'ki-map' },
            { key: 'deadline', label: 'Deadline', icon: 'ki-calendar' },
            { key: 'salary_amount', label: 'Salary', icon: 'ki-coin' },
            { key: 'currency', label: 'Currency', icon: 'ki-dollar' },
            { key: 'payment_period', label: 'Pay Period', icon: 'ki-repeat' },
            { key: 'email', label: 'Email', icon: 'ki-message' },
            { key: 'telephone', label: 'Phone', icon: 'ki-call' },
            { key: 'experience_level_name', label: 'Experience Level', icon: 'ki-star' },
            { key: 'education_level_name', label: 'Education Level', icon: 'ki-school' },
            { key: 'industry_name', label: 'Industry', icon: 'ki-building-factory' },
            { key: 'category_name', label: 'Category', icon: 'ki-category' },
            { key: 'country_code', label: 'Country', icon: 'ki-flag' },
        ];

        const aiDefaulted = new Set(['employment_type','experience_level_name','education_level_name','deadline']);

        let html = `
            <div class="alert alert-success py-2 mb-2 d-flex align-items-center gap-2 small">
                <i class="ki-duotone ki-robot fs-3 flex-shrink-0"></i>
                <div>Content extracted. Fields marked <span class="badge badge-light-warning ms-1">default</span> were auto-generated.</div>
            </div>
            <div class="d-flex flex-column gap-2" style="font-family: Arial, sans-serif;">`;

        // Show fields
        fields.forEach(f => {
            const val = data[f.key];
            if (val === null || val === undefined || val === '') return;
            const badge = aiDefaulted.has(f.key)
                ? `<span class="badge badge-light-warning ms-1" style="font-size:10px">default</span>`
                : '';
                                            
            // For country code, show country name
            let displayVal = val;
            if (f.key === 'country_code' && val) {
                const countryMap = {
                    'AU': 'Australia 🇦🇺', 'UG': 'Uganda 🇺🇬', 'KE': 'Kenya 🇰🇪',
                    'TZ': 'Tanzania 🇹🇿', 'RW': 'Rwanda 🇷🇼', 'MW': 'Malawi 🇲🇼',
                    'ZM': 'Zambia 🇿🇲', 'SG': 'Singapore 🇸🇬'
                };
                displayVal = countryMap[val] || val;
            }
            
            html += `
                <div class="field-item d-flex gap-2 align-items-start" style="font-family: Arial, sans-serif;">
                    <i class="ki-duotone ${f.icon} text-primary flex-shrink-0 mt-1"></i>
                    <div class="flex-grow-1 min-w-0">
                        <div class="field-label">${escapeHtml(f.label)}${badge}</div>
                        <div class="field-value" style="font-family: Arial, sans-serif;">${escapeHtml(String(displayVal))}</div>
                    </div>
                </div>`;
        });

        // Show content fields with full HTML preservation
        const contentFields = [
            { key: 'job_description', label: 'Job Description', icon: 'ki-file-text' },
            { key: 'responsibilities', label: 'Responsibilities', icon: 'ki-check-circle' },
            { key: 'qualifications', label: 'Qualifications', icon: 'ki-shield-tick' },
            { key: 'skills', label: 'Skills', icon: 'ki-tools' },
            { key: 'application_procedure', label: 'Application Procedure', icon: 'ki-send' },
        ];

        contentFields.forEach(f => {
            const val = data[f.key];
            if (!val || val === '' || val === '<br>' || val === '<p><br></p>') return;
            
            html += `
                <div class="field-item d-flex gap-2 align-items-start" style="font-family: Arial, sans-serif;">
                    <i class="ki-duotone ${f.icon} text-primary flex-shrink-0 mt-1"></i>
                    <div class="flex-grow-1 min-w-0">
                        <div class="field-label">${escapeHtml(f.label)}</div>
                        <div class="field-value" style="font-family: Arial, sans-serif; max-height:150px;overflow-y:auto;background:#f8f9fa;padding:8px;border-radius:4px;font-size:13px;">
                            ${val}
                        </div>
                    </div>
                </div>`;
        });

        html += '</div>';
        panel.innerHTML = html;
    }

</script>