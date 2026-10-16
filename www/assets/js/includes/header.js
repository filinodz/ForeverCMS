(() => {
  'use strict';
  const initialize = () => {
    const toggle = document.querySelector('.nav-toggle');
    const close = document.querySelector('.nav-close');
    const nav = document.querySelector('header nav');
    const hamburger = toggle?.querySelector('.hamburger');
    const profileButton = document.getElementById('profileToggle');
    const profileMenu = document.getElementById('dropdownMenu');
    const languageButton = document.getElementById('langSelected');
    const languageMenu = document.getElementById('langOptions');
    const dropdown = (button, menu, open) => {
      menu?.classList.toggle('show', open);
      button?.setAttribute('aria-expanded', String(open));
    };
    const mobile = open => {
      nav?.classList.toggle('nav-open', open);
      hamburger?.classList.toggle('active', open);
      toggle?.setAttribute('aria-expanded', String(open));
    };
    toggle?.addEventListener('click', () => mobile(!nav?.classList.contains('nav-open')));
    close?.addEventListener('click', () => mobile(false));
    nav?.querySelectorAll('a').forEach(link => link.addEventListener('click', () => mobile(false)));
    profileButton?.addEventListener('click', event => {
      event.stopPropagation();
      dropdown(profileButton, profileMenu, !profileMenu?.classList.contains('show'));
      dropdown(languageButton, languageMenu, false);
    });
    languageButton?.addEventListener('click', event => {
      event.stopPropagation();
      dropdown(languageButton, languageMenu, !languageMenu?.classList.contains('show'));
      dropdown(profileButton, profileMenu, false);
    });
    languageMenu?.querySelectorAll('li[data-value]').forEach(option => {
      option.addEventListener('click', event => {
        event.stopPropagation();
        const language = option.dataset.value;
        if (!language || !/^[a-z]{2}(?:-[a-zA-Z]{2})?$/.test(language)) return;
        event.preventDefault();
        const url = new URL(window.location.href);
        url.searchParams.set('lang', language);
        window.location.assign(url.href);
      });
    });
    document.addEventListener('click', event => {
      if (!(event.target instanceof Element)) return;
      if (!event.target.closest('#langDropdownContainer')) dropdown(languageButton, languageMenu, false);
      if (!event.target.closest('#profileDropdownContainer')) dropdown(profileButton, profileMenu, false);
    });
    document.addEventListener('keydown', event => {
      if (event.key !== 'Escape') return;
      const languageOpen = languageMenu?.classList.contains('show');
      dropdown(languageButton, languageMenu, false);
      dropdown(profileButton, profileMenu, false);
      mobile(false);
      if (languageOpen) languageButton?.focus();
    });
    window.matchMedia('(min-width: 1400px)').addEventListener('change', event => {
      if (event.matches) mobile(false);
      dropdown(profileButton, profileMenu, false);
      dropdown(languageButton, languageMenu, false);
    });
  };
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', initialize, {once: true});
  else initialize();
})();
