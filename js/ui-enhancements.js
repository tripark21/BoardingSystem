/**
 * BoardingEase - Modern UI/UX Enhancements
 * Professional Design System with JavaScript Interactions
 */

// ── THEME & ANIMATIONS ──
class UIEnhancements {
  constructor() {
    this.init();
  }

  init() {
    this.setupAnimation();
    this.setupSmoothScroll();
    this.setupHoverEffects();
    this.setupModals();
    this.setupFormEnhancements();
    this.setupTableInteractions();
    this.setupStatCardsAnimation();
    this.setupResponsiveSidebar();
    this.setupLoadingStates();
    this.observeElementsEntry();
  }

  // ── SMOOTH ANIMATIONS ON SCROLL ──
  observeElementsEntry() {
    const observer = new IntersectionObserver((entries) => {
      entries.forEach((entry) => {
        if (entry.isIntersecting) {
          entry.target.style.animation = 'fadeInUp 0.6s cubic-bezier(0.4, 0, 0.2, 1) forwards';
          observer.unobserve(entry.target);
        }
      });
    }, { threshold: 0.1 });

    document.querySelectorAll('.card, .stat-card').forEach((el) => {
      observer.observe(el);
    });
  }

  // ── SMOOTH SCROLLING ──
  setupSmoothScroll() {
    document.querySelectorAll('a[href^="#"]').forEach((anchor) => {
      anchor.addEventListener('click', (e) => {
        const target = document.querySelector(anchor.getAttribute('href'));
        if (target) {
          e.preventDefault();
          target.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
      });
    });
  }

  // ── HOVER EFFECTS ──
  setupHoverEffects() {
    // Card hover enhancement
    document.querySelectorAll('.card').forEach((card) => {
      card.addEventListener('mouseenter', () => {
        card.style.transform = 'translateY(-2px)';
      });
      card.addEventListener('mouseleave', () => {
        card.style.transform = 'translateY(0)';
      });
    });

    // Button ripple effect
    document.querySelectorAll('.btn').forEach((btn) => {
      btn.addEventListener('click', function (e) {
        const rect = this.getBoundingClientRect();
        const x = e.clientX - rect.left;
        const y = e.clientY - rect.top;
        const ripple = document.createElement('div');
        ripple.className = 'ripple';
        ripple.style.left = x + 'px';
        ripple.style.top = y + 'px';
        this.appendChild(ripple);
        setTimeout(() => ripple.remove(), 600);
      });
    });
  }

  // ── MODAL MANAGEMENT ──
  setupModals() {
    window.openModal = (id) => {
      const modal = document.getElementById(id);
      if (modal) {
        modal.classList.add('open');
        document.body.style.overflow = 'hidden';
        // Trigger animation
        modal.querySelector('.modal')?.style.animation = 'zoomIn 0.3s ease-out';
      }
    };

    window.closeModal = (id) => {
      const modal = document.getElementById(id);
      if (modal) {
        modal.classList.remove('open');
        document.body.style.overflow = '';
      }
    };

    // Close on overlay click
    document.querySelectorAll('.modal-overlay').forEach((overlay) => {
      overlay.addEventListener('click', (e) => {
        if (e.target === overlay) {
          overlayElement.classList.remove('open');
          document.body.style.overflow = '';
        }
      });
    });

    // Close on Escape
    document.addEventListener('keydown', (e) => {
      if (e.key === 'Escape') {
        document.querySelectorAll('.modal-overlay.open').forEach((modal) => {
          modal.classList.remove('open');
        });
        document.body.style.overflow = '';
      }
    });

    // Close button
    document.querySelectorAll('.modal-close').forEach((btn) => {
      btn.addEventListener('click', () => {
        btn.closest('.modal-overlay')?.classList.remove('open');
        document.body.style.overflow = '';
      });
    });
  }

  // ── FORM ENHANCEMENTS ──
  setupFormEnhancements() {
    // Input focus animation
    document.querySelectorAll('.form-control').forEach((input) => {
      input.addEventListener('focus', () => {
        input.style.borderColor = 'var(--accent)';
        input.style.boxShadow = '0 0 0 3px rgba(249, 115, 22, 0.1)';
      });
      input.addEventListener('blur', () => {
        input.style.borderColor = 'var(--border)';
        input.style.boxShadow = 'none';
      });

      // Character counter
      if (input.type === 'text' || input.type === 'password') {
        input.addEventListener('input', () => {
          input.style.borderColor = input.value ? 'var(--accent)' : 'var(--border)';
        });
      }
    });

    // Form validation visual feedback
    document.querySelectorAll('form').forEach((form) => {
      form.addEventListener('submit', (e) => {
        let isValid = true;
        form.querySelectorAll('[required]').forEach((field) => {
          if (!field.value.trim()) {
            isValid = false;
            field.style.borderColor = 'var(--danger)';
            field.style.animation = 'shake 0.4s ease-in-out';
          }
        });
        if (!isValid) e.preventDefault();
      });
    });
  }

  // ── TABLE INTERACTIONS ──
  setupTableInteractions() {
    document.querySelectorAll('tbody tr').forEach((row) => {
      row.addEventListener('mouseenter', () => {
        row.style.backgroundColor = 'var(--bg-dark)';
        row.style.boxShadow = 'inset 0 0 0 1px var(--border)';
      });
      row.addEventListener('mouseleave', () => {
        row.style.backgroundColor = '';
        row.style.boxShadow = '';
      });
    });

    // Sortable tables (if data-sortable attribute exists)
    document.querySelectorAll('thead th[data-sortable]').forEach((th) => {
      th.style.cursor = 'pointer';
      th.addEventListener('click', () => {
        this.sortTable(th);
      });
    });
  }

  // ── STAT CARDS ANIMATION ──
  setupStatCardsAnimation() {
    document.querySelectorAll('.stat-value').forEach((el) => {
      const finalValue = parseInt(el.textContent);
      if (!isNaN(finalValue)) {
        el.addEventListener('mouseenter', () => {
          this.animateValue(el, 0, finalValue, 800);
        });
      }
    });
  }

  animateValue(element, start, end, duration) {
    const range = end - start;
    const increment = end > start ? 1 : -1;
    const stepTime = Math.abs(Math.floor(duration / range));
    let current = start;

    const timer = setInterval(() => {
      current += increment;
      if ((increment > 0 && current >= end) || (increment < 0 && current <= end)) {
        clearInterval(timer);
        current = end;
      }
      element.textContent = current.toLocaleString();
    }, stepTime);
  }

  // ── RESPONSIVE SIDEBAR ──
  setupResponsiveSidebar() {
    const sidebar = document.querySelector('.sidebar');
    if (!sidebar) return;

    // Hamburger menu
    const hamburger = document.createElement('button');
    hamburger.className = 'hamburger-menu';
    hamburger.innerHTML = '☰';
    hamburger.style.cssText = `
      display: none;
      position: fixed;
      top: 16px;
      left: 16px;
      z-index: 999;
      background: var(--accent);
      color: white;
      border: none;
      border-radius: 8px;
      padding: 8px 12px;
      cursor: pointer;
      font-size: 20px;
    `;

    document.body.appendChild(hamburger);

    hamburger.addEventListener('click', () => {
      sidebar.classList.toggle('open');
      hamburger.textContent = sidebar.classList.contains('open') ? '✕' : '☰';
    });

    // Show hamburger on mobile
    window.addEventListener('resize', () => {
      if (window.innerWidth <= 768) {
        hamburger.style.display = 'block';
      } else {
        hamburger.style.display = 'none';
        sidebar.classList.remove('open');
      }
    });

    if (window.innerWidth <= 768) {
      hamburger.style.display = 'block';
    }
  }

  // ── LOADING STATES ──
  setupLoadingStates() {
    document.querySelectorAll('form').forEach((form) => {
      form.addEventListener('submit', () => {
        const submitBtn = form.querySelector('button[type="submit"]');
        if (submitBtn) {
          const originalText = submitBtn.textContent;
          submitBtn.disabled = true;
          submitBtn.textContent = '⏳ Loading...';
          submitBtn.style.opacity = '0.7';

          // Reset after 3 seconds (adjust based on your backend)
          setTimeout(() => {
            submitBtn.disabled = false;
            submitBtn.textContent = originalText;
            submitBtn.style.opacity = '1';
          }, 3000);
        }
      });
    });
  }

  // ── UTILITY: Sort Table ──
  sortTable(th) {
    const table = th.closest('table');
    const tbody = table.querySelector('tbody');
    const rows = Array.from(tbody.querySelectorAll('tr'));
    const index = Array.from(th.parentNode.children).indexOf(th);
    const isAsc = th.classList.toggle('sort-asc');

    rows.sort((a, b) => {
      const aValue = a.cells[index].textContent.trim();
      const bValue = b.cells[index].textContent.trim();
      return (isAsc ? aValue > bValue : aValue < bValue) ? 1 : -1;
    });

    rows.forEach((row) => tbody.appendChild(row));
  }

  // ── SETUP ANIMATIONS ──
  setupAnimation() {
    const style = document.createElement('style');
    style.textContent = `
      @keyframes fadeInUp {
        from {
          opacity: 0;
          transform: translateY(20px);
        }
        to {
          opacity: 1;
          transform: translateY(0);
        }
      }

      @keyframes zoomIn {
        from {
          opacity: 0;
          transform: scale(0.95);
        }
        to {
          opacity: 1;
          transform: scale(1);
        }
      }

      @keyframes shake {
        0%, 100% { transform: translateX(0); }
        25% { transform: translateX(-5px); }
        75% { transform: translateX(5px); }
      }

      @keyframes slideDown {
        from {
          opacity: 0;
          transform: translateY(-10px);
        }
        to {
          opacity: 1;
          transform: translateY(0);
        }
      }

      .ripple {
        position: absolute;
        border-radius: 50%;
        background: rgba(255, 255, 255, 0.6);
        width: 20px;
        height: 20px;
        pointer-events: none;
        animation: ripple-animation 0.6s ease-out;
      }

      @keyframes ripple-animation {
        from {
          opacity: 1;
          transform: scale(1);
        }
        to {
          opacity: 0;
          transform: scale(4);
        }
      }

      .btn {
        position: relative;
        overflow: hidden;
      }
    `;
    document.head.appendChild(style);
  }
}

// ── INITIALIZE ON DOM READY ──
document.addEventListener('DOMContentLoaded', () => {
  new UIEnhancements();
});

// ── GLOBAL UTILITY FUNCTIONS ──
window.showAlert = (message, type = 'info') => {
  const alert = document.createElement('div');
  alert.className = `alert alert-${type}`;
  alert.textContent = message;
  alert.style.animation = 'slideDown 0.3s ease-out';

  const container = document.querySelector('.page-body') || document.body;
  container.insertBefore(alert, container.firstChild);

  setTimeout(() => {
    alert.style.animation = 'fadeOut 0.3s ease-out';
    setTimeout(() => alert.remove(), 300);
  }, 3000);
};

window.showLoading = (text = 'Loading...') => {
  const loader = document.createElement('div');
  loader.id = 'global-loader';
  loader.style.cssText = `
    position: fixed;
    inset: 0;
    background: rgba(255, 255, 255, 0.9);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 999;
    flex-direction: column;
    gap: 16px;
  `;
  loader.innerHTML = `
    <div style="
      width: 40px;
      height: 40px;
      border: 3px solid var(--bg-dark);
      border-top: 3px solid var(--accent);
      border-radius: 50%;
      animation: spin 1s linear infinite;
    "></div>
    <p style="color: var(--text-muted);">${text}</p>
  `;
  document.body.appendChild(loader);
};

window.hideLoading = () => {
  const loader = document.getElementById('global-loader');
  if (loader) loader.remove();
};

// ── TOAST NOTIFICATIONS ──
class Toast {
  static show(message, type = 'info', duration = 3000) {
    const toast = document.createElement('div');
    toast.className = `toast toast-${type}`;
    toast.style.cssText = `
      position: fixed;
      bottom: 24px;
      right: 24px;
      background: var(--${type === 'error' ? 'danger' : type === 'success' ? 'success' : 'info'});
      color: white;
      padding: 14px 20px;
      border-radius: 8px;
      box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
      z-index: 9999;
      animation: slideUp 0.3s ease-out;
    `;
    toast.textContent = message;
    document.body.appendChild(toast);

    setTimeout(() => {
      toast.style.animation = 'slideDown 0.3s ease-out';
      setTimeout(() => toast.remove(), 300);
    }, duration);
  }
}

// ── ADD ANIMATIONS TO GLOBAL STYLE ──
const toastStyle = document.createElement('style');
toastStyle.textContent = `
  @keyframes slideUp {
    from { opacity: 0; transform: translateY(20px); }
    to { opacity: 1; transform: translateY(0); }
  }

  @keyframes slideDown {
    from { opacity: 1; transform: translateY(0); }
    to { opacity: 0; transform: translateY(20px); }
  }

  @keyframes spin {
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
  }

  @keyframes fadeOut {
    from { opacity: 1; }
    to { opacity: 0; }
  }
`;
document.head.appendChild(toastStyle);
