@extends('investor.layout')

@section('title', 'Projects - Auxiliare')

@section('additional_css')
<link rel="stylesheet" href="{{ asset('css/investor/projects.css') }}">
<link rel="stylesheet" href="{{ asset('css/entrepreneur/home.css') }}">

@endsection

@section('content')
<div class="projects-wrapper">
    <div class="projects-container">
        <!-- Investment Overview Section -->
        <div class="stats-row">
            <div class="stats-category">
                <h4 class="category-title">
                    <i class="fas fa-chart-pie"></i> Investment Dashboard
                </h4>
                <div class="info-box">
                    <span class="info-box-icon bg-primary"><i class="fas fa-rocket"></i></span>
                    <div class="info-box-content">
                        <span>Active Opportunities</span>
                        <span>{{ $totalProjects }}</span>
                    </div>
                </div>
                <div class="info-box">
                    <span class="info-box-icon bg-success"><i class="fas fa-hand-holding-usd"></i></span>
                    <div class="info-box-content">
                        <span>Total Investment Pool</span>
                        <span>₱{{ number_format($totalFundingNeeded) }}</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Category Filters -->
        <div class="filter-section">
            <h4><i class="fas fa-filter"></i> Filter Projects</h4>
            <div class="category-filters">
                <button class="category-filter active" data-category="all">All Projects</button>
                @foreach($categories as $category)
                    <button class="category-filter" data-category="{{ $category }}">
                        {{ $category }}
                    </button>
                @endforeach
            </div>
        </div>

        <!-- Projects Grid -->
        <div class="projects-grid">
            @forelse($projects as $project)
            <div class="project-card">
                <div class="project-header">
                    <div class="title-section">
                        <h3>{{ $project->title }}</h3>
                        <span class="category-badge">{{ $project->category }}</span>
                    </div>
                    <div class="status-badge {{ strtotime($project->end_date) < time() ? 'closed' : 'active' }}">
                        {{ strtotime($project->end_date) < time() ? 'Closed' : 'Active' }}
                    </div>
                </div>

                <p class="description">{{ $project->description }}</p>

                <div class="investment-details">
                    <div class="detail-item">
                        <i class="fas fa-users"></i>
                        <span>Min. Investment: ₱{{ number_format($project->minimum_investment) }}</span>
                    </div>
                    <div class="detail-item">
                        <i class="fas fa-clock"></i>
                        <span>{{ \Carbon\Carbon::parse($project->end_date)->diffForHumans() }}</span>
                    </div>
                </div>

                <div class="funding-progress">
                    @php
                    $percentage = ($project->current_funding / $project->funding_goal) * 100;
                    $percentage = min(100, round($percentage));
                    @endphp
                    <div class="progress-info">
                        <span class="progress-label">Funding Progress</span>
                        <span class="progress-percentage">{{ $percentage }}%</span>
                    </div>
                    <div class="progress-bar">
                        <div class="progress" style="width: {{ $percentage }}%"></div>
                    </div>
                    <div class="funding-stats">
                        <span>₱{{ number_format($project->current_funding) }} raised</span>
                        <span>of ₱{{ number_format($project->funding_goal) }} goal</span>
                    </div>
                </div>

                <div class="entrepreneur-info">
                    <img src="{{ $project->user->avatar ?? asset('assets/default-avatar.png') }}" alt="Entrepreneur" class="avatar">
                    <div class="info">
                        <span class="name">{{ $project->user->firstname }} {{ $project->user->lastname }}</span>
                        <span class="role">Project Lead</span>
                    </div>
                    <button class="contact-btn" title="Contact Entrepreneur">
                        <i class="fas fa-envelope"></i>
                    </button>
                </div>

                <div class="project-meta">
                    <div class="meta-info">
                        <span class="deadline">
                            <i class="fas fa-calendar-alt"></i>
                            Deadline: {{ date('M d, Y', strtotime($project->end_date)) }}
                        </span>
                    </div>
                    <a href="{{ route('investor.project.details', $project->id) }}" class="invest-btn">
                        <i class="fas fa-chart-line"></i> Invest Now
                    </a>
                </div>
            </div>
            @empty
            <div class="no-projects">
                <i class="fas fa-folder-open"></i>
                <p>No projects available for investment at this time.</p>
            </div>
            @endforelse
        </div>
    </div>
</div>

@section('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    const filterButtons = document.querySelectorAll('.category-filter');
    const projectsGrid = document.querySelector('.projects-grid');
    const statsContent = document.querySelector('.stats-category');
    
    filterButtons.forEach(button => {
        button.addEventListener('click', async function() {
            // Remove active class from all buttons
            filterButtons.forEach(btn => btn.classList.remove('active'));
            // Add active class to clicked button
            this.classList.add('active');
            
            const category = this.dataset.category;
            
            try {
                // Show loading state
                projectsGrid.style.opacity = '0.5';
                projectsGrid.style.pointerEvents = 'none';
                
                // Fetch filtered projects
                const response = await fetch(`/investor/filter-projects?category=${category}`, {
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                });
                
                const data = await response.json();
                
                // Animate out old content
                projectsGrid.style.opacity = '0';
                
                // Update content
                setTimeout(() => {
                    projectsGrid.innerHTML = data.html;
                    
                    // Update stats
                    document.querySelector('.info-box-content span:last-child').textContent = data.totalProjects;
                    document.querySelector('.info-box:last-child .info-box-content span:last-child').textContent = 
                        '₱' + Number(data.totalFundingNeeded).toLocaleString();
                    
                    // Animate in new content
                    projectsGrid.style.opacity = '1';
                    projectsGrid.style.pointerEvents = 'auto';
                }, 300);
                
            } catch (error) {
                console.error('Error:', error);
                projectsGrid.style.opacity = '1';
                projectsGrid.style.pointerEvents = 'auto';
            }
        });
    });
});
</script>
@endsection