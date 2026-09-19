<div class="ft-topbar-search" data-ft-topbar-search>
    <svg class="ft-topbar-search-icon" viewBox="0 0 20 20" fill="none" aria-hidden="true">
        <path d="M8.5 14.5a6 6 0 1 1 0-12 6 6 0 0 1 0 12Z" stroke="currentColor" stroke-width="1.6"/>
        <path d="M13.2 13.2 17 17" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/>
    </svg>
    <input
        type="search"
        class="ft-topbar-search-input"
        placeholder="Search"
        autocomplete="off"
        aria-label="Search"
    >
</div>
<script>
    (function () {
        var root = document.querySelector('.ft-topbar-search');
        if (!root || root.dataset.ftBound === '1') {
            return;
        }
        root.dataset.ftBound = '1';
        var input = root.querySelector('.ft-topbar-search-input');
        if (!input) {
            return;
        }

        function tableSearchInput() {
            return document.querySelector('.fi-ta-search-field input, .fi-ta-search input');
        }

        function sync(focusTable) {
            var tableInput = tableSearchInput();
            if (!tableInput) {
                return;
            }
            var value = input.value;
            var proto = window.HTMLInputElement ? window.HTMLInputElement.prototype : null;
            var setter = proto && Object.getOwnPropertyDescriptor(proto, 'value');
            if (setter && setter.set) {
                setter.set.call(tableInput, value);
            } else {
                tableInput.value = value;
            }
            tableInput.dispatchEvent(new Event('input', { bubbles: true }));
            tableInput.dispatchEvent(new Event('change', { bubbles: true }));
            if (focusTable) {
                tableInput.focus();
            }
        }

        input.addEventListener('input', function () {
            sync(false);
        });
        input.addEventListener('keydown', function (event) {
            if (event.key === 'Enter') {
                event.preventDefault();
                sync(true);
            }
        });
    })();
</script>
