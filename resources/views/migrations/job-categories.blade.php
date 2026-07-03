@extends('layouts.admin')

@section('title', 'Job Categories Migration')
@section('page_title', 'Job Categories Migration')

@section('breadcrumb')
    <li class="breadcrumb-item text-muted">
        <a href="{{ route('admin.dashboard') }}" class="text-muted text-hover-primary">Home</a>
    </li>
    <li class="breadcrumb-item">
        <span class="bullet bg-gray-500 w-5px h-2px"></span>
    </li>
    <li class="breadcrumb-item text-muted">Migration</li>
    <li class="breadcrumb-item">
        <span class="bullet bg-gray-500 w-5px h-2px"></span>
    </li>
    <li class="breadcrumb-item text-muted">Job Categories</li>
@endsection

@section('content')
<div class="card card-flush">
    <div class="card-header mt-6">
        <div class="card-title">
            <div class="d-flex align-items-center gap-3 flex-wrap">
                <div class="d-flex align-items-center position-relative">
                    <i class="ki-duotone ki-magnifier fs-3 position-absolute ms-5">
                        <span class="path1"></span><span class="path2"></span>
                    </i>
                    <input type="text" id="searchInput" class="form-control form-control-solid w-250px ps-13" placeholder="Search Categories..." />
                </div>
                <div>
                    <select id="filterStatus" class="form-select form-select-solid w-150px">
                        <option value="">All Status</option>
                        <option value="migrated">Migrated</option>
                        <option value="pending">Pending</option>
                    </select>
                </div>
                <div>
                    <select id="filterCountry" class="form-select form-select-solid w-100px">
                        <option value="">All</option>
                        <option value="AU">AU</option>
                        <option value="KE">KE</option>
                        <option value="UG">UG</option>
                        <option value="RW">RW</option>
                        <option value="TZ">TZ</option>
                    </select>
                </div>
            </div>
        </div>
        <div class="card-toolbar d-flex gap-3">
            <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#kt_modal_import_categories">
                <i class="ki-duotone ki-file-up fs-2">
                    <span class="path1"></span><span class="path2"></span>
                </i> Upload SQL
            </button>
            <button type="button" class="btn btn-warning" onclick="bulkMigrate()">
                <i class="ki-duotone ki-check-square fs-2">
                    <span class="path1"></span><span class="path2"></span>
                </i> Bulk Migrate
            </button>
        </div>
    </div>
    
    <div class="card-body pt-0">
        <!-- Summary Cards -->
        <div class="row g-5 g-xl-10 mb-5">
            <div class="col-xxl-2 col-xl-4 col-lg-4 col-md-4">
                <div class="card card-flush shadow-sm">
                    <div class="card-body py-3 px-4">
                        <div class="d-flex align-items-center">
                            <div class="symbol symbol-35px symbol-circle bg-light-primary me-2">
                                <i class="ki-duotone ki-category fs-2 text-primary">
                                    <span class="path1"></span><span class="path2"></span>
                                </i>
                            </div>
                            <div class="flex-grow-1 min-w-0">
                                <span class="text-gray-600 fw-semibold d-block text-truncate fs-7">Total</span>
                                <span class="fw-bold text-gray-800" id="totalStats" style="font-size: clamp(0.8rem, 2vw, 1.5rem);">0</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xxl-2 col-xl-4 col-lg-4 col-md-4">
                <div class="card card-flush shadow-sm">
                    <div class="card-body py-3 px-4">
                        <div class="d-flex align-items-center">
                            <div class="symbol symbol-35px symbol-circle bg-light-success me-2">
                                <i class="ki-duotone ki-check-circle fs-2 text-success">
                                    <span class="path1"></span><span class="path2"></span>
                                </i>
                            </div>
                            <div class="flex-grow-1 min-w-0">
                                <span class="text-gray-600 fw-semibold d-block text-truncate fs-7">Migrated</span>
                                <span class="fw-bold text-gray-800" id="migratedStats" style="font-size: clamp(0.8rem, 2vw, 1.5rem);">0</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xxl-2 col-xl-4 col-lg-4 col-md-4">
                <div class="card card-flush shadow-sm">
                    <div class="card-body py-3 px-4">
                        <div class="d-flex align-items-center">
                            <div class="symbol symbol-35px symbol-circle bg-light-warning me-2">
                                <i class="ki-duotone ki-time fs-2 text-warning">
                                    <span class="path1"></span><span class="path2"></span>
                                </i>
                            </div>
                            <div class="flex-grow-1 min-w-0">
                                <span class="text-gray-600 fw-semibold d-block text-truncate fs-7">Pending</span>
                                <span class="fw-bold text-gray-800" id="pendingStats" style="font-size: clamp(0.8rem, 2vw, 1.5rem);">0</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xxl-2 col-xl-4 col-lg-4 col-md-4">
                <div class="card card-flush shadow-sm">
                    <div class="card-body py-3 px-4">
                        <div class="d-flex align-items-center">
                            <div class="symbol symbol-35px symbol-circle bg-light-info me-2">
                                <i class="ki-duotone ki-user fs-2 text-info">
                                    <span class="path1"></span><span class="path2"></span>
                                </i>
                            </div>
                            <div class="flex-grow-1 min-w-0">
                                <span class="text-gray-600 fw-semibold d-block text-truncate fs-7">Legacy</span>
                                <span class="fw-bold text-gray-800" id="legacyStats" style="font-size: clamp(0.8rem, 2vw, 1.5rem);">0</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xxl-2 col-xl-4 col-lg-4 col-md-4">
                <div class="card card-flush shadow-sm">
                    <div class="card-body py-3 px-4">
                        <div class="d-flex align-items-center">
                            <div class="symbol symbol-35px symbol-circle bg-light-success me-2">
                                <i class="ki-duotone ki-check fs-2 text-success">
                                    <span class="path1"></span><span class="path2"></span>
                                </i>
                            </div>
                            <div class="flex-grow-1 min-w-0">
                                <span class="text-gray-600 fw-semibold d-block text-truncate fs-7">Active</span>
                                <span class="fw-bold text-gray-800" id="activeStats" style="font-size: clamp(0.8rem, 2vw, 1.5rem);">0</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xxl-2 col-xl-4 col-lg-4 col-md-4">
                <div class="card card-flush shadow-sm">
                    <div class="card-body py-3 px-4">
                        <div class="d-flex align-items-center">
                            <div class="symbol symbol-35px symbol-circle bg-light-danger me-2">
                                <i class="ki-duotone ki-cross-circle fs-2 text-danger">
                                    <span class="path1"></span><span class="path2"></span>
                                </i>
                            </div>
                            <div class="flex-grow-1 min-w-0">
                                <span class="text-gray-600 fw-semibold d-block text-truncate fs-7">Inactive</span>
                                <span class="fw-bold text-gray-800" id="inactiveStats" style="font-size: clamp(0.8rem, 2vw, 1.5rem);">0</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Loading Spinner -->
        <div id="loadingSpinner" class="text-center py-10 d-none">
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">Loading...</span>
            </div>
            <p class="mt-3 text-muted">Loading categories...</p>
        </div>

        <!-- Table Container -->
        <div id="tableContainer" class="d-none">
            <div class="table-responsive">
                <table class="table align-middle table-row-dashed fs-6 gy-5 mb-0">
                    <thead>
                        <tr class="text-start text-gray-500 fw-bold fs-7 text-uppercase gs-0">
                            <th class="min-w-50px">ID</th>
                            <th class="min-w-50px">Legacy ID</th>
                            <th class="min-w-180px">Name</th>
                            <th class="min-w-120px">Slug</th>
                            <th class="min-w-60px">Country</th>
                            <th class="min-w-80px">Status</th>
                            <th class="min-w-100px">Migration</th>
                            <th class="min-w-120px">Migrated At</th>
                            <th class="text-end min-w-180px">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="categoriesTableBody"></tbody>
                </table>
            </div>

            <div id="paginationContainer" class="d-flex justify-content-between align-items-center mt-5 d-none">
                <div id="paginationInfo" class="text-muted"></div>
                <nav><ul class="pagination m-0" id="pagination"></ul></nav>
            </div>
        </div>

        <div id="noDataMessage" class="text-center py-10 d-none">
            <i class="ki-duotone ki-information-5 fs-2tx text-muted mb-3 d-block">
                <span class="path1"></span><span class="path2"></span><span class="path3"></span>
            </i>
            <p class="text-muted">No categories found.</p>
        </div>
    </div>
</div>

<!-- Import Categories Modal -->
<div class="modal fade" id="kt_modal_import_categories" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered mw-800px">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="fw-bold">Upload SQL File</h2>
                <div class="btn btn-icon btn-sm btn-active-icon-primary" data-bs-dismiss="modal">
                    <i class="ki-duotone ki-cross fs-1"><span class="path1"></span><span class="path2"></span></i>
                </div>
            </div>
            <div class="modal-body scroll-y mx-5 my-7">
                <form id="importForm" enctype="multipart/form-data">
                    @csrf
                    <div class="alert alert-info d-flex align-items-center mb-7">
                        <i class="ki-duotone ki-information-5 fs-2tx me-3"></i>
                        <div>
                            <strong>SQL File Upload</strong><br>
                            Upload a SQL dump file containing the legacy job categories table data.
                        </div>
                    </div>

                    <div class="fv-row mb-7">
                        <label class="required fw-semibold fs-6 mb-2">SQL File</label>
                        <input type="file" class="form-control form-control-solid" name="sql_file" accept=".sql,.txt" required />
                        <div class="form-text text-muted">Only .sql and .txt files are allowed. Max size: 10MB</div>
                    </div>

                    <div class="row mb-7">
                        <div class="col-md-6">
                            <label class="required fw-semibold fs-6 mb-2">Table Name</label>
                            <input type="text" class="form-control form-control-solid" name="table_name" placeholder="icop0_js_job_categories" required />
                        </div>
                        <div class="col-md-6">
                            <label class="required fw-semibold fs-6 mb-2">Country</label>
                            <select class="form-select form-select-solid" name="country_code" required>
                                <option value="AU">🇦🇺 Australia (AU)</option>
                                <option value="KE">🇰🇪 Kenya (KE)</option>
                                <option value="UG">🇺🇬 Uganda (UG)</option>
                                <option value="RW">🇷🇼 Rwanda (RW)</option>
                                <option value="TZ">🇹🇿 Tanzania (TZ)</option>
                            </select>
                        </div>
                    </div>

                    <div class="text-center pt-15">
                        <button type="button" class="btn btn-light me-3" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-success" id="importBtn">
                            <span class="indicator-label">Upload & Import</span>
                            <span class="indicator-progress">Importing... <span class="spinner-border spinner-border-sm align-middle ms-2"></span></span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- View/Edit Category Modal -->
<div class="modal fade" id="kt_modal_category" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered mw-700px">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="fw-bold" id="categoryModalTitle">Category Details</h2>
                <div class="btn btn-icon btn-sm btn-active-icon-primary" data-bs-dismiss="modal">
                    <i class="ki-duotone ki-cross fs-1"><span class="path1"></span><span class="path2"></span></i>
                </div>
            </div>
            <div class="modal-body p-7" id="categoryModalContent">
                <div class="text-center py-10">
                    <div class="spinner-border text-primary" role="status"></div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
let currentPage = 1;
let currentSearch = '';
let currentStatus = '';
let currentCountry = '';

// Load statistics
function loadStats() {
    fetch('{{ route("admin.job-categories.migration.stats") }}')
        .then(res => res.json())
        .then(data => {
            document.getElementById('totalStats').innerHTML = data.total || 0;
            document.getElementById('migratedStats').innerHTML = data.migrated || 0;
            document.getElementById('pendingStats').innerHTML = data.pending || 0;
            document.getElementById('legacyStats').innerHTML = data.legacy || 0;
            document.getElementById('activeStats').innerHTML = data.active || 0;
            document.getElementById('inactiveStats').innerHTML = data.inactive || 0;
        })
        .catch(err => console.error('Error loading stats:', err));
}

// Load categories
function loadCategories() {
    const spinner = document.getElementById('loadingSpinner');
    const table = document.getElementById('tableContainer');
    const noData = document.getElementById('noDataMessage');
    const pagination = document.getElementById('paginationContainer');

    if (spinner) spinner.classList.remove('d-none');
    if (table) table.classList.add('d-none');
    if (noData) noData.classList.add('d-none');
    if (pagination) pagination.classList.add('d-none');

    let url = `{{ route("admin.job-categories.migration.data") }}?page=${currentPage}&per_page=20`;
    if (currentSearch) url += `&search=${encodeURIComponent(currentSearch)}`;
    if (currentStatus) url += `&status=${currentStatus}`;
    if (currentCountry) url += `&country=${currentCountry}`;

    fetch(url)
        .then(res => res.json())
        .then(data => {
            if (spinner) spinner.classList.add('d-none');
            if (data.data.length === 0) {
                if (noData) noData.classList.remove('d-none');
            } else {
                if (table) table.classList.remove('d-none');
                renderCategoriesTable(data.data);
                renderPagination(data);
                if (pagination) pagination.classList.remove('d-none');
            }
        })
        .catch(err => {
            if (spinner) spinner.classList.add('d-none');
            window.showToast('error', 'Failed to load categories');
        });
}

function renderCategoriesTable(categories) {
    const tbody = document.getElementById('categoriesTableBody');
    if (!tbody) return;
    tbody.innerHTML = '';

    categories.forEach(cat => {
        const row = tbody.insertRow();
        row.insertCell(0).innerHTML = `<span class="fw-bold">${cat.id}</span>`;
        row.insertCell(1).innerHTML = cat.legacy_id ? `<span class="badge badge-light-primary">${cat.legacy_id}</span>` : '-';
        row.insertCell(2).innerHTML = `<div class="fw-bold">${escapeHtml(cat.name)}</div>`;
        row.insertCell(3).innerHTML = `<span class="text-muted fs-7">${escapeHtml(cat.slug)}</span>`;
        row.insertCell(4).innerHTML = `<span class="badge badge-light-info">${cat.country_code}</span>`;
        row.insertCell(5).innerHTML = getStatusBadge(cat.is_active);
        row.insertCell(6).innerHTML = getMigrationBadge(cat.migrated_at);
        row.insertCell(7).innerHTML = cat.migrated_at ? formatDate(cat.migrated_at) : '-';
        row.insertCell(8).innerHTML = getActionButtons(cat);
    });
}

function getStatusBadge(isActive) {
    if (isActive) {
        return '<span class="badge badge-light-success">Active</span>';
    }
    return '<span class="badge badge-light-danger">Inactive</span>';
}

function getMigrationBadge(migratedAt) {
    if (migratedAt) {
        return '<span class="badge badge-light-success">Migrated</span>';
    }
    return '<span class="badge badge-light-warning">Pending</span>';
}

function getActionButtons(cat) {
    let buttons = `
        <div class="d-flex justify-content-end gap-2">
            <button class="btn btn-sm btn-icon btn-light" onclick="viewCategory(${cat.id})" title="View">
                <i class="ki-duotone ki-eye fs-3"><span class="path1"></span><span class="path2"></span></i>
            </button>
    `;

    // Edit button - always show
    buttons += `
        <button class="btn btn-sm btn-icon btn-light" onclick="editCategory(${cat.id})" title="Edit">
            <i class="ki-duotone ki-setting-3 fs-3"><span class="path1"></span><span class="path2"></span><span class="path3"></span><span class="path4"></span><span class="path5"></span></i>
        </button>
    `;

    // Show Migrate button only if not migrated
    if (!cat.migrated_at) {
        buttons += `
            <button class="btn btn-sm btn-icon btn-light" onclick="migrateSingle(${cat.id})" title="Migrate">
                <i class="ki-duotone ki-check fs-3 text-success"><span class="path1"></span><span class="path2"></span></i>
            </button>
        `;
    } else {
        buttons += `
            <button class="btn btn-sm btn-icon btn-light" onclick="rollback(${cat.id})" title="Rollback">
                <i class="ki-duotone ki-arrow-left fs-3 text-warning">
                    <span class="path1"></span><span class="path2"></span>
                </i><span class="path1"></span><span class="path2"></span></i>
            </button>
        `;
    }

    // Delete button
    buttons += `
        <button class="btn btn-sm btn-icon btn-light" onclick="deleteCategory(${cat.id})" title="Delete">
            <i class="ki-duotone ki-trash fs-3 text-danger"><span class="path1"></span><span class="path2"></span><span class="path3"></span><span class="path4"></span><span class="path5"></span></i>
        </button>
    `;

    buttons += `</div>`;
    return buttons;
}

function renderPagination(data) {
    const el = document.getElementById('pagination');
    const info = document.getElementById('paginationInfo');
    if (!el) return;

    el.innerHTML = '';
    if (info) {
        info.innerHTML = `Showing ${data.from || 0} to ${data.to || 0} of ${data.total} entries`;
    }

    const addPage = (page, text, isActive = false, isDisabled = false) => {
        const li = document.createElement('li');
        li.className = `page-item ${isActive ? 'active' : ''} ${isDisabled ? 'disabled' : ''}`;
        const a = document.createElement('a');
        a.className = 'page-link';
        a.href = '#';
        a.textContent = text;
        if (!isDisabled) a.onclick = (e) => { e.preventDefault(); changePage(page); };
        li.appendChild(a);
        el.appendChild(li);
    };

    addPage(data.current_page - 1, 'Previous', false, !data.prev_page_url);
    let start = Math.max(1, data.current_page - 2);
    let end = Math.min(data.last_page, data.current_page + 2);
    if (start > 1) addPage(1, '1');
    if (start > 2) el.innerHTML += '<li class="page-item disabled"><span class="page-link">...</span></li>';
    for (let i = start; i <= end; i++) addPage(i, i, i === data.current_page);
    if (end < data.last_page - 1) el.innerHTML += '<li class="page-item disabled"><span class="page-link">...</span></li>';
    if (end < data.last_page) addPage(data.last_page, data.last_page);
    addPage(data.current_page + 1, 'Next', false, !data.next_page_url);
}

window.changePage = function(page) {
    if (page !== currentPage && page > 0) { currentPage = page; loadCategories(); }
};

// View Category
window.viewCategory = function(id) {
    const modal = new bootstrap.Modal(document.getElementById('kt_modal_category'));
    const content = document.getElementById('categoryModalContent');
    document.getElementById('categoryModalTitle').innerHTML = 'Category Details';
    content.innerHTML = '<div class="text-center py-10"><div class="spinner-border text-primary" role="status"></div></div>';
    modal.show();

    fetch(`/admin/job-categories/migration/${id}`)
        .then(res => res.json())
        .then(data => {
            let html = `
                <div class="row mb-5">
                    <div class="col-md-6">
                        <span class="text-muted">ID</span>
                        <div class="fw-bold fs-4">${data.id}</div>
                    </div>
                    <div class="col-md-6">
                        <span class="text-muted">Legacy ID</span>
                        <div class="fw-bold">${data.legacy_id || 'N/A'}</div>
                    </div>
                </div>
                <div class="separator my-5"></div>
                <div class="row mb-5">
                    <div class="col-md-12">
                        <span class="text-muted">Name</span>
                        <div class="fw-bold">${escapeHtml(data.name)}</div>
                    </div>
                </div>
                <div class="row mb-5">
                    <div class="col-md-6">
                        <span class="text-muted">Slug</span>
                        <div class="fw-bold">${escapeHtml(data.slug)}</div>
                    </div>
                    <div class="col-md-6">
                        <span class="text-muted">Country</span>
                        <div class="fw-bold">${data.country_code}</div>
                    </div>
                </div>
                <div class="row mb-5">
                    <div class="col-md-6">
                        <span class="text-muted">Status</span>
                        <div>${getStatusBadge(data.is_active)}</div>
                    </div>
                    <div class="col-md-6">
                        <span class="text-muted">Migration</span>
                        <div>${getMigrationBadge(data.migrated_at)}</div>
                    </div>
                </div>
                ${data.description ? `
                    <div class="row mb-5">
                        <div class="col-md-12">
                            <span class="text-muted">Description</span>
                            <div class="fw-bold">${escapeHtml(data.description)}</div>
                        </div>
                    </div>
                ` : ''}
                ${data.legacy_alias ? `
                    <div class="row mb-5">
                        <div class="col-md-12">
                            <span class="text-muted">Legacy Alias</span>
                            <div class="fw-bold">${escapeHtml(data.legacy_alias)}</div>
                        </div>
                    </div>
                ` : ''}
                <div class="row mb-5">
                    <div class="col-md-6">
                        <span class="text-muted">Sort Order</span>
                        <div class="fw-bold">${data.sort_order || 0}</div>
                    </div>
                    <div class="col-md-6">
                        <span class="text-muted">Is Default</span>
                        <div class="fw-bold">${data.is_default ? 'Yes' : 'No'}</div>
                    </div>
                </div>
                ${data.migrated_at ? `
                    <div class="row mb-5">
                        <div class="col-md-12">
                            <span class="text-muted">Migrated At</span>
                            <div class="fw-bold">${formatDate(data.migrated_at)}</div>
                        </div>
                    </div>
                ` : ''}
                <div class="row mb-5">
                    <div class="col-md-12">
                        <span class="text-muted">Created At</span>
                        <div class="fw-bold">${formatDate(data.created_at)}</div>
                    </div>
                </div>
            `;
            content.innerHTML = html;
        })
        .catch(err => {
            content.innerHTML = '<div class="text-center text-danger py-5">Failed to load category details</div>';
        });
};

// Edit Category
window.editCategory = function(id) {
    const modal = new bootstrap.Modal(document.getElementById('kt_modal_category'));
    const content = document.getElementById('categoryModalContent');
    document.getElementById('categoryModalTitle').innerHTML = 'Edit Category';
    content.innerHTML = '<div class="text-center py-10"><div class="spinner-border text-primary" role="status"></div></div>';
    modal.show();

    fetch(`/admin/job-categories/migration/${id}`)
        .then(res => res.json())
        .then(data => {
            let html = `
                <form id="editCategoryForm">
                    @csrf
                    @method('PUT')
                    <input type="hidden" name="id" value="${data.id}">
                    
                    <div class="row mb-5">
                        <div class="col-md-12">
                            <label class="required fw-semibold fs-6 mb-2">Name</label>
                            <input type="text" class="form-control form-control-solid" name="name" value="${escapeHtml(data.name)}" required />
                        </div>
                    </div>
                    
                    <div class="row mb-5">
                        <div class="col-md-6">
                            <label class="required fw-semibold fs-6 mb-2">Slug</label>
                            <input type="text" class="form-control form-control-solid" name="slug" value="${escapeHtml(data.slug)}" required />
                        </div>
                        <div class="col-md-6">
                            <label class="fw-semibold fs-6 mb-2">Country Code</label>
                            <select class="form-select form-select-solid" name="country_code">
                                <option value="AU" ${data.country_code === 'AU' ? 'selected' : ''}>AU - Australia</option>
                                <option value="KE" ${data.country_code === 'KE' ? 'selected' : ''}>KE - Kenya</option>
                                <option value="UG" ${data.country_code === 'UG' ? 'selected' : ''}>UG - Uganda</option>
                                <option value="RW" ${data.country_code === 'RW' ? 'selected' : ''}>RW - Rwanda</option>
                                <option value="TZ" ${data.country_code === 'TZ' ? 'selected' : ''}>TZ - Tanzania</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="row mb-5">
                        <div class="col-md-12">
                            <label class="fw-semibold fs-6 mb-2">Description</label>
                            <textarea class="form-control form-control-solid" name="description" rows="3">${escapeHtml(data.description || '')}</textarea>
                        </div>
                    </div>
                    
                    <div class="row mb-5">
                        <div class="col-md-6">
                            <label class="fw-semibold fs-6 mb-2">Sort Order</label>
                            <input type="number" class="form-control form-control-solid" name="sort_order" value="${data.sort_order || 0}" />
                        </div>
                        <div class="col-md-6">
                            <div class="form-check form-switch form-check-custom form-check-solid mt-8">
                                <input class="form-check-input" type="checkbox" name="is_active" ${data.is_active ? 'checked' : ''} />
                                <label class="form-check-label fw-semibold">Active</label>
                            </div>
                        </div>
                    </div>
                    
                    <div class="text-center pt-15">
                        <button type="button" class="btn btn-light me-3" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary" id="editCategoryBtn">
                            <span class="indicator-label">Update Category</span>
                            <span class="indicator-progress">Saving... <span class="spinner-border spinner-border-sm align-middle ms-2"></span></span>
                        </button>
                    </div>
                </form>
            `;
            content.innerHTML = html;

            // Handle form submission in editCategory function
            document.getElementById('editCategoryForm')?.addEventListener('submit', function(e) {
                e.preventDefault();
                const btn = document.getElementById('editCategoryBtn');
                window.showButtonSpinner(btn);

                const formData = new FormData(this);
                const categoryId = document.querySelector('input[name="id"]').value;

                // Fix checkbox - explicitly set boolean value
                const isActive = document.querySelector('input[name="is_active"]');
                if (isActive) {
                    formData.set('is_active', isActive.checked ? '1' : '0');
                } else {
                    formData.set('is_active', '0');
                }

                // Remove the id field from form data (it's not needed for update)
                formData.delete('id');
                formData.append('_method', 'PUT');

                fetch(`/admin/job-categories/migration/${categoryId}`, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Accept': 'application/json'
                    },
                    body: formData
                })
                .then(res => {
                    if (!res.ok) {
                        return res.json().then(data => {
                            throw data;
                        });
                    }
                    return res.json();
                })
                .then(data => {
                    if (data.success) {
                        window.showToast('success', data.message);
                        bootstrap.Modal.getInstance(document.getElementById('kt_modal_category'))?.hide();
                        loadCategories();
                        loadStats();
                    } else {
                        window.showToast('error', data.message || 'Failed to update category');
                    }
                })
                .catch(err => {
                    console.error('Error:', err);
                    let errorMessage = 'Failed to update category';
                    if (err.errors) {
                        errorMessage = Object.values(err.errors).flat().join('\n');
                    } else if (err.message) {
                        errorMessage = err.message;
                    }
                    window.showToast('error', errorMessage);
                })
                .finally(() => {
                    window.hideButtonSpinner(btn);
                });
            });

        })
        .catch(err => {
            content.innerHTML = '<div class="text-center text-danger py-5">Failed to load category details</div>';
        });
};

// Migrate single
window.migrateSingle = function(id) {
    if (confirm('Are you sure you want to mark this category as migrated?')) {
        fetch(`/admin/job-categories/migration/${id}/migrate`, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'Accept': 'application/json',
                'Content-Type': 'application/json'
            }
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                window.showToast('success', data.message);
                loadCategories();
                loadStats();
            } else {
                window.showToast('error', data.message);
            }
        })
        .catch(err => window.showToast('error', 'Failed to migrate category'));
    }
};

// Bulk migrate
window.bulkMigrate = function() {
    const ids = [];
    document.querySelectorAll('#categoriesTableBody input[type="checkbox"]:checked').forEach(cb => {
        ids.push(cb.value);
    });

    if (ids.length === 0) {
        window.showToast('warning', 'Please select at least one category to migrate.');
        return;
    }

    if (confirm(`Are you sure you want to migrate ${ids.length} category(s)?`)) {
        fetch('{{ route("admin.job-categories.migration.bulk") }}', {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'Accept': 'application/json',
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ ids: ids })
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                window.showToast('success', data.message);
                loadCategories();
                loadStats();
            } else {
                window.showToast('error', data.message);
            }
        })
        .catch(err => window.showToast('error', 'Failed to bulk migrate'));
    }
};

// Rollback
window.rollback = function(id) {
    if (confirm('Are you sure you want to rollback this migration?')) {
        fetch(`/admin/job-categories/migration/${id}/rollback`, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'Accept': 'application/json',
                'Content-Type': 'application/json'
            }
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                window.showToast('success', data.message);
                loadCategories();
                loadStats();
            } else {
                window.showToast('error', data.message);
            }
        })
        .catch(err => window.showToast('error', 'Failed to rollback'));
    }
};

// Delete category - FIXED
window.deleteCategory = function(id) {
    if (confirm('Are you sure you want to delete this category? This action cannot be undone.')) {
        fetch(`/admin/job-categories/migration/${id}`, {
            method: 'DELETE',
            headers: {
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'Accept': 'application/json',
                'Content-Type': 'application/json'
            }
        })
        .then(res => {
            if (!res.ok) {
                return res.json().then(data => {
                    throw data;
                });
            }
            return res.json();
        })
        .then(data => {
            if (data.success) {
                window.showToast('success', data.message);
                loadCategories();
                loadStats();
            } else {
                window.showToast('error', data.message || 'Failed to delete category');
            }
        })
        .catch(err => {
            console.error('Error:', err);
            let errorMessage = 'Failed to delete category';
            if (err.message) {
                errorMessage = err.message;
            }
            window.showToast('error', errorMessage);
        });
    }
};

// Import form submission
document.getElementById('importForm')?.addEventListener('submit', function(e) {
    e.preventDefault();
    e.stopPropagation();

    const btn = document.getElementById('importBtn');
    window.showButtonSpinner(btn);

    const formData = new FormData(this);

    fetch('{{ route("admin.job-categories.migration.import") }}', {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': '{{ csrf_token() }}',
            'Accept': 'application/json'
        },
        body: formData
    })
    .then(res => {
        if (!res.ok) {
            return res.json().then(data => { throw data; });
        }
        return res.json();
    })
    .then(data => {
        if (data.success) {
            window.showToast('success', data.message);
            bootstrap.Modal.getInstance(document.getElementById('kt_modal_import_categories'))?.hide();
            this.reset();
            loadCategories();
            loadStats();
        } else {
            window.showToast('error', data.message || 'Import failed');
        }
    })
    .catch(err => {
        console.error('Error:', err);
        let errorMessage = 'Import failed';
        if (err.errors) {
            errorMessage = Object.values(err.errors).flat().join('\n');
        } else if (err.message) {
            errorMessage = err.message;
        }
        window.showToast('error', errorMessage);
    })
    .finally(() => {
        window.hideButtonSpinner(btn);
    });

    return false;
});

// Utility functions
function formatDate(dateString) {
    if (!dateString) return '-';
    const date = new Date(dateString);
    return date.toLocaleString('en-US', {
        year: 'numeric',
        month: 'short',
        day: '2-digit',
        hour: '2-digit',
        minute: '2-digit'
    });
}

function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Event listeners
document.addEventListener('DOMContentLoaded', function() {
    loadStats();
    loadCategories();

    const searchInput = document.getElementById('searchInput');
    let timeout;
    if (searchInput) {
        searchInput.addEventListener('keyup', function() {
            clearTimeout(timeout);
            timeout = setTimeout(() => {
                currentSearch = this.value;
                currentPage = 1;
                loadCategories();
            }, 500);
        });
    }

    document.getElementById('filterStatus')?.addEventListener('change', function() {
        currentStatus = this.value;
        currentPage = 1;
        loadCategories();
    });

    document.getElementById('filterCountry')?.addEventListener('change', function() {
        currentCountry = this.value;
        currentPage = 1;
        loadCategories();
    });
});
</script>
@endpush