/**
 * JSON Search - Frontend JavaScript
 *
 * Supports 2 modes: Popup and Inline (dropdown below search input)
 *
 * @package Simple_Realtime_Search
 */

(function() {
    'use strict';

    var config = window.srsPublic || {};
    var searchData = null;
    var debounceTimer = null;
    var activeInlineDropdown = null; // Track current dropdown (inline mode)

    // Debug mode.
    var DEBUG = false;

    function log() {
        if (DEBUG && console && console.log) {
            console.log.apply(console, ['[Simple Realtime Search]'].concat(Array.prototype.slice.call(arguments)));
        }
    }

    // Templates from wp.template.
    var templates = {
        item: null,
        group: null,
        noResults: null
    };

    /**
     * Initialize templates.
     */
    function initTemplates() {
        if (typeof wp !== 'undefined' && wp.template) {
            try {
                templates.item = wp.template('srs-search-item');
                templates.group = wp.template('srs-search-group');
                templates.noResults = wp.template('srs-no-results');
                log('Templates initialized');
            } catch (e) {
                log('Error initializing templates:', e);
            }
        } else {
            log('wp.template not available');
        }
    }

    /**
     * Get popup elements.
     */
    function getPopupElements() {
        return {
            overlay: document.getElementById('srs-search-overlay'),
            input: document.getElementById('srs-search-input'),
            results: document.getElementById('srs-search-results'),
            closeBtn: document.getElementById('srs-search-close')
        };
    }

    /**
     * Load JSON data.
     *
     * @param {Function} callback Callback after load.
     */
    function loadSearchData(callback) {
        if (searchData !== null) {
            log('Using cached data:', searchData.length, 'items');
            callback(searchData);
            return;
        }

        if (!config.jsonUrl) {
            log('No jsonUrl configured');
            callback([]);
            return;
        }

        log('Fetching JSON from:', config.jsonUrl);

        fetch(config.jsonUrl)
            .then(function(response) {
                if (!response.ok) {
                    throw new Error('HTTP ' + response.status);
                }
                return response.json();
            })
            .then(function(data) {
                searchData = Array.isArray(data) ? data : [];
                log('Loaded', searchData.length, 'items');
                callback(searchData);
            })
            .catch(function(error) {
                log('Error fetching data:', error);
                searchData = [];
                callback([]);
            });
    }

    /**
     * Remove diacritics (Vietnamese accents).
     */
    function removeDiacritics(str) {
        return str.normalize('NFD').replace(/[\u0300-\u036f]/g, '');
    }

    /**
     * Highlight keyword in text.
     */
    function highlightText(text, searchText) {
        if (!searchText) {
            return text;
        }

        var words = searchText.trim().split(/\s+/);
        var result = text;

        words.forEach(function(word) {
            if (word.length > 0) {
                var regex = new RegExp('(' + word.replace(/[.*+?^${}()|[\]\\]/g, '\\$&') + ')', 'gi');
                result = result.replace(regex, '<span class="srs-highlight">$1</span>');
            }
        });

        return result;
    }

    /**
     * Get post type label.
     */
    function getPostTypeLabel(postType) {
        var labels = {
            'post': 'Bài viết',
            'product': 'Sản phẩm',
            'page': 'Trang'
        };
        return labels[postType] || postType;
    }

    /**
     * Build results HTML.
     */
    function buildResultsHTML(grouped, keyword) {
        var html = '';
        var hasResults = false;

        // Prioritize product.
        var sortedTypes = Object.keys(grouped);
        if (sortedTypes.indexOf('product') !== -1) {
            sortedTypes.splice(sortedTypes.indexOf('product'), 1);
            sortedTypes.unshift('product');
        }

        sortedTypes.forEach(function(type) {
            var items = grouped[type];
            if (items.length > 0) {
                hasResults = true;

                var itemsHtml = '';
                items.forEach(function(item) {
                    if (templates.item) {
                        itemsHtml += templates.item(item);
                    }
                });

                if (templates.group) {
                    html += templates.group({
                        label: getPostTypeLabel(type),
                        items: itemsHtml
                    });
                }
            }
        });

        if (!hasResults) {
            if (templates.noResults) {
                html = templates.noResults({
                    message: config.i18n ? config.i18n.noResults : 'No results found'
                });
            }
        }

        return html;
    }

    /**
     * Search and return grouped results.
     */
    function searchInData(keyword, callback) {
        loadSearchData(function(data) {
            var normalizedKeyword = removeDiacritics(keyword.toLowerCase());
            var words = normalizedKeyword.trim().split(/\s+/);
            var limit = config.resultsLimit || 10;

            // Group results by post type.
            var grouped = {};

            data.forEach(function(item) {
                var normalizedTitle = removeDiacritics(item.title.toLowerCase());
                
                // Check if all keywords exist in title.
                var allMatch = words.every(function(word) {
                    return normalizedTitle.indexOf(word) !== -1;
                });

                if (allMatch) {
                    var type = item.type;
                    if (!grouped[type]) {
                        grouped[type] = [];
                    }
                    if (grouped[type].length < limit) {
                        item.highlightedTitle = highlightText(item.title, keyword);
                        grouped[type].push(item);
                    }
                }
            });

            callback(grouped, keyword);
        });
    }

    // ==========================================
    // POPUP MODE
    // ==========================================

    function openPopup() {
        var els = getPopupElements();
        log('Opening popup');
        
        if (els.overlay) {
            els.overlay.style.display = 'flex';
            els.overlay.offsetHeight; // Trigger reflow
            els.overlay.classList.add('is-active');
            
            if (els.input) {
                els.input.focus();
            }
        }
    }

    function closePopup() {
        var els = getPopupElements();
        log('Closing popup');
        
        if (els.overlay) {
            els.overlay.classList.remove('is-active');
            setTimeout(function() {
                els.overlay.style.display = 'none';
                if (els.results) {
                    els.results.innerHTML = '';
                }
                if (els.input) {
                    els.input.value = '';
                }
            }, 300);
        }
    }

    function performPopupSearch(keyword) {
        var els = getPopupElements();
        
        if (!keyword || keyword.length < 1) {
            if (els.results) {
                els.results.innerHTML = '';
            }
            return;
        }

        log('Popup searching for:', keyword);

        searchInData(keyword, function(grouped, kw) {
            var html = buildResultsHTML(grouped, kw);
            if (els.results) {
                els.results.innerHTML = html;
            }
        });
    }

    function initPopupMode() {
        log('Initializing POPUP mode');
        var els = getPopupElements();

        // Close on close button click.
        if (els.closeBtn) {
            els.closeBtn.addEventListener('click', closePopup);
        }

        // Close on overlay click.
        if (els.overlay) {
            els.overlay.addEventListener('click', function(e) {
                if (e.target === els.overlay) {
                    closePopup();
                }
            });
        }

        // Close on ESC.
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closePopup();
            }
        });

        // Search on input.
        if (els.input) {
            els.input.addEventListener('input', function() {
                var keyword = this.value.trim();
                clearTimeout(debounceTimer);
                debounceTimer = setTimeout(function() {
                    performPopupSearch(keyword);
                }, 150);
            });
        }

        // Focus on search inputs outside -> open popup
        attachPopupTriggers();
    }

    function attachPopupTriggers() {
        var selectors = [
            'input[name="s"]',
            'input[type="search"]',
            '.search-field',
            '.ast-search-input',
            'form[role="search"] input[type="text"]'
        ];

        function attachToInput(input) {
            if (input.hasAttribute('data-srs-popup-attached')) return;
            if (input.id === 'srs-search-input') return;
            
            input.setAttribute('data-srs-popup-attached', 'true');
            log('Attaching popup trigger to:', input);

            input.addEventListener('focus', function() {
                var value = this.value;
                var els = getPopupElements();
                
                openPopup();
                
                if (els.input && value) {
                    els.input.value = value;
                    performPopupSearch(value);
                }
            });
        }

        selectors.forEach(function(selector) {
            document.querySelectorAll(selector).forEach(attachToInput);
        });

        // Observer for dynamic elements
        if (typeof MutationObserver !== 'undefined') {
            var observer = new MutationObserver(function() {
                selectors.forEach(function(selector) {
                    document.querySelectorAll(selector).forEach(attachToInput);
                });
            });
            observer.observe(document.body, { childList: true, subtree: true });
        }
    }

    // ==========================================
    // INLINE MODE
    // ==========================================

    function createInlineDropdown(inputEl) {
        // Check if dropdown exists
        var existingDropdown = inputEl.parentElement.querySelector('.srs-inline-dropdown');
        if (existingDropdown) {
            return existingDropdown;
        }

        var dropdown = document.createElement('div');
        dropdown.className = 'srs-inline-dropdown';
        dropdown.style.display = 'none';
        
        // Add to input container
        var container = inputEl.closest('form') || inputEl.parentElement;
        container.style.position = 'relative';
        container.appendChild(dropdown);
        
        log('Created inline dropdown for:', inputEl);
        return dropdown;
    }

    function showInlineDropdown(dropdown, html) {
        dropdown.innerHTML = html;
        dropdown.style.display = 'block';
        activeInlineDropdown = dropdown;
    }

    function hideInlineDropdown(dropdown) {
        if (dropdown) {
            dropdown.style.display = 'none';
            dropdown.innerHTML = '';
        }
        if (activeInlineDropdown === dropdown) {
            activeInlineDropdown = null;
        }
    }

    function hideAllInlineDropdowns() {
        document.querySelectorAll('.srs-inline-dropdown').forEach(function(dropdown) {
            hideInlineDropdown(dropdown);
        });
    }

    function performInlineSearch(inputEl, dropdown) {
        var keyword = inputEl.value.trim();
        
        if (!keyword || keyword.length < 1) {
            hideInlineDropdown(dropdown);
            return;
        }

        log('Inline searching for:', keyword);

        searchInData(keyword, function(grouped, kw) {
            var html = buildResultsHTML(grouped, kw);
            showInlineDropdown(dropdown, html);
        });
    }

    function initInlineMode() {
        log('Initializing INLINE mode');

        var selectors = [
            'input[name="s"]',
            'input[type="search"]',
            '.search-field',
            '.ast-search-input',
            'form[role="search"] input[type="text"]'
        ];

        function attachToInput(input) {
            if (input.hasAttribute('data-srs-inline-attached')) return;
            if (input.id === 'srs-search-input') return;
            
            input.setAttribute('data-srs-inline-attached', 'true');
            
            var dropdown = createInlineDropdown(input);
            log('Attaching inline handler to:', input);

            // On input -> search
            input.addEventListener('input', function() {
                var el = this;
                clearTimeout(debounceTimer);
                debounceTimer = setTimeout(function() {
                    performInlineSearch(el, dropdown);
                }, 150);
            });

            // On focus -> search if text exists
            input.addEventListener('focus', function() {
                if (this.value.trim().length > 0) {
                    performInlineSearch(this, dropdown);
                }
            });

            // On blur -> hide dropdown (delay to allow click)
            input.addEventListener('blur', function() {
                setTimeout(function() {
                    hideInlineDropdown(dropdown);
                }, 200);
            });
        }

        selectors.forEach(function(selector) {
            document.querySelectorAll(selector).forEach(attachToInput);
        });

        // Observer for dynamic elements
        if (typeof MutationObserver !== 'undefined') {
            var observer = new MutationObserver(function() {
                selectors.forEach(function(selector) {
                    document.querySelectorAll(selector).forEach(attachToInput);
                });
            });
            observer.observe(document.body, { childList: true, subtree: true });
        }

        // Hide when clicking outside
        document.addEventListener('click', function(e) {
            if (!e.target.closest('.srs-inline-dropdown') && !e.target.matches('input[name="s"], input[type="search"], .search-field, .ast-search-input')) {
                hideAllInlineDropdowns();
            }
        });

        // Hide on ESC
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                hideAllInlineDropdowns();
            }
        });
    }

    // ==========================================
    // INIT
    // ==========================================

    function init() {
        log('Initializing Simple Realtime Search');
        log('Config:', config);
        log('Display Mode:', config.displayMode || 'popup');
        
        initTemplates();

        if (config.displayMode === 'inline') {
            initInlineMode();
        } else {
            initPopupMode();
        }
    }

    // DOM Ready.
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    // Export for debug.
    window.srsJsonSearch = {
        openPopup: openPopup,
        closePopup: closePopup,
        hideAllInlineDropdowns: hideAllInlineDropdowns,
        config: config
    };

})();
