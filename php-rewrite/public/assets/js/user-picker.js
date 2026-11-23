/**
 * Выбор пользователей с поиском и выпадающим списком (модераторы/пул/эксперты этапа).
 * Пустой запрос: первые записи по алфавиту — кликните в поле, не вводя текст.
 */
(function () {
    'use strict';

    function debounce(fn, ms) {
        var t = null;
        return function () {
            var ctx = this;
            var args = arguments;
            clearTimeout(t);
            t = setTimeout(function () {
                fn.apply(ctx, args);
            }, ms);
        };
    }

    function esc(s) {
        var d = document.createElement('div');
        d.textContent = s;
        return d.innerHTML;
    }

    function initRoot(root) {
        var role = root.getAttribute('data-picker-role') || 'expert';
        var inputName = root.getAttribute('data-picker-name') || 'expert_ids[]';
        var contestId = root.getAttribute('data-picker-contest-id') || '0';
        var forStage = root.getAttribute('data-picker-for-stage') === '1';
        var initialRaw = root.getAttribute('data-picker-initial') || '[]';
        var initial;
        try {
            initial = JSON.parse(initialRaw);
        } catch (e) {
            initial = [];
        }
        if (!Array.isArray(initial)) {
            initial = [];
        }

        var chipsEl = root.querySelector('.iro-user-picker-chips');
        var hiddensEl = root.querySelector('.iro-user-picker-hiddens');
        var searchEl = root.querySelector('.iro-user-picker-search');
        var dropEl = root.querySelector('.iro-user-picker-dropdown');
        if (!chipsEl || !hiddensEl || !searchEl || !dropEl) {
            return;
        }

        var selected = [];
        initial.forEach(function (row) {
            if (row && typeof row.id === 'number' && row.label) {
                selected.push({ id: row.id, label: String(row.label) });
            } else if (row && row.id != null && row.label) {
                selected.push({ id: parseInt(String(row.id), 10), label: String(row.label) });
            }
        });

        function selectedIds() {
            return selected.map(function (s) {
                return s.id;
            });
        }

        function syncHiddens() {
            hiddensEl.innerHTML = '';
            selected.forEach(function (s) {
                var inp = document.createElement('input');
                inp.type = 'hidden';
                inp.name = inputName;
                inp.value = String(s.id);
                hiddensEl.appendChild(inp);
            });
        }

        function renderChips() {
            chipsEl.innerHTML = '';
            selected.forEach(function (s) {
                var wrap = document.createElement('span');
                wrap.className = 'iro-user-picker-chip';
                wrap.innerHTML =
                    '<span class="iro-user-picker-chip-text">' +
                    esc(s.label) +
                    '</span>' +
                    '<button type="button" class="iro-user-picker-remove" data-id="' +
                    s.id +
                    '" aria-label="Удалить">&times;</button>';
                chipsEl.appendChild(wrap);
            });
            syncHiddens();
        }

        var idToLabel = {};

        function closeDrop() {
            dropEl.hidden = true;
            dropEl.innerHTML = '';
        }

        function openDrop(html) {
            dropEl.innerHTML = html;
            dropEl.hidden = !html;
        }

        function fetchUsers(q, cb) {
            var params = new URLSearchParams();
            params.set('role', role);
            params.set('q', q || '');
            if (contestId && contestId !== '0') {
                params.set('contest_id', contestId);
            }
            if (forStage) {
                params.set('for_stage', '1');
            }
            fetch('/admin/api/user-picker?' + params.toString(), {
                headers: { Accept: 'application/json' },
                credentials: 'same-origin',
            })
                .then(function (r) {
                    return r.json();
                })
                .then(function (data) {
                    var users = data && data.users ? data.users : [];
                    cb(users);
                })
                .catch(function () {
                    cb([]);
                });
        }

        function renderList(users) {
            var ids = selectedIds();
            var filtered = users.filter(function (u) {
                return ids.indexOf(u.id) === -1;
            });
            idToLabel = {};
            filtered.forEach(function (u) {
                idToLabel[u.id] = u.label;
            });
            if (!filtered.length) {
                openDrop(
                    '<div class="list-group-item small text-muted">Никого не найдено или все уже выбраны.</div>'
                );
                return;
            }
            var html = filtered
                .map(function (u) {
                    return (
                        '<button type="button" class="list-group-item list-group-item-action iro-user-picker-item small text-start" data-id="' +
                        u.id +
                        '">' +
                        esc(u.label) +
                        '</button>'
                    );
                })
                .join('');
            openDrop(html);
        }

        var runSearch = debounce(function () {
            var q = (searchEl.value || '').trim();
            fetchUsers(q, function (users) {
                renderList(users);
            });
        }, 220);

        searchEl.addEventListener('focus', function () {
            runSearch();
        });

        searchEl.addEventListener('input', function () {
            runSearch();
        });

        searchEl.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') {
                closeDrop();
                searchEl.blur();
            }
        });

        dropEl.addEventListener('mousedown', function (e) {
            var btn = e.target.closest('.iro-user-picker-item');
            if (!btn) {
                return;
            }
            e.preventDefault();
            var id = parseInt(btn.getAttribute('data-id') || '0', 10);
            var label = idToLabel[id] || '';
            if (!id) {
                return;
            }
            if (selectedIds().indexOf(id) !== -1) {
                return;
            }
            selected.push({ id: id, label: label });
            renderChips();
            searchEl.value = '';
            closeDrop();
        });

        chipsEl.addEventListener('click', function (e) {
            var rm = e.target.closest('.iro-user-picker-remove');
            if (!rm) {
                return;
            }
            var id = parseInt(rm.getAttribute('data-id') || '0', 10);
            selected = selected.filter(function (s) {
                return s.id !== id;
            });
            renderChips();
        });

        document.addEventListener(
            'click',
            function (e) {
                if (!root.contains(e.target)) {
                    closeDrop();
                }
            },
            true
        );

        renderChips();
    }

    function boot() {
        document.querySelectorAll('[data-iro-user-picker]').forEach(function (el) {
            initRoot(el);
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
})();
