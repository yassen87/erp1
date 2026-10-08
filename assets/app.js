document.addEventListener('DOMContentLoaded', () => {
  const sections = [...document.querySelectorAll('.nav-section')];
  const body = document.body;
  const menuButton = document.querySelector('[data-menu-toggle]');
  const backdrop = document.querySelector('[data-menu-backdrop]');
  const closeButton = document.querySelector('[data-menu-close]');
  const search = document.querySelector('[data-nav-search]');

  sections.forEach((section) => {
    const button = section.querySelector('.nav-section-toggle');
    if (!button) return;

    button.addEventListener('click', () => {
      sections.forEach((other) => {
        if (other !== section) {
          other.classList.remove('open');
          other.querySelector('.nav-section-toggle')?.setAttribute('aria-expanded', 'false');
        }
      });

      const isOpen = section.classList.toggle('open');
      button.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
    });
  });

  menuButton?.addEventListener('click', () => body.classList.toggle('sidebar-open'));
  backdrop?.addEventListener('click', () => body.classList.remove('sidebar-open'));
  closeButton?.addEventListener('click', () => body.classList.remove('sidebar-open'));

  search?.addEventListener('input', () => {
    const term = search.value.trim().toLowerCase();
    document.querySelectorAll('[data-nav-item]').forEach((item) => {
      const match = item.textContent.toLowerCase().includes(term);
      item.hidden = term !== '' && !match;
    });

    if (term !== '') {
      sections.forEach((section) => section.classList.add('open'));
    }
  });

  document.querySelectorAll('[data-get-location]').forEach((button) => {
    button.addEventListener('click', () => {
      const isEn = document.documentElement.getAttribute('lang') === 'en';
      if (!navigator.geolocation) {
        alert(isEn ? 'Browser does not support geolocation' : 'المتصفح لا يدعم تحديد الموقع');
        return;
      }
      button.textContent = isEn ? 'Locating...' : 'جاري التحديد...';
      navigator.geolocation.getCurrentPosition((position) => {
        const form = button.closest('form');
        form?.querySelector('[data-lat]') && (form.querySelector('[data-lat]').value = position.coords.latitude.toFixed(7));
        form?.querySelector('[data-lng]') && (form.querySelector('[data-lng]').value = position.coords.longitude.toFixed(7));
        button.textContent = isEn ? 'Location set' : 'تم تحديد الموقع';
      }, () => {
        button.textContent = isEn ? 'Locate Me' : 'تحديد موقعي';
        alert(isEn ? 'Unable to determine location' : 'تعذر تحديد الموقع');
      }, { enableHighAccuracy: true, timeout: 10000 });
    });
  });

  // Global click listener to close all custom select boxes when clicking outside
  document.addEventListener('click', () => {
    document.querySelectorAll('.custom-select-wrapper').forEach(w => w.classList.remove('open'));
  });

  // Auto-scroll active sidebar nav item into view
  (function() {
    try {
      const active = document.querySelector('.nav-section-panel a.active, .nav-accordion a.active, [data-nav-item].active');
      if (active) {
        const scrollable = active.closest('.nav-accordion') || document.querySelector('.sidebar');
        if (scrollable) {
          // scrollIntoView on the active element will scroll the nearest scrollable ancestor
          active.scrollIntoView({ behavior: 'auto', block: 'nearest' });
        }
      }
    } catch (e) {
      // ignore
    }
  })();
});

// Custom Searchable Dropdown Builder (vanilla HTML/CSS/JS)
window.makeSelectSearchable = function(select) {
  if (!select || select.dataset.searchableInitialized) return;
  select.dataset.searchableInitialized = "true";

  const isEn = document.documentElement.getAttribute('lang') === 'en';
  const isMultiple = select.multiple;

  // Hide original select
  select.style.display = 'none';

  // Create wrapper
  const wrapper = document.createElement('div');
  wrapper.className = 'custom-select-wrapper';

  // Create trigger button
  const trigger = document.createElement('div');
  trigger.className = 'custom-select-trigger';

  // Set initial text from select option
  const updateTriggerText = () => {
    if (isMultiple) {
      const selectedOpts = Array.from(select.selectedOptions);
      if (selectedOpts.length === 0) {
        trigger.textContent = isEn ? '-- Select --' : '-- اختر --';
      } else if (selectedOpts.length <= 2) {
        trigger.textContent = selectedOpts.map(o => o.textContent).join(', ');
      } else {
        trigger.textContent = isEn ? `${selectedOpts.length} Selected` : `تم تحديد ${selectedOpts.length}`;
      }
    } else {
      const selectedOpt = select.options[select.selectedIndex];
      trigger.textContent = selectedOpt ? selectedOpt.textContent : (isEn ? '-- Select --' : '-- اختر --');
    }
  };
  updateTriggerText();

  // Create options container
  const optionsBox = document.createElement('div');
  optionsBox.className = 'custom-select-options-box';

  // Create search input
  const searchInput = document.createElement('input');
  searchInput.type = 'text';
  searchInput.placeholder = isEn ? 'Type to search...' : 'اكتب للبحث...';
  searchInput.className = 'custom-select-search';

  // Create list container
  const listContainer = document.createElement('div');
  listContainer.className = 'custom-select-options-list';

  // Function to rebuild options list
  const buildOptionsList = () => {
    listContainer.innerHTML = '';
    Array.from(select.options).forEach((opt, index) => {
      // Don't show options with empty value if multiple (like "-- Choose --") unless it's necessary
      if (isMultiple && opt.value === '') return;
      const item = document.createElement('div');
      item.className = 'custom-select-item';
      if (opt.selected) item.classList.add('selected');
      
      item.style.display = 'flex';
      item.style.alignItems = 'center';
      item.style.gap = '6px';
      item.style.paddingRight = '6px'; // reduce right padding
      
      if (isMultiple) {
        const checkbox = document.createElement('input');
        checkbox.type = 'checkbox';
        checkbox.checked = opt.selected;
        checkbox.style.pointerEvents = 'none';
        checkbox.style.flexShrink = '0';
        checkbox.style.margin = '0';
        checkbox.style.padding = '0';
        checkbox.style.width = '16px';
        checkbox.style.height = '16px';
        item.appendChild(checkbox);
      }
      
      const textSpan = document.createElement('span');
      textSpan.textContent = opt.textContent;
      textSpan.style.flex = '1';
      textSpan.style.whiteSpace = 'normal'; // allow text to wrap if it needs to, preventing cutoff
      textSpan.style.wordBreak = 'break-word';
      textSpan.style.textAlign = 'right';
      item.appendChild(textSpan);
      
      item.dataset.value = opt.value;
      item.dataset.index = index;

      item.addEventListener('click', (e) => {
        e.stopPropagation();
        
        if (isMultiple) {
          opt.selected = !opt.selected;
        } else {
          select.selectedIndex = index;
        }
        
        // Trigger change event on select
        select.dispatchEvent(new Event('change', { bubbles: true }));
        updateTriggerText();
        
        if (!isMultiple) {
          wrapper.classList.remove('open');
        } else {
          // just update the checkbox UI
          const cb = item.querySelector('input[type="checkbox"]');
          if (cb) cb.checked = opt.selected;
          if (opt.selected) item.classList.add('selected');
          else item.classList.remove('selected');
        }
      });
      listContainer.appendChild(item);
    });
  };

  buildOptionsList();

  // Search input filter logic
  searchInput.addEventListener('input', () => {
    const filter = searchInput.value.toLowerCase().trim();
    const items = listContainer.querySelectorAll('.custom-select-item');
    items.forEach(item => {
      const text = item.textContent.toLowerCase();
      if (text.includes(filter)) {
        item.style.display = 'flex';
      } else {
        item.style.display = 'none';
      }
    });
  });

  // Toggle dropdown
  trigger.addEventListener('click', (e) => {
    e.stopPropagation();

    // Close all other custom selects first
    document.querySelectorAll('.custom-select-wrapper').forEach(w => {
      if (w !== wrapper) w.classList.remove('open');
    });

    // Rebuild in case select options changed dynamically (like when a customer is added)
    buildOptionsList();

    wrapper.classList.toggle('open');
    if (wrapper.classList.contains('open')) {
      searchInput.value = '';
      searchInput.focus();
      // Reset filter
      listContainer.querySelectorAll('.custom-select-item').forEach(item => {
        item.style.display = 'flex';
      });
    }
  });

  // Assemble DOM
  optionsBox.appendChild(searchInput);
  optionsBox.appendChild(listContainer);
  wrapper.appendChild(trigger);
  optionsBox.addEventListener('click', (e) => e.stopPropagation()); // prevent closing when clicking search input
  wrapper.appendChild(optionsBox);

  // Insert wrapper before the select
  select.parentNode.insertBefore(wrapper, select);

  // Keep select element and wrapper in sync if value changed programmatically
  select.addEventListener('change', () => {
    updateTriggerText();
    const items = listContainer.querySelectorAll('.custom-select-item');
    items.forEach(item => {
      const opt = select.options[item.dataset.index];
      if (opt && opt.selected) {
        item.classList.add('selected');
        const cb = item.querySelector('input[type="checkbox"]');
        if(cb) cb.checked = true;
      } else {
        item.classList.remove('selected');
        const cb = item.querySelector('input[type="checkbox"]');
        if(cb) cb.checked = false;
      }
    });
  });
};

// Notes Inline Toggle Helper
window.toggleEditNote = function(id) {
  const wrapper = document.getElementById(`notes-w-${id}`);
  if (!wrapper) return;
  const textSpan = wrapper.querySelector('.notes-text');
  const editBtn = wrapper.querySelector('.btn-edit-note');
  const form = document.getElementById(`notes-f-${id}`);
  
  if (form.classList.contains('hidden')) {
    form.classList.remove('hidden');
    textSpan.classList.add('hidden');
    if (editBtn) editBtn.classList.add('hidden');
  } else {
    form.classList.add('hidden');
    textSpan.classList.remove('hidden');
    if (editBtn) editBtn.classList.remove('hidden');
  }
};

window.initTablePagination = function() {
  const pageSizeOptions = [25, 50, 100, 150];
  const defaultPageSize = 25;
  const savedPageSize = parseInt(localStorage.getItem('tablePageSize'), 10);
  const initialPageSize = pageSizeOptions.includes(savedPageSize) ? savedPageSize : defaultPageSize;
  const tableStates = [];

  function createControls(table, totalRows) {
    const controls = document.createElement('div');
    controls.className = 'table-pagination';

    const leftGroup = document.createElement('div');
    leftGroup.className = 'pagination-group';

    const sizeLabel = document.createElement('span');
    sizeLabel.textContent = 'عرض الصفوف:';
    leftGroup.appendChild(sizeLabel);

    const pageSizeSelect = document.createElement('select');
    pageSizeSelect.className = 'pagination-size-select';
    pageSizeOptions.forEach((size) => {
      const option = document.createElement('option');
      option.value = size;
      option.textContent = size;
      pageSizeSelect.appendChild(option);
    });
    pageSizeSelect.value = initialPageSize;
    leftGroup.appendChild(pageSizeSelect);
    controls.appendChild(leftGroup);

    const rightGroup = document.createElement('div');
    rightGroup.className = 'pagination-group';

    const info = document.createElement('span');
    info.className = 'pagination-info';
    rightGroup.appendChild(info);

    const nav = document.createElement('div');
    nav.className = 'pagination-nav';

    const firstBtn = document.createElement('button');
    firstBtn.type = 'button';
    firstBtn.className = 'btn small';
    firstBtn.textContent = '<<';
    nav.appendChild(firstBtn);

    const prevBtn = document.createElement('button');
    prevBtn.type = 'button';
    prevBtn.className = 'btn small';
    prevBtn.textContent = '<';
    nav.appendChild(prevBtn);

    const nextBtn = document.createElement('button');
    nextBtn.type = 'button';
    nextBtn.className = 'btn small';
    nextBtn.textContent = '>';
    nav.appendChild(nextBtn);

    const lastBtn = document.createElement('button');
    lastBtn.type = 'button';
    lastBtn.className = 'btn small';
    lastBtn.textContent = '>>';
    nav.appendChild(lastBtn);

    rightGroup.appendChild(nav);
    controls.appendChild(rightGroup);

    table.insertAdjacentElement('afterend', controls);

    return {
      controls,
      pageSizeSelect,
      info,
      firstBtn,
      prevBtn,
      nextBtn,
      lastBtn,
      totalRows,
    };
  }

  function renderPage(state) {
    const start = (state.currentPage - 1) * state.pageSize;
    const end = start + state.pageSize;
    state.rows.forEach((row, index) => {
      row.style.display = index >= start && index < end ? '' : 'none';
    });

    const totalPages = Math.max(1, Math.ceil(state.totalRows / state.pageSize));
    state.pageInfo = state.pageInfo || state.info;
    state.info.textContent = `الصفحة ${state.currentPage} من ${totalPages} · عرض ${Math.min(state.totalRows, state.pageSize)} من ${state.totalRows} صفوف`;

    state.firstBtn.disabled = state.currentPage === 1;
    state.prevBtn.disabled = state.currentPage === 1;
    state.nextBtn.disabled = state.currentPage >= totalPages;
    state.lastBtn.disabled = state.currentPage >= totalPages;
    state.controls.hidden = totalPages <= 1;
  }

  function updateAll(pageSize) {
    tableStates.forEach((state) => {
      state.pageSize = pageSize;
      state.currentPage = 1;
      state.pageSizeSelect.value = pageSize;
      renderPage(state);
    });
  }

  const tables = Array.from(document.querySelectorAll('table')).filter((table) => {
    if (table.closest('form')) return false;
    if (table.dataset.noPagination !== undefined) return false;
    if (!table.tBodies.length) return false;
    const rowCount = table.tBodies[0].querySelectorAll('tr').length;
    return rowCount > defaultPageSize;
  });

  tables.forEach((table) => {
    const rows = Array.from(table.tBodies[0].querySelectorAll('tr'));
    const controls = createControls(table, rows.length);
    const state = {
      table,
      rows,
      totalRows: rows.length,
      pageSize: initialPageSize,
      currentPage: 1,
      controls,
      info: controls.info,
      firstBtn: controls.firstBtn,
      prevBtn: controls.prevBtn,
      nextBtn: controls.nextBtn,
      lastBtn: controls.lastBtn,
      pageSizeSelect: controls.pageSizeSelect,
    };

    state.pageSizeSelect.addEventListener('change', (event) => {
      const newSize = parseInt(event.target.value, 10);
      if (!pageSizeOptions.includes(newSize)) return;
      localStorage.setItem('tablePageSize', String(newSize));
      updateAll(newSize);
    });

    state.firstBtn.addEventListener('click', () => {
      state.currentPage = 1;
      renderPage(state);
    });
    state.prevBtn.addEventListener('click', () => {
      state.currentPage = Math.max(1, state.currentPage - 1);
      renderPage(state);
    });
    state.nextBtn.addEventListener('click', () => {
      const totalPages = Math.max(1, Math.ceil(state.totalRows / state.pageSize));
      state.currentPage = Math.min(totalPages, state.currentPage + 1);
      renderPage(state);
    });
    state.lastBtn.addEventListener('click', () => {
      state.currentPage = Math.max(1, Math.ceil(state.totalRows / state.pageSize));
      renderPage(state);
    });

    tableStates.push(state);
    renderPage(state);
  });
};

window.addEventListener('DOMContentLoaded', () => {
  if (typeof window.initTablePagination === 'function') {
    window.initTablePagination();
  }

  // Force all date inputs to display dd/mm/yyyy format
  const formatInputs = () => {
    document.querySelectorAll('input[type="date"]').forEach(input => {
      if (input.getAttribute('lang') !== 'en-GB') {
        input.setAttribute('lang', 'en-GB');
      }
    });
  };
  formatInputs();

  // Watch for dynamically added inputs
  const observer = new MutationObserver((mutations) => {
    mutations.forEach(mutation => {
      if (mutation.addedNodes.length > 0) {
        formatInputs();
      }
    });
  });
  observer.observe(document.body, { childList: true, subtree: true });
});

