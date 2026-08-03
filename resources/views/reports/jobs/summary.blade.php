@extends('layouts.admin')

@section('title', 'Jobs Summary Report')
@section('page_title', 'Jobs Summary Report')

@section('breadcrumb')
    <li class="breadcrumb-item text-muted">
        <a href="{{ route('admin.dashboard') }}" class="text-muted text-hover-primary">Home</a>
    </li>
    <li class="breadcrumb-item">
        <span class="bullet bg-gray-500 w-5px h-2px"></span>
    </li>
    <li class="breadcrumb-item text-muted">Reports</li>
    <li class="breadcrumb-item">
        <span class="bullet bg-gray-500 w-5px h-2px"></span>
    </li>
    <li class="breadcrumb-item text-muted">
        <a href="{{ route('admin.jobs-reports') }}" class="text-muted text-hover-primary">Jobs Reports</a>
    </li>
    <li class="breadcrumb-item">
        <span class="bullet bg-gray-500 w-5px h-2px"></span>
    </li>
    <li class="breadcrumb-item text-muted">Summary</li>
@endsection

@section('content')
@can('view job summary')
<!-- Filters -->
<div class="card card-flush shadow-sm mb-5">
    <div class="card-body py-4">
        <form method="GET" action="{{ route('admin.jobs-reports.summary') }}" class="row g-3 align-items-end">
            <div class="col-md-2">
                <label class="fw-semibold fs-7 mb-1">Start Date</label>
                <input type="date" name="start_date" class="form-control form-control-solid" value="{{ $startDate ?? '' }}">
            </div>
            <div class="col-md-2">
                <label class="fw-semibold fs-7 mb-1">End Date</label>
                <input type="date" name="end_date" class="form-control form-control-solid" value="{{ $endDate ?? '' }}">
            </div>
            <div class="col-md-2">
                <label class="fw-semibold fs-7 mb-1">Category</label>
                <select name="category_id" class="form-select form-select-solid" data-control="select2">
                    <option value="">All Categories</option>
                    @foreach($categories as $category)
                        <option value="{{ $category->id }}" {{ ($categoryId ?? '') == $category->id ? 'selected' : '' }}>
                            {{ $category->name }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label class="fw-semibold fs-7 mb-1">Company</label>
                <select name="company_id" class="form-select form-select-solid" data-control="select2">
                    <option value="">All Companies</option>
                    @foreach($companies as $company)
                        <option value="{{ $company->id }}" {{ ($companyId ?? '') == $company->id ? 'selected' : '' }}>
                            {{ $company->name }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label class="fw-semibold fs-7 mb-1">Status</label>
                <select name="status" class="form-select form-select-solid">
                    <option value="">All Status</option>
                    @foreach($statuses as $status)
                        <option value="{{ $status }}" {{ ($status ?? '') == $status ? 'selected' : '' }}>
                            {{ ucfirst($status) }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label class="fw-semibold fs-7 mb-1">Country</label>
                <select name="country_code" class="form-select form-select-solid" data-control="select2">
                    <option value="">All Countries</option>
                    @foreach($countries as $code => $name)
                        <option value="{{ $code }}" {{ ($countryCode ?? '') == $code ? 'selected' : '' }}>
                            {{ $name }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-primary w-100">
                    <i class="ki-duotone ki-filter fs-2 me-1"></i> Apply Filters
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Summary Cards -->
<div class="row g-5 g-xl-10 mb-5">
    <div class="col-xxl-3 col-xl-6 col-lg-6 col-md-6">
        <div class="card card-flush shadow-sm">
            <div class="card-body py-3 px-4">
                <div class="d-flex align-items-center">
                    <div class="symbol symbol-35px symbol-circle bg-light-primary me-2">
                        <i class="ki-duotone ki-briefcase fs-2 text-primary">
                            <span class="path1"></span><span class="path2"></span>
                        </i>
                    </div>
                    <div class="flex-grow-1 min-w-0">
                        <span class="text-gray-600 fw-semibold d-block text-truncate fs-7">Total Jobs</span>
                        <span class="fw-bold text-gray-800" style="font-size: clamp(0.8rem, 2vw, 1.5rem);">{{ $summary['total_jobs'] ?? 0 }}</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xxl-3 col-xl-6 col-lg-6 col-md-6">
        <div class="card card-flush shadow-sm">
            <div class="card-body py-3 px-4">
                <div class="d-flex align-items-center">
                    <div class="symbol symbol-35px symbol-circle bg-light-info me-2">
                        <i class="ki-duotone ki-eye fs-2 text-info">
                            <span class="path1"></span><span class="path2"></span>
                        </i>
                    </div>
                    <div class="flex-grow-1 min-w-0">
                        <span class="text-gray-600 fw-semibold d-block text-truncate fs-7">Total Views</span>
                        <span class="fw-bold text-gray-800" style="font-size: clamp(0.8rem, 2vw, 1.5rem);">{{ number_format($summary['total_views'] ?? 0) }}</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xxl-3 col-xl-6 col-lg-6 col-md-6">
        <div class="card card-flush shadow-sm">
            <div class="card-body py-3 px-4">
                <div class="d-flex align-items-center">
                    <div class="symbol symbol-35px symbol-circle bg-light-success me-2">
                        <i class="ki-duotone ki-profile-user fs-2 text-success">
                            <span class="path1"></span><span class="path2"></span>
                        </i>
                    </div>
                    <div class="flex-grow-1 min-w-0">
                        <span class="text-gray-600 fw-semibold d-block text-truncate fs-7">Total Applications</span>
                        <span class="fw-bold text-gray-800" style="font-size: clamp(0.8rem, 2vw, 1.5rem);">{{ number_format($summary['total_applications'] ?? 0) }}</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xxl-3 col-xl-6 col-lg-6 col-md-6">
        <div class="card card-flush shadow-sm">
            <div class="card-body py-3 px-4">
                <div class="d-flex align-items-center">
                    <div class="symbol symbol-35px symbol-circle bg-light-warning me-2">
                        <i class="ki-duotone ki-chart fs-2 text-warning">
                            <span class="path1"></span><span class="path2"></span>
                        </i>
                    </div>
                    <div class="flex-grow-1 min-w-0">
                        <span class="text-gray-600 fw-semibold d-block text-truncate fs-7">Avg Views/Job</span>
                        <span class="fw-bold text-gray-800" style="font-size: clamp(0.8rem, 2vw, 1.5rem);">{{ number_format($summary['average_views'] ?? 0, 1) }}</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Additional Summary Cards -->
<div class="row g-5 g-xl-10 mb-5">
    <div class="col-xxl-2 col-xl-4 col-lg-4 col-md-6">
        <div class="card card-flush shadow-sm">
            <div class="card-body py-3 px-4">
                <div class="d-flex align-items-center">
                    <div class="symbol symbol-30px symbol-circle bg-light-success me-2">
                        <i class="ki-duotone ki-check-circle fs-2 text-success">
                            <span class="path1"></span><span class="path2"></span>
                        </i>
                    </div>
                    <div class="flex-grow-1 min-w-0">
                        <span class="text-gray-600 fw-semibold d-block text-truncate fs-7">Active</span>
                        <span class="fw-bold text-gray-800">{{ $summary['active_jobs'] ?? 0 }}</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xxl-2 col-xl-4 col-lg-4 col-md-6">
        <div class="card card-flush shadow-sm">
            <div class="card-body py-3 px-4">
                <div class="d-flex align-items-center">
                    <div class="symbol symbol-30px symbol-circle bg-light-secondary me-2">
                        <i class="ki-duotone ki-alarm fs-2 text-secondary">
                            <span class="path1"></span><span class="path2"></span>
                        </i>
                    </div>
                    <div class="flex-grow-1 min-w-0">
                        <span class="text-gray-600 fw-semibold d-block text-truncate fs-7">Expired</span>
                        <span class="fw-bold text-gray-800">{{ $summary['expired_jobs'] ?? 0 }}</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xxl-2 col-xl-4 col-lg-4 col-md-6">
        <div class="card card-flush shadow-sm">
            <div class="card-body py-3 px-4">
                <div class="d-flex align-items-center">
                    <div class="symbol symbol-30px symbol-circle bg-light-primary me-2">
                        <i class="ki-duotone ki-star fs-2 text-primary">
                            <span class="path1"></span><span class="path2"></span>
                        </i>
                    </div>
                    <div class="flex-grow-1 min-w-0">
                        <span class="text-gray-600 fw-semibold d-block text-truncate fs-7">Featured</span>
                        <span class="fw-bold text-gray-800">{{ $summary['featured_jobs'] ?? 0 }}</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xxl-2 col-xl-4 col-lg-4 col-md-6">
        <div class="card card-flush shadow-sm">
            <div class="card-body py-3 px-4">
                <div class="d-flex align-items-center">
                    <div class="symbol symbol-30px symbol-circle bg-light-danger me-2">
                        <i class="ki-duotone ki-fire fs-2 text-danger">
                            <span class="path1"></span><span class="path2"></span>
                        </i>
                    </div>
                    <div class="flex-grow-1 min-w-0">
                        <span class="text-gray-600 fw-semibold d-block text-truncate fs-7">Urgent</span>
                        <span class="fw-bold text-gray-800">{{ $summary['urgent_jobs'] ?? 0 }}</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xxl-2 col-xl-4 col-lg-4 col-md-6">
        <div class="card card-flush shadow-sm">
            <div class="card-body py-3 px-4">
                <div class="d-flex align-items-center">
                    <div class="symbol symbol-30px symbol-circle bg-light-info me-2">
                        <i class="ki-duotone ki-send fs-2 text-info">
                            <span class="path1"></span><span class="path2"></span>
                        </i>
                    </div>
                    <div class="flex-grow-1 min-w-0">
                        <span class="text-gray-600 fw-semibold d-block text-truncate fs-7">Pinged</span>
                        <span class="fw-bold text-gray-800">{{ $summary['pinged_jobs'] ?? 0 }}</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xxl-2 col-xl-4 col-lg-4 col-md-6">
        <div class="card card-flush shadow-sm">
            <div class="card-body py-3 px-4">
                <div class="d-flex align-items-center">
                    <div class="symbol symbol-30px symbol-circle bg-light-success me-2">
                        <i class="ki-duotone ki-google fs-2 text-success">
                            <span class="path1"></span><span class="path2"></span>
                        </i>
                    </div>
                    <div class="flex-grow-1 min-w-0">
                        <span class="text-gray-600 fw-semibold d-block text-truncate fs-7">Indexed</span>
                        <span class="fw-bold text-gray-800">{{ $summary['indexed_jobs'] ?? 0 }}</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Charts and Tables -->
<div class="row g-5 g-xl-10 mb-5">
    <!-- Category Breakdown -->
    <div class="col-xxl-6 col-xl-6">
        <div class="card card-flush shadow-sm">
            <div class="card-header py-3">
                <h3 class="card-title fs-5 fw-bold">Jobs by Category</h3>
            </div>
            <div class="card-body">
                @if($categoryBreakdown->count() > 0)
                    <div class="table-responsive">
                        <table class="table align-middle table-row-dashed fs-6 gy-3">
                            <thead>
                                <tr class="text-start text-gray-500 fw-bold fs-7 text-uppercase gs-0">
                                    <th>Category</th>
                                    <th class="text-center">Jobs</th>
                                    <th class="text-end">Views</th>
                                    <th class="text-end">Applications</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($categoryBreakdown as $item)
                                    <tr>
                                        <td>{{ $item['category_name'] }}</td>
                                        <td class="text-center">
                                            <span class="badge badge-light-primary">{{ $item['count'] }}</span>
                                        </td>
                                        <td class="text-end">{{ number_format($item['views']) }}</td>
                                        <td class="text-end">{{ number_format($item['applications']) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                            <tfoot>
                                <tr class="fw-bold">
                                    <td>Total</td>
                                    <td class="text-center">{{ $categoryBreakdown->sum('count') }}</td>
                                    <td class="text-end">{{ number_format($categoryBreakdown->sum('views')) }}</td>
                                    <td class="text-end">{{ number_format($categoryBreakdown->sum('applications')) }}</td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                @else
                    <div class="text-center py-5 text-muted">No data available</div>
                @endif
            </div>
        </div>
    </div>

    <!-- Company Breakdown -->
    <div class="col-xxl-6 col-xl-6">
        <div class="card card-flush shadow-sm">
            <div class="card-header py-3">
                <h3 class="card-title fs-5 fw-bold">Jobs by Company</h3>
            </div>
            <div class="card-body">
                @if($companyBreakdown->count() > 0)
                    <div class="table-responsive">
                        <table class="table align-middle table-row-dashed fs-6 gy-3">
                            <thead>
                                <tr class="text-start text-gray-500 fw-bold fs-7 text-uppercase gs-0">
                                    <th>Company</th>
                                    <th class="text-center">Jobs</th>
                                    <th class="text-end">Views</th>
                                    <th class="text-end">Applications</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($companyBreakdown as $item)
                                    <tr>
                                        <td>{{ $item['company_name'] }}</td>
                                        <td class="text-center">
                                            <span class="badge badge-light-primary">{{ $item['count'] }}</span>
                                        </td>
                                        <td class="text-end">{{ number_format($item['views']) }}</td>
                                        <td class="text-end">{{ number_format($item['applications']) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                            <tfoot>
                                <tr class="fw-bold">
                                    <td>Total</td>
                                    <td class="text-center">{{ $companyBreakdown->sum('count') }}</td>
                                    <td class="text-end">{{ number_format($companyBreakdown->sum('views')) }}</td>
                                    <td class="text-end">{{ number_format($companyBreakdown->sum('applications')) }}</td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                @else
                    <div class="text-center py-5 text-muted">No data available</div>
                @endif
            </div>
        </div>
    </div>
</div>

<!-- Location & Source Breakdown -->
<div class="row g-5 g-xl-10 mb-5">
    <!-- Location Breakdown -->
    <div class="col-xxl-6 col-xl-6">
        <div class="card card-flush shadow-sm">
            <div class="card-header py-3">
                <h3 class="card-title fs-5 fw-bold">Jobs by Location</h3>
            </div>
            <div class="card-body">
                @if($locationBreakdown->count() > 0)
                    <div class="table-responsive">
                        <table class="table align-middle table-row-dashed fs-6 gy-3">
                            <thead>
                                <tr class="text-start text-gray-500 fw-bold fs-7 text-uppercase gs-0">
                                    <th>Location</th>
                                    <th class="text-center">Jobs</th>
                                    <th class="text-end">Views</th>
                                    <th class="text-end">Applications</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($locationBreakdown as $item)
                                    <tr>
                                        <td>{{ $item['location_name'] }}</td>
                                        <td class="text-center">
                                            <span class="badge badge-light-primary">{{ $item['count'] }}</span>
                                        </td>
                                        <td class="text-end">{{ number_format($item['views']) }}</td>
                                        <td class="text-end">{{ number_format($item['applications']) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                            <tfoot>
                                <tr class="fw-bold">
                                    <td>Total</td>
                                    <td class="text-center">{{ $locationBreakdown->sum('count') }}</td>
                                    <td class="text-end">{{ number_format($locationBreakdown->sum('views')) }}</td>
                                    <td class="text-end">{{ number_format($locationBreakdown->sum('applications')) }}</td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                @else
                    <div class="text-center py-5 text-muted">No data available</div>
                @endif
            </div>
        </div>
    </div>

    <!-- Source Breakdown -->
    <div class="col-xxl-6 col-xl-6">
        <div class="card card-flush shadow-sm">
            <div class="card-header py-3">
                <h3 class="card-title fs-5 fw-bold">Jobs by Source</h3>
            </div>
            <div class="card-body">
                @if($sourceBreakdown->count() > 0)
                    <div class="table-responsive">
                        <table class="table align-middle table-row-dashed fs-6 gy-3">
                            <thead>
                                <tr class="text-start text-gray-500 fw-bold fs-7 text-uppercase gs-0">
                                    <th>Source</th>
                                    <th class="text-center">Jobs</th>
                                    <th class="text-end">Views</th>
                                    <th class="text-end">Applications</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($sourceBreakdown as $item)
                                    <tr>
                                        <td>
                                            @php
                                                $sourceLabels = [
                                                    'competitor_website' => 'Competitor Website',
                                                    'whatsapp' => 'WhatsApp',
                                                    'newspaper' => 'Newspaper',
                                                    'employer_website' => 'Employer Website',
                                                    'linkedin' => 'LinkedIn',
                                                    'facebook' => 'Facebook',
                                                    'other' => 'Other'
                                                ];
                                            @endphp
                                            {{ $sourceLabels[$item['source']] ?? $item['source'] }}
                                        </td>
                                        <td class="text-center">
                                            <span class="badge badge-light-primary">{{ $item['count'] }}</span>
                                        </td>
                                        <td class="text-end">{{ number_format($item['views']) }}</td>
                                        <td class="text-end">{{ number_format($item['applications']) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                            <tfoot>
                                <tr class="fw-bold">
                                    <td>Total</td>
                                    <td class="text-center">{{ $sourceBreakdown->sum('count') }}</td>
                                    <td class="text-end">{{ number_format($sourceBreakdown->sum('views')) }}</td>
                                    <td class="text-end">{{ number_format($sourceBreakdown->sum('applications')) }}</td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                @else
                    <div class="text-center py-5 text-muted">No data available</div>
                @endif
            </div>
        </div>
    </div>
</div>

<!-- Daily Breakdown -->
<div class="row g-5 g-xl-10 mb-5">
    <div class="col-12">
        <div class="card card-flush shadow-sm">
            <div class="card-header py-3">
                <h3 class="card-title fs-5 fw-bold">Daily Breakdown</h3>
            </div>
            <div class="card-body">
                @if($dailyBreakdown->count() > 0)
                    <div class="table-responsive">
                        <table class="table align-middle table-row-dashed fs-6 gy-3">
                            <thead>
                                <tr class="text-start text-gray-500 fw-bold fs-7 text-uppercase gs-0">
                                    <th>Date</th>
                                    <th class="text-center">Jobs</th>
                                    <th class="text-end">Views</th>
                                    <th class="text-end">Applications</th>
                                    <th class="text-end">Clicks</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($dailyBreakdown as $item)
                                    <tr>
                                        <td>{{ $item['date_formatted'] }}</td>
                                        <td class="text-center">
                                            <span class="badge badge-light-primary">{{ $item['count'] }}</span>
                                        </td>
                                        <td class="text-end">{{ number_format($item['views']) }}</td>
                                        <td class="text-end">{{ number_format($item['applications']) }}</td>
                                        <td class="text-end">{{ number_format($item['clicks']) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                            <tfoot>
                                <tr class="fw-bold">
                                    <td>Total</td>
                                    <td class="text-center">{{ $dailyBreakdown->sum('count') }}</td>
                                    <td class="text-end">{{ number_format($dailyBreakdown->sum('views')) }}</td>
                                    <td class="text-end">{{ number_format($dailyBreakdown->sum('applications')) }}</td>
                                    <td class="text-end">{{ number_format($dailyBreakdown->sum('clicks')) }}</td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                @else
                    <div class="text-center py-5 text-muted">No daily data available</div>
                @endif
            </div>
        </div>
    </div>
</div>

<!-- Status Breakdown -->
<div class="row g-5 g-xl-10 mb-5">
    <div class="col-12">
        <div class="card card-flush shadow-sm">
            <div class="card-header py-3">
                <h3 class="card-title fs-5 fw-bold">Status Breakdown</h3>
            </div>
            <div class="card-body">
                @if($statusBreakdown->count() > 0)
                    <div class="table-responsive">
                        <table class="table align-middle table-row-dashed fs-6 gy-3">
                            <thead>
                                <tr class="text-start text-gray-500 fw-bold fs-7 text-uppercase gs-0">
                                    <th>Status</th>
                                    <th class="text-center">Jobs</th>
                                    <th class="text-end">Views</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($statusBreakdown as $item)
                                    @php
                                        $badgeColor = match($item['label']) {
                                            'Active' => 'success',
                                            'Inactive' => 'danger',
                                            'Expired' => 'secondary',
                                            'Featured' => 'primary',
                                            'Urgent' => 'warning',
                                            'Pinged' => 'info',
                                            'Indexed' => 'success',
                                            default => 'secondary'
                                        };
                                    @endphp
                                    <tr>
                                        <td>
                                            <span class="badge badge-light-{{ $badgeColor }}">
                                                {{ $item['label'] }}
                                            </span>
                                        </td>
                                        <td class="text-center fw-bold">{{ $item['count'] }}</td>
                                        <td class="text-end">{{ number_format($item['views']) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                            <tfoot>
                                <tr class="fw-bold">
                                    <td>Total</td>
                                    <td class="text-center">{{ $statusBreakdown->sum('count') }}</td>
                                    <td class="text-end">{{ number_format($statusBreakdown->sum('views')) }}</td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                @else
                    <div class="text-center py-5 text-muted">No data available</div>
                @endif
            </div>
        </div>
    </div>
</div>

<!-- Jobs List with Pagination -->
<div class="row g-5 g-xl-10">
    <div class="col-12">
        <div class="card card-flush shadow-sm">
            <div class="card-header py-3">
                <h3 class="card-title fs-5 fw-bold">Jobs List</h3>
                <div class="card-toolbar">
                    <div class="d-flex align-items-center gap-2">
                        <span class="text-muted fs-7">Per Page:</span>
                        <select class="form-select form-select-sm w-70px" onchange="window.location.href=this.value">
                            <option value="{{ request()->fullUrlWithQuery(['per_page' => 10, 'page' => 1]) }}" {{ ($perPage ?? 20) == 10 ? 'selected' : '' }}>10</option>
                            <option value="{{ request()->fullUrlWithQuery(['per_page' => 20, 'page' => 1]) }}" {{ ($perPage ?? 20) == 20 ? 'selected' : '' }}>20</option>
                            <option value="{{ request()->fullUrlWithQuery(['per_page' => 50, 'page' => 1]) }}" {{ ($perPage ?? 20) == 50 ? 'selected' : '' }}>50</option>
                            <option value="{{ request()->fullUrlWithQuery(['per_page' => 100, 'page' => 1]) }}" {{ ($perPage ?? 20) == 100 ? 'selected' : '' }}>100</option>
                        </select>
                    </div>
                </div>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table align-middle table-row-dashed fs-6 gy-3">
                        <thead>
                            <tr class="text-start text-gray-500 fw-bold fs-7 text-uppercase gs-0">
                                <th>Job Title</th>
                                <th>Company</th>
                                <th>Category</th>
                                <th class="text-center">Views</th>
                                <th class="text-center">Applications</th>
                                <th>Created</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($jobs as $job)
                                <tr>
                                    <td>
                                        <span class="fw-bold">{{ Str::limit($job->job_title, 40) }}</span>
                                    </td>
                                    <td>{{ $job->company?->name ?? 'N/A' }}</td>
                                    <td>{{ $job->jobCategory?->name ?? 'N/A' }}</td>
                                    <td class="text-center">
                                        <span class="badge badge-light-info">{{ number_format($job->view_count) }}</span>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge badge-light-success">{{ number_format($job->application_count) }}</span>
                                    </td>
                                    <td>{{ $job->created_at->format('M d, Y') }}</td>
                                    <td>
                                        @if($job->is_active && $job->deadline >= now())
                                            <span class="badge badge-light-success">Active</span>
                                        @elseif($job->deadline < now())
                                            <span class="badge badge-light-secondary">Expired</span>
                                        @else
                                            <span class="badge badge-light-danger">Inactive</span>
                                        @endif
                                        @if($job->is_featured)
                                            <span class="badge badge-light-primary">Featured</span>
                                        @endif
                                        @if($job->is_urgent)
                                            <span class="badge badge-light-warning">Urgent</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="text-center text-muted py-5">No jobs found matching the filters</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <div class="d-flex justify-content-between align-items-center mt-5">
                    <div class="text-muted fs-7">
                        Showing {{ $jobs->firstItem() ?? 0 }} to {{ $jobs->lastItem() ?? 0 }} of {{ $jobs->total() }} entries
                    </div>
                    <div>
                        {{ $jobs->appends(request()->except('page'))->links('pagination::bootstrap-5') }}
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Export Button -->
<div class="row g-5 g-xl-10 mt-3">
    <div class="col-12">
        <div class="card card-flush shadow-sm">
            <div class="card-body py-3">
                <div class="d-flex justify-content-end gap-2">
                    <a href="{{ route('admin.jobs-reports.export', ['type' => 'summary']) . '?' . http_build_query(request()->except('page', 'per_page')) }}" class="btn btn-sm btn-success">
                        <i class="ki-duotone ki-file-down fs-2 me-1"></i> Export CSV
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>
@endcan
@endsection