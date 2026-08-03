@extends('layouts.admin')

@section('title', 'Jobs Reports Dashboard')
@section('page_title', 'Jobs Reports Dashboard')

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
    <li class="breadcrumb-item text-muted">Jobs Reports</li>
@endsection

@section('content')
@can('view jobs')
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
                        <span class="fw-bold text-gray-800" style="font-size: clamp(0.8rem, 2vw, 1.5rem);">{{ $summary['total'] ?? 0 }}</span>
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
                        <span class="fw-bold text-gray-800" style="font-size: clamp(0.8rem, 2vw, 1.5rem);">{{ number_format($summary['views'] ?? 0) }}</span>
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
                        <span class="fw-bold text-gray-800" style="font-size: clamp(0.8rem, 2vw, 1.5rem);">{{ number_format($summary['applications'] ?? 0) }}</span>
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
                        <span class="fw-bold text-gray-800" style="font-size: clamp(0.8rem, 2vw, 1.5rem);">{{ number_format($summary['avg_views'] ?? 0, 1) }}</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Monthly Trends & Status Breakdown -->
<div class="row g-5 g-xl-10 mb-5">
    <!-- Monthly Trends Chart -->
    <div class="col-xxl-7 col-xl-7">
        <div class="card card-flush shadow-sm">
            <div class="card-header py-3">
                <h3 class="card-title fs-5 fw-bold">Monthly Trends</h3>
            </div>
            <div class="card-body">
                @if(count($monthlyTrends) > 0)
                    <div class="table-responsive">
                        <table class="table align-middle table-row-dashed fs-6 gy-3">
                            <thead>
                                <tr class="text-start text-gray-500 fw-bold fs-7 text-uppercase gs-0">
                                    <th>Month</th>
                                    <th class="text-center">Jobs</th>
                                    <th class="text-end">Views</th>
                                    <th class="text-end">Applications</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($monthlyTrends as $trend)
                                    <tr>
                                        <td>{{ $trend['month_label'] }}</td>
                                        <td class="text-center">
                                            <span class="badge badge-light-primary">{{ $trend['count'] }}</span>
                                        </td>
                                        <td class="text-end">{{ number_format($trend['views']) }}</td>
                                        <td class="text-end">{{ number_format($trend['applications']) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                            <tfoot>
                                <tr class="fw-bold">
                                    <td>Total</td>
                                    <td class="text-center">{{ collect($monthlyTrends)->sum('count') }}</td>
                                    <td class="text-end">{{ number_format(collect($monthlyTrends)->sum('views')) }}</td>
                                    <td class="text-end">{{ number_format(collect($monthlyTrends)->sum('applications')) }}</td>
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

    <!-- Status Breakdown -->
    <div class="col-xxl-5 col-xl-5">
        <div class="card card-flush shadow-sm">
            <div class="card-header py-3">
                <h3 class="card-title fs-5 fw-bold">Status Breakdown</h3>
            </div>
            <div class="card-body">
                @if(count($statusBreakdown) > 0)
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
                                @foreach($statusBreakdown as $status)
                                    @php
                                        $badgeColor = match($status['label']) {
                                            'Active' => 'success',
                                            'Inactive' => 'danger',
                                            'Expired' => 'secondary',
                                            'Featured' => 'primary',
                                            'Urgent' => 'warning',
                                            default => 'secondary'
                                        };
                                    @endphp
                                    <tr>
                                        <td>
                                            <span class="badge badge-light-{{ $badgeColor }}">
                                                {{ $status['label'] }}
                                            </span>
                                        </td>
                                        <td class="text-center fw-bold">{{ $status['count'] }}</td>
                                        <td class="text-end">{{ number_format($status['views']) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                            <tfoot>
                                <tr class="fw-bold">
                                    <td>Total</td>
                                    <td class="text-center">{{ collect($statusBreakdown)->sum('count') }}</td>
                                    <td class="text-end">{{ number_format(collect($statusBreakdown)->sum('views')) }}</td>
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

<!-- Top Categories & Top Companies -->
<div class="row g-5 g-xl-10 mb-5">
    <!-- Top Categories -->
    <div class="col-xxl-6 col-xl-6">
        <div class="card card-flush shadow-sm">
            <div class="card-header py-3">
                <h3 class="card-title fs-5 fw-bold">Top Categories</h3>
            </div>
            <div class="card-body">
                @if($topCategories->count() > 0)
                    <div class="table-responsive">
                        <table class="table align-middle table-row-dashed fs-6 gy-3">
                            <thead>
                                <tr class="text-start text-gray-500 fw-bold fs-7 text-uppercase gs-0">
                                    <th>Category</th>
                                    <th class="text-center">Jobs</th>
                                    <th class="text-end">Views</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($topCategories as $category)
                                    <tr>
                                        <td>{{ $category->category_name }}</td>
                                        <td class="text-center">
                                            <span class="badge badge-light-primary">{{ $category->count }}</span>
                                        </td>
                                        <td class="text-end">{{ number_format($category->views) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                            <tfoot>
                                <tr class="fw-bold">
                                    <td>Total</td>
                                    <td class="text-center">{{ $topCategories->sum('count') }}</td>
                                    <td class="text-end">{{ number_format($topCategories->sum('views')) }}</td>
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

    <!-- Top Companies -->
    <div class="col-xxl-6 col-xl-6">
        <div class="card card-flush shadow-sm">
            <div class="card-header py-3">
                <h3 class="card-title fs-5 fw-bold">Top Companies</h3>
            </div>
            <div class="card-body">
                @if($topCompanies->count() > 0)
                    <div class="table-responsive">
                        <table class="table align-middle table-row-dashed fs-6 gy-3">
                            <thead>
                                <tr class="text-start text-gray-500 fw-bold fs-7 text-uppercase gs-0">
                                    <th>Company</th>
                                    <th class="text-center">Jobs</th>
                                    <th class="text-end">Views</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($topCompanies as $company)
                                    <tr>
                                        <td>{{ $company->company_name }}</td>
                                        <td class="text-center">
                                            <span class="badge badge-light-primary">{{ $company->count }}</span>
                                        </td>
                                        <td class="text-end">{{ number_format($company->views) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                            <tfoot>
                                <tr class="fw-bold">
                                    <td>Total</td>
                                    <td class="text-center">{{ $topCompanies->sum('count') }}</td>
                                    <td class="text-end">{{ number_format($topCompanies->sum('views')) }}</td>
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

<!-- Recent Jobs -->
<div class="row g-5 g-xl-10">
    <div class="col-12">
        <div class="card card-flush shadow-sm">
            <div class="card-header py-3">
                <h3 class="card-title fs-5 fw-bold">Recent Jobs</h3>
                <div class="card-toolbar">
                    <div class="d-flex align-items-center gap-2">
                        <span class="text-muted fs-7">Per Page:</span>
                        <select class="form-select form-select-sm w-70px" onchange="window.location.href=this.value">
                            <option value="{{ request()->fullUrlWithQuery(['per_page' => 10, 'page' => 1]) }}" {{ ($perPage ?? 10) == 10 ? 'selected' : '' }}>10</option>
                            <option value="{{ request()->fullUrlWithQuery(['per_page' => 20, 'page' => 1]) }}" {{ ($perPage ?? 10) == 20 ? 'selected' : '' }}>20</option>
                            <option value="{{ request()->fullUrlWithQuery(['per_page' => 50, 'page' => 1]) }}" {{ ($perPage ?? 10) == 50 ? 'selected' : '' }}>50</option>
                            <option value="{{ request()->fullUrlWithQuery(['per_page' => 100, 'page' => 1]) }}" {{ ($perPage ?? 10) == 100 ? 'selected' : '' }}>100</option>
                        </select>
                    </div>
                    <a href="{{ route('admin.jobs-reports.summary') }}" class="btn btn-sm btn-primary ms-2">
                        <i class="ki-duotone ki-chart fs-2 me-1"></i> View Full Report
                    </a>
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
                            @forelse($recentJobs as $job)
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
                                    <td colspan="7" class="text-center text-muted py-5">No jobs found</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <!-- Pagination -->
                <div class="d-flex justify-content-between align-items-center mt-5">
                    <div class="text-muted fs-7">
                        Showing {{ $recentJobs->firstItem() ?? 0 }} to {{ $recentJobs->lastItem() ?? 0 }} of {{ $recentJobs->total() }} entries
                    </div>
                    <div>
                        {{ $recentJobs->appends(request()->except('page'))->links('pagination::bootstrap-5') }}
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endcan
@endsection