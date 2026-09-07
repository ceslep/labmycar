/**
 * utils.js — Utility Functions for Clinical Lab Viewer
 * CountUp animation, skeleton loading, command palette, toast notifications, flatpickr init
 */
(function() {
  'use strict';

  // ─── 1. COUNTUP ANIMATION ──────────────────────────────
  window.animateCountUp = function(el, target, duration) {
    duration = duration || 1200;
    var start = parseInt(el.textContent) || 0;
    var diff = target - start;
    if (diff === 0) return;
    var startTime = null;

    function step(timestamp) {
      if (!startTime) startTime = timestamp;
      var progress = Math.min((timestamp - startTime) / duration, 1);
      // Ease out cubic
      var eased = 1 - Math.pow(1 - progress, 3);
      el.textContent = Math.round(start + diff * eased);
      if (progress < 1) {
        requestAnimationFrame(step);
      }
    }

    requestAnimationFrame(step);
  };

  // Animate all elements with data-countup attribute
  window.initCountUps = function() {
    document.querySelectorAll('[data-countup]').forEach(function(el) {
      var target = parseInt(el.getAttribute('data-countup'));
      if (!isNaN(target)) {
        animateCountUp(el, target);
      }
    });
  };

  // ─── 2. SKELETON LOADING ───────────────────────────────
  window.showSkeleton = function(container, rows, height) {
    rows = rows || 5;
    height = height || '4rem';
    var html = '';
    for (var i = 0; i < rows; i++) {
      html += '<div class="skeleton" style="height:' + height + ';margin-bottom:.5rem;border-radius:12px;animation-delay:' + (i * 0.08) + 's"></div>';
    }
    container.innerHTML = html;
  };

  window.hideSkeleton = function(container) {
    container.innerHTML = '';
  };

  // ─── 3. TOAST NOTIFICATION SYSTEM ──────────────────────
  window.labToast = function(message, type, duration) {
    type = type || 'info';
    duration = duration || 4000;

    var container = document.getElementById('toast-container');
    if (!container) {
      container = document.createElement('div');
      container.id = 'toast-container';
      document.body.appendChild(container);
    }

    var icons = {
      success: 'bi-check-circle-fill',
      error: 'bi-x-circle-fill',
      warning: 'bi-exclamation-triangle-fill',
      info: 'bi-info-circle-fill'
    };
    var titles = {
      success: 'Exito',
      error: 'Error',
      warning: 'Advertencia',
      info: 'Informacion'
    };

    var toast = document.createElement('div');
    toast.className = 'lab-toast lab-toast-' + type;
    toast.setAttribute('role', 'status');
    toast.setAttribute('aria-live', 'polite');
    toast.innerHTML =
      '<div class="lab-toast-icon"><i class="bi ' + (icons[type] || icons.info) + '"></i></div>' +
      '<div class="lab-toast-content">' +
        '<div style="font-weight:700;line-height:1.3">' + (titles[type] || titles.info) + '</div>' +
        '<div style="font-size:var(--lab-text-2xs);opacity:.9;margin-top:.125rem">' + message + '</div>' +
      '</div>' +
      '<button class="lab-toast-close" aria-label="Cerrar notificacion"><i class="bi bi-x-lg" style="font-size:.7rem"></i></button>' +
      '<div class="lab-toast-progress" style="width:100%"></div>';

    container.appendChild(toast);

    var progress = toast.querySelector('.lab-toast-progress');
    var closeBtn = toast.querySelector('.lab-toast-close');
    var startTime = null;
    var remaining = duration;
    var lastTime = performance.now();
    var rafId = null;
    var paused = false;

    function dismiss() {
      if (rafId) cancelAnimationFrame(rafId);
      toast.classList.remove('lab-toast-show');
      setTimeout(function() {
        if (toast.parentNode) toast.parentNode.removeChild(toast);
      }, 350);
    }

    closeBtn.addEventListener('click', dismiss);
    toast.addEventListener('mouseenter', function() { paused = true; });
    toast.addEventListener('mouseleave', function() { paused = false; lastTime = performance.now(); });

    function tick(now) {
      if (!startTime) startTime = now;
      if (!paused) {
        var delta = now - lastTime;
        remaining -= delta;
        var pct = Math.max(0, (remaining / duration) * 100);
        progress.style.width = pct + '%';
      }
      lastTime = now;
      if (remaining > 0) {
        rafId = requestAnimationFrame(tick);
      } else {
        dismiss();
      }
    }

    // Trigger show animation
    requestAnimationFrame(function() {
      requestAnimationFrame(function() {
        toast.classList.add('lab-toast-show');
        rafId = requestAnimationFrame(tick);
      });
    });
  };

  // ─── 4. DARK MODE ──────────────────────────────────────
  window.initDarkMode = function() {
    var stored = localStorage.getItem('lab-dark-mode');
    if (stored === 'true') {
      document.documentElement.classList.add('dark');
    } else if (stored === null) {
      // Auto-detect system preference
      if (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) {
        document.documentElement.classList.add('dark');
        localStorage.setItem('lab-dark-mode', 'true');
      }
    }
  };

  window.toggleDarkMode = function() {
    document.documentElement.classList.toggle('dark');
    var isDark = document.documentElement.classList.contains('dark');
    localStorage.setItem('lab-dark-mode', isDark.toString());

    // Update flatpickr theme if loaded
    if (typeof flatpickr !== 'undefined') {
      document.querySelectorAll('.flatpickr-input').forEach(function(el) {
        if (el._flatpickr) {
          el._flatpickr.set('theme', isDark ? 'dark' : 'default');
        }
      });
    }
  };

  window.isDarkMode = function() {
    return document.documentElement.classList.contains('dark');
  };

  // ─── 5. FLATPICKR INIT ─────────────────────────────────
  window.initFlatpickr = function() {
    if (typeof flatpickr === 'undefined') return;

    var isDark = isDarkMode();

    // Destroy existing instances first
    document.querySelectorAll('.flatpickr-input').forEach(function(el) {
      if (el._flatpickr) el._flatpickr.destroy();
    });

    document.querySelectorAll('input[type="date"]:not(.flatpickr-input)').forEach(function(el) {
      flatpickr(el, {
        dateFormat: 'Y-m-d',
        locale: 'es',
        theme: isDark ? 'dark' : 'default',
        disableMobile: true,
        animate: true,
        onChange: function(selectedDates, dateStr) {
          el.value = dateStr;
          el.dispatchEvent(new Event('change', { bubbles: true }));
          el.dispatchEvent(new Event('input', { bubbles: true }));
        }
      });
    });
  };

  // ─── 6. COMMAND PALETTE ────────────────────────────────
  window.initCommandPalette = function() {
    document.addEventListener('keydown', function(e) {
      // Ctrl+K or Cmd+K
      if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
        e.preventDefault();
        var event = new CustomEvent('toggle-command-palette');
        document.dispatchEvent(event);
      }
    });
  };

  // ─── 7. INTERSECTION OBSERVER (Infinite Scroll) ────────
  window.setupInfiniteScroll = function(callback, triggerId) {
    triggerId = triggerId || 'load-more-trigger';

    var observer = new IntersectionObserver(function(entries) {
      entries.forEach(function(entry) {
        if (entry.isIntersecting) {
          callback();
        }
      });
    }, {
      threshold: 0.1,
      rootMargin: '100px'
    });

    var trigger = document.getElementById(triggerId);
    if (trigger) observer.observe(trigger);

    return observer;
  };

  // ─── 8. KEYBOARD SHORTCUTS ─────────────────────────────
  window.initKeyboardShortcuts = function(appData) {
    document.addEventListener('keydown', function(e) {
      // Escape - close modals
      if (e.key === 'Escape') {
        if (appData) {
          if (appData.showCommandPalette) {
            appData.showCommandPalette = false;
          } else if (appData.mostrarModalTelefono) {
            appData.mostrarModalTelefono = false;
          } else if (appData.mostrarModalEntidades) {
            appData.mostrarModalEntidades = false;
          } else if (appData.mostrarModalPacientesBusqueda) {
            appData.cerrarModalPacientesBusqueda();
          } else if (appData.vistaReporte) {
            appData.urlReporte = null;
            appData.vistaReporte = false;
          }
        }
      }
    });
  };

  // ─── 9. DEBOUNCE UTILITY ──────────────────────────────
  window.debounce = function(fn, delay) {
    var timer;
    return function() {
      var context = this;
      var args = arguments;
      clearTimeout(timer);
      timer = setTimeout(function() {
        fn.apply(context, args);
      }, delay);
    };
  };

  // ─── 10. FORMAT HELPERS ────────────────────────────────
  window.formatNumber = function(num) {
    return new Intl.NumberFormat('es-CO').format(num);
  };

  window.formatDate = function(dateStr) {
    if (!dateStr) return '';
    var d = new Date(dateStr + 'T00:00:00');
    return d.toLocaleDateString('es-CO', {
      weekday: 'long',
      year: 'numeric',
      month: 'long',
      day: 'numeric'
    });
  };

  window.formatDateShort = function(dateStr) {
    if (!dateStr) return '';
    var d = new Date(dateStr + 'T00:00:00');
    return d.toLocaleDateString('es-CO');
  };

  // ─── 11. AUTO-INIT ─────────────────────────────────────
  document.addEventListener('DOMContentLoaded', function() {
    initDarkMode();
    initCommandPalette();
  });

})();
