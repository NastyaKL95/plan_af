/**
 * v9 — десктоп: свёрнутый сайдбар (иконки), переключатель и localStorage audit-fund-sidebar-expanded.
 * v8 — модалки: закрытие по клику/Escape даже если на странице нет data-modal-open (расписание).
 * v7 — мобильная навигация: гамбургер, выезжающий сайдбар, затемнение, Escape и клик по фону.
 * v6 — убрана неиспользуемая демо-навигация по неделям (неделя задаётся на сервере в schedule.php).
 * v5 — тема: Авто / Светлая / Тёмная (localStorage, слушатель prefers-color-scheme).
 * v4 — модалки: Escape, ловушка Tab, возврат фокуса на кнопку открытия.
 * v3 — форма «Добавить аудиторию» отправляется на сервер (room_add.php), без preventDefault в JS.
 *
 * После загрузки HTML добавляем класс is-visible ко всем блокам с animate-on-load.
 * В CSS у этих блоков заданы opacity: 0 и translateY до появления класса —
 * браузер плавно показывает контент (лёгкая анимация «снизу вверх»).
 */
(function () {
  "use strict";

  function setupScheduleViewToggle() {
    var toggles = document.querySelectorAll("button.view-toggle__btn[data-view]");
    if (!toggles.length) return;

    var views = document.querySelectorAll(".schedule-view[data-schedule-view]");
    if (!views.length) return;

    function setView(viewName) {
      views.forEach(function (el) {
        var isTarget = el.getAttribute("data-schedule-view") === viewName;
        el.classList.toggle("is-hidden", !isTarget);
      });

      toggles.forEach(function (btn) {
        var isActive = btn.getAttribute("data-view") === viewName;
        btn.classList.toggle("is-active", isActive);
        btn.setAttribute("aria-selected", isActive ? "true" : "false");
      });
    }

    toggles.forEach(function (btn) {
      btn.addEventListener("click", function () {
        setView(btn.getAttribute("data-view"));
      });
    });
  }

  var THEME_KEY = "audit-fund-theme";

  function applyThemeFromStorage() {
    var pref = localStorage.getItem(THEME_KEY);
    if (pref !== "light" && pref !== "dark" && pref !== "auto") {
      pref = "auto";
    }
    var dark =
      pref === "dark" ||
      (pref === "auto" &&
        window.matchMedia &&
        window.matchMedia("(prefers-color-scheme: dark)").matches);
    document.documentElement.setAttribute("data-theme", dark ? "dark" : "light");
    document.documentElement.setAttribute("data-theme-pref", pref);

    document.querySelectorAll(".theme-toggle__btn[data-theme-pref]").forEach(function (btn) {
      var isSel = btn.getAttribute("data-theme-pref") === pref;
      btn.setAttribute("aria-pressed", isSel ? "true" : "false");
      btn.classList.toggle("is-active", isSel);
    });
  }

  function setupThemeToggle() {
    if (!document.querySelector(".theme-toggle")) {
      return;
    }

    applyThemeFromStorage();

    document.addEventListener("click", function (e) {
      var btn = e.target.closest(".theme-toggle__btn");
      if (!btn) {
        return;
      }
      var pref = btn.getAttribute("data-theme-pref");
      if (pref !== "light" && pref !== "dark" && pref !== "auto") {
        return;
      }
      try {
        localStorage.setItem(THEME_KEY, pref);
      } catch (err) {}
      applyThemeFromStorage();
    });

    var mq = window.matchMedia("(prefers-color-scheme: dark)");
    function onSchemeChange() {
      var p = localStorage.getItem(THEME_KEY) || "auto";
      if (p === "auto") {
        applyThemeFromStorage();
      }
    }
    if (mq.addEventListener) {
      mq.addEventListener("change", onSchemeChange);
    } else if (mq.addListener) {
      mq.addListener(onSchemeChange);
    }

    window.addEventListener("storage", function (e) {
      if (e.key === THEME_KEY) {
        applyThemeFromStorage();
      }
    });
  }

  function setupMobileNav() {
    var toggle = document.getElementById("app-mobile-nav-toggle");
    var backdrop = document.getElementById("app-mobile-nav-backdrop");
    var sidebar = document.getElementById("app-sidebar");
    if (!toggle || !backdrop || !sidebar) {
      return;
    }

    var mq = window.matchMedia("(max-width: 960px)");

    function applyDesktopState() {
      document.documentElement.classList.remove("app--nav-open");
      toggle.setAttribute("aria-expanded", "false");
      toggle.setAttribute("aria-label", "Открыть меню разделов");
      backdrop.setAttribute("aria-hidden", "true");
      sidebar.removeAttribute("aria-hidden");
    }

    function applyMobileClosedState() {
      toggle.setAttribute("aria-expanded", "false");
      toggle.setAttribute("aria-label", "Открыть меню разделов");
      backdrop.setAttribute("aria-hidden", "true");
      sidebar.setAttribute("aria-hidden", "true");
    }

    function setOpen(open) {
      if (!mq.matches) {
        return;
      }
      document.documentElement.classList.toggle("app--nav-open", open);
      toggle.setAttribute("aria-expanded", open ? "true" : "false");
      toggle.setAttribute("aria-label", open ? "Закрыть меню разделов" : "Открыть меню разделов");
      backdrop.setAttribute("aria-hidden", open ? "false" : "true");
      sidebar.setAttribute("aria-hidden", open ? "false" : "true");
      if (open) {
        var first = sidebar.querySelector("a");
        window.setTimeout(function () {
          if (first && typeof first.focus === "function") {
            first.focus();
          }
        }, 200);
      } else {
        window.setTimeout(function () {
          if (toggle && typeof toggle.focus === "function") {
            toggle.focus();
          }
        }, 0);
      }
    }

    function close() {
      if (mq.matches && document.documentElement.classList.contains("app--nav-open")) {
        setOpen(false);
      }
    }

    function onMqChange() {
      if (mq.matches) {
        var open = document.documentElement.classList.contains("app--nav-open");
        if (open) {
          toggle.setAttribute("aria-expanded", "true");
          toggle.setAttribute("aria-label", "Закрыть меню разделов");
          backdrop.setAttribute("aria-hidden", "false");
          sidebar.setAttribute("aria-hidden", "false");
        } else {
          applyMobileClosedState();
        }
      } else {
        applyDesktopState();
      }
    }

    toggle.addEventListener("click", function () {
      if (!mq.matches) {
        return;
      }
      setOpen(!document.documentElement.classList.contains("app--nav-open"));
    });

    backdrop.addEventListener("click", close);

    document.addEventListener("keydown", function (e) {
      if (e.key === "Escape") {
        close();
      }
    });

    sidebar.querySelectorAll("a").forEach(function (link) {
      link.addEventListener("click", function () {
        if (mq.matches) {
          document.documentElement.classList.remove("app--nav-open");
          applyMobileClosedState();
        }
      });
    });

    if (mq.addEventListener) {
      mq.addEventListener("change", onMqChange);
    } else if (mq.addListener) {
      mq.addListener(onMqChange);
    }
    onMqChange();
  }

  var SIDEBAR_EXPANDED_KEY = "audit-fund-sidebar-expanded";

  function setupSidebarRail() {
    var toggle = document.getElementById("app-sidebar-rail-toggle");
    var root = document.documentElement;
    if (!toggle) {
      return;
    }

    var mq = window.matchMedia("(min-width: 961px)");

    function isExpandedPreferred() {
      try {
        var v = localStorage.getItem(SIDEBAR_EXPANDED_KEY);
        if (v === "1") {
          return true;
        }
        if (v === "0") {
          return false;
        }
      } catch (err) {}
      return false;
    }

    function applyRailState() {
      if (!mq.matches) {
        root.classList.remove("app--sidebar-collapsed");
        return;
      }
      var expanded = isExpandedPreferred();
      root.classList.toggle("app--sidebar-collapsed", !expanded);
      toggle.setAttribute("aria-expanded", expanded ? "true" : "false");
      toggle.setAttribute(
        "title",
        expanded ? "Свернуть меню (только иконки)" : "Развернуть меню с подписями"
      );
      toggle.setAttribute(
        "aria-label",
        expanded ? "Свернуть боковое меню" : "Развернуть боковое меню"
      );
    }

    toggle.addEventListener("click", function () {
      if (!mq.matches) {
        return;
      }
      var willExpand = root.classList.contains("app--sidebar-collapsed");
      try {
        localStorage.setItem(SIDEBAR_EXPANDED_KEY, willExpand ? "1" : "0");
      } catch (err) {}
      applyRailState();
    });

    if (mq.addEventListener) {
      mq.addEventListener("change", applyRailState);
    } else if (mq.addListener) {
      mq.addListener(applyRailState);
    }

    applyRailState();
  }

  function setupModal() {
    var openers = document.querySelectorAll("[data-modal-open]");

    var activeModal = null;
    var lastFocusEl = null;

    function getFocusable(container) {
      if (!container) return [];
      var sel =
        'a[href], button:not([disabled]), textarea:not([disabled]), input:not([disabled]), select:not([disabled]), [tabindex]:not([tabindex="-1"])';
      return Array.prototype.filter.call(container.querySelectorAll(sel), function (el) {
        if (el.getAttribute("aria-hidden") === "true") return false;
        if (el.closest("[aria-hidden=\"true\"]")) return false;
        var rect = el.getBoundingClientRect();
        return rect.width > 0 && rect.height > 0;
      });
    }

    function onTabTrap(e) {
      if (e.key !== "Tab" || !activeModal) return;
      var dialog = activeModal.querySelector(".modal__dialog");
      if (!dialog) return;
      var list = getFocusable(dialog);
      if (list.length < 2) return;
      var first = list[0];
      var last = list[list.length - 1];
      if (e.shiftKey) {
        if (document.activeElement === first) {
          e.preventDefault();
          last.focus();
        }
      } else if (document.activeElement === last) {
        e.preventDefault();
        first.focus();
      }
    }

    function openModal(modal, opener) {
      if (!modal) return;
      lastFocusEl = opener && typeof opener.focus === "function" ? opener : document.activeElement;
      activeModal = modal;
      modal.classList.remove("is-hidden");

      var dialog = modal.querySelector(".modal__dialog");
      var list = dialog ? getFocusable(dialog) : [];

      window.setTimeout(function () {
        if (list.length > 0) {
          list[0].focus();
        } else if (dialog) {
          dialog.setAttribute("tabindex", "-1");
          dialog.focus();
        }
      }, 0);

      document.addEventListener("keydown", onTabTrap, true);
    }

    function closeModal(modal) {
      if (!modal) return;
      modal.classList.add("is-hidden");
      document.removeEventListener("keydown", onTabTrap, true);
      var wasOpenedViaOpener = activeModal === modal;
      if (wasOpenedViaOpener) {
        activeModal = null;
      }
      var back = wasOpenedViaOpener ? lastFocusEl : null;
      if (wasOpenedViaOpener) {
        lastFocusEl = null;
      }
      if (back && typeof back.focus === "function") {
        window.setTimeout(function () {
          back.focus();
        }, 0);
      }
    }

    function onEscapeAnyModal(e) {
      if (e.key !== "Escape") return;
      var openList = document.querySelectorAll(".modal:not(.is-hidden)");
      if (!openList.length) return;
      var topModal = openList[openList.length - 1];
      closeModal(topModal);
    }

    document.addEventListener("keydown", onEscapeAnyModal, true);

    openers.forEach(function (btn) {
      btn.addEventListener("click", function () {
        var modalName = btn.getAttribute("data-modal-open");
        var modal = document.getElementById("modal-" + modalName);
        openModal(modal, btn);
      });
    });

    window.auditFundOpenModal = function (modalId, openerEl) {
      var modal = typeof modalId === "string" ? document.getElementById(modalId) : modalId;
      openModal(modal, openerEl || null);
    };

    document.addEventListener("click", function (e) {
      var closer = e.target.closest("[data-modal-close]");
      if (!closer) return;
      var modal = e.target.closest(".modal");
      closeModal(modal);
    });
  }

  function showAnimatedBlocks() {
    var blocks = document.querySelectorAll(".animate-on-load");

    // requestAnimationFrame откладывает смену класса на кадр отрисовки —
    // так переход из начального состояния в конечное гарантированно анимируется.
    window.requestAnimationFrame(function () {
      blocks.forEach(function (el) {
        el.classList.add("is-visible");
      });
    });
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", function () {
      showAnimatedBlocks();
      setupThemeToggle();
      setupMobileNav();
      setupSidebarRail();
      setupScheduleViewToggle();
      setupModal();
    });
  } else {
    showAnimatedBlocks();
    setupThemeToggle();
    setupMobileNav();
    setupSidebarRail();
    setupScheduleViewToggle();
    setupModal();
  }
})();
