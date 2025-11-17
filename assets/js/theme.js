(function () {
  'use strict';

  var STORAGE_KEY = 'ds_theme';

  function applyTheme(theme) {
    var root = document.documentElement;
    if (theme === 'dark') {
      root.setAttribute('data-theme', 'dark');
    } else {
      root.setAttribute('data-theme', 'light');
    }
  }

  function getCurrentTheme() {
    var root = document.documentElement;
    var t = root.getAttribute('data-theme');
    if (t === 'dark' || t === 'light') {
      return t;
    }
    return 'light';
  }

  function syncToggleLabels(theme) {
    var nodes = document.querySelectorAll('[data-theme-toggle-label]');
    for (var i = 0; i < nodes.length; i++) {
      nodes[i].textContent = theme === 'dark' ? 'Dark' : 'Light';
    }
  }

  function setTheme(theme) {
    applyTheme(theme);
    try {
      window.localStorage.setItem(STORAGE_KEY, theme);
    } catch (e) {}
    syncToggleLabels(theme);
  }

  function initTheme() {
    var stored = null;
    try {
      stored = window.localStorage.getItem(STORAGE_KEY);
    } catch (e) {}
    var theme = stored === 'dark' ? 'dark' : 'light';
    applyTheme(theme);
    syncToggleLabels(theme);

    var toggles = document.querySelectorAll('[data-theme-toggle]');
    for (var i = 0; i < toggles.length; i++) {
      toggles[i].addEventListener('click', function () {
        var next = getCurrentTheme() === 'dark' ? 'light' : 'dark';
        setTheme(next);
      });
    }
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initTheme);
  } else {
    initTheme();
  }

  window.ThemeSwitcher = {
    setTheme: setTheme,
    applyTheme: applyTheme
  };
})();
