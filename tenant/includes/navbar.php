<div class="container-fluid">
    <div class="d-flex justify-content-end align-items-center py-2">
        
        <!-- User Dropdown -->
        <div class="dropdown">
  <button class="btn btn-light dropdown-toggle d-flex align-items-center" type="button" id="userDropdown" data-bs-toggle="dropdown" aria-expanded="false">
    <i class="bi bi-person-circle me-1 fs-5"></i>
    <span><?php echo htmlspecialchars($_SESSION['username'] ?? ''); ?></span>
  </button>
  <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userDropdown">
    <li><a class="dropdown-item" href="profile.php"><i class="bi bi-person me-2"></i>Profile</a></li>
    <li><a class="dropdown-item" href="settings.php"><i class="bi bi-gear me-2"></i>Settings</a></li>

    <!-- Dark Mode Toggle Inside Dropdown -->
    <li class="px-3 py-2">
      <div class="form-check form-switch d-flex align-items-center">
        <input class="form-check-input me-2" type="checkbox" id="darkModeToggle">
        <label class="form-check-label mb-0" for="darkModeToggle">
          <i class="bi bi-moon-stars me-1"></i> Dark Mode
        </label>
      </div>
    </li>

    <li><hr class="dropdown-divider"></li>
    <li><a class="dropdown-item" href="../logout.php"><i class="bi bi-box-arrow-left me-2"></i>Logout</a></li>
  </ul>
</div>

    </div>
</div>

<script>
    
// Sidebar toggle functionality
document.getElementById('sidebarToggle').addEventListener('click', function() {
    document.querySelector('.sidebar').classList.toggle('collapsed');
    document.getElementById('content').classList.toggle('expanded');
    
    // Save state in localStorage
    const isCollapsed = document.querySelector('.sidebar').classList.contains('collapsed');
    localStorage.setItem('sidebarCollapsed', isCollapsed);
});

// Check for saved state on page load
document.addEventListener('DOMContentLoaded', function() {
    const isCollapsed = localStorage.getItem('sidebarCollapsed') === 'true';
    if (isCollapsed) {
        document.querySelector('.sidebar').classList.add('collapsed');
        document.getElementById('content').classList.add('expanded');
    }
});
</script>