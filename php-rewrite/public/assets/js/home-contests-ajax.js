(function () {
  'use strict';

  var cfg = typeof window.IRO_HOME_AJAX === 'object' && window.IRO_HOME_AJAX ? window.IRO_HOME_AJAX : null;
  if (!cfg || !cfg.partialUrl || !cfg.homePath) {
    return;
  }

  var root = document.getElementById('iro-home-contests-root');
  if (!root) {
    return;
  }

  var activeFetch = null;
  var loadGeneration = 0;

  function buildListUrl(search) {
    var q = search || '';
    if (q.charAt(0) === '?') {
      q = q.slice(1);
    }
    return cfg.partialUrl.replace(/\/?$/, '/') + (q ? '?' + q : '');
  }

  function buildPageUrl(canonicalQuery) {
    var q = canonicalQuery || '';
    if (q.charAt(0) === '?') {
      q = q.slice(1);
    }
    var home = String(cfg.homePath || '/');
    return home.replace(/\/?$/, '/') + (q ? '?' + q : '');
  }

  function syncHeroFromCanonical(canonicalQuery) {
    var params = new URLSearchParams(canonicalQuery ? canonicalQuery : '');
    var form = document.querySelector('.iro-hero .iro-search');
    if (!form) {
      return;
    }
    var qInput = form.querySelector('input[name="q"]');
    if (qInput) {
      qInput.value = params.get('q') || '';
    }
    var viewIn = form.querySelector('input[name="view"]');
    if (params.get('view') === 'list') {
      if (!viewIn) {
        viewIn = document.createElement('input');
        viewIn.type = 'hidden';
        viewIn.name = 'view';
        form.appendChild(viewIn);
      }
      viewIn.value = 'list';
    } else if (viewIn) {
      viewIn.remove();
    }
    var phaseIn = form.querySelector('input[name="phase"]');
    var phaseVal = params.get('phase') || '';
    if (phaseVal) {
      if (!phaseIn) {
        phaseIn = document.createElement('input');
        phaseIn.type = 'hidden';
        phaseIn.name = 'phase';
        form.appendChild(phaseIn);
      }
      phaseIn.value = phaseVal;
    } else if (phaseIn) {
      phaseIn.remove();
    }

    var phaseLinks = document.querySelectorAll('.iro-hero .iro-tags a.iro-home-ajax[data-phase]');
    for (var li = 0; li < phaseLinks.length; li++) {
      var link = phaseLinks[li];
      if (link.classList.contains('iro-home-reset')) {
        continue;
      }
      var code = link.getAttribute('data-phase');
      if (!code) {
        continue;
      }
      var ph = new URLSearchParams();
      var qTrim = (params.get('q') || '').trim();
      if (qTrim) {
        ph.set('q', qTrim);
      }
      if (params.get('view') === 'list') {
        ph.set('view', 'list');
      }
      ph.set('phase', code);
      link.setAttribute('href', '?' + ph.toString());
    }

    var tags = document.querySelectorAll('.iro-hero .iro-tags .iro-tag[data-phase]');
    for (var i = 0; i < tags.length; i++) {
      var t = tags[i];
      var p = t.getAttribute('data-phase') || '';
      if (p && p === phaseVal) {
        t.classList.add('is-active');
      } else if (p) {
        t.classList.remove('is-active');
      }
    }

    var resetLink = document.querySelector('.iro-hero .iro-home-reset');
    if (resetLink) {
      var showReset = !!(params.get('phase') || (params.get('q') || '').trim() || params.get('view') === 'list');
      resetLink.classList.toggle('d-none', !showReset);
      var base = String(cfg.homePath || '/').replace(/\/?$/, '/');
      if (params.get('view') === 'list') {
        resetLink.href = base + '?view=list';
      } else {
        resetLink.href = base;
      }
    }
  }

  function setLoading(on) {
    root.classList.toggle('iro-home-contests-root--loading', on);
    root.setAttribute('aria-busy', on ? 'true' : 'false');
  }

  function clearMinHeightWhenSettled(gen) {
    window.requestAnimationFrame(function () {
      window.requestAnimationFrame(function () {
        if (gen === loadGeneration) {
          root.style.minHeight = '';
        }
      });
    });
  }

  function loadPartial(search, historyMode) {
    if (activeFetch) {
      activeFetch.abort();
      activeFetch = null;
    }

    var gen = ++loadGeneration;
    activeFetch = new AbortController();

    var prevH = root.offsetHeight;
    if (prevH > 64) {
      root.style.minHeight = prevH + 'px';
    }

    var listUrl = buildListUrl(search);
    setLoading(true);

    window
      .fetch(listUrl, {
        method: 'GET',
        credentials: 'same-origin',
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        signal: activeFetch.signal,
      })
      .then(function (res) {
        if (!res.ok) {
          throw new Error('HTTP ' + res.status);
        }
        var cq = res.headers.get('X-IRO-Canonical-Query');
        if (cq === null) {
          cq = search && search.charAt(0) === '?' ? search.slice(1) : (search || '');
        }
        return res.text().then(function (text) {
          return { text: text, canonicalQuery: cq || '' };
        });
      })
      .then(function (data) {
        if (gen !== loadGeneration) {
          return;
        }
        root.innerHTML = data.text;
        if (historyMode === 'push' && window.history && window.history.pushState) {
          window.history.pushState({ iroHomeAjax: true }, '', buildPageUrl(data.canonicalQuery));
        }
        syncHeroFromCanonical(data.canonicalQuery);

        var qs = (data.canonicalQuery || '').replace(/^\?/, '');
        if (/(^|&)page=/.test(qs)) {
          try {
            root.scrollIntoView({ block: 'start', behavior: 'smooth' });
          } catch (e) {
            root.scrollIntoView(true);
          }
        }
      })
      .catch(function (err) {
        if (err.name === 'AbortError') {
          return;
        }
        var raw = search || '';
        if (raw.charAt(0) === '?') {
          raw = raw.slice(1);
        }
        window.location.assign(buildPageUrl(raw));
      })
      .finally(function () {
        if (gen !== loadGeneration) {
          return;
        }
        activeFetch = null;
        setLoading(false);
        clearMinHeightWhenSettled(gen);
      });
  }

  document.addEventListener('click', function (e) {
    var a = e.target.closest('a');
    if (!a || !a.classList.contains('iro-home-ajax')) {
      return;
    }
    if (e.defaultPrevented) {
      return;
    }
    if (e.button !== 0 || e.ctrlKey || e.metaKey || e.shiftKey || e.altKey) {
      return;
    }
    if (a.getAttribute('target') === '_blank') {
      return;
    }
    var href = a.getAttribute('href');
    if (!href || href.indexOf('#') === 0) {
      return;
    }
    var u;
    try {
      u = new URL(a.href, window.location.origin);
    } catch (err) {
      return;
    }
    if (u.origin !== window.location.origin) {
      return;
    }
    function normPath(p) {
      if (!p || p === '/') {
        return '/';
      }
      return p.replace(/\/+$/, '') || '/';
    }
    var path = normPath(u.pathname);
    var home = normPath(String(cfg.homePath || '/'));
    if (path !== home) {
      return;
    }
    e.preventDefault();
    loadPartial(u.search, 'push');
  });

  var heroForm = document.querySelector('.iro-hero .iro-search');
  if (heroForm) {
    heroForm.addEventListener('submit', function (e) {
      e.preventDefault();
      var fd = new FormData(heroForm);
      var params = new URLSearchParams();
      var q = (fd.get('q') || '').toString().trim();
      if (q) {
        params.set('q', q);
      }
      if (fd.get('phase')) {
        params.set('phase', fd.get('phase').toString());
      }
      if (fd.get('view') === 'list') {
        params.set('view', 'list');
      }
      var qs = params.toString();
      loadPartial(qs ? '?' + qs : '', 'push');
    });
  }

  window.addEventListener('popstate', function () {
    loadPartial(window.location.search, 'none');
  });
})();
