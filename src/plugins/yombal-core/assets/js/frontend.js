(function () {
  function ready(fn) {
    if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', fn);
      return;
    }

    fn();
  }

  function addClass(el, className) {
    if (el) {
      el.classList.add(className);
    }
  }

  function addClasses(el, classNames) {
    if (el) {
      el.classList.add.apply(el.classList, classNames);
    }
  }

  function setLinkLabel(link, label) {
    if (!link) {
      return;
    }

    var hasElementChildren = Array.prototype.some.call(link.childNodes, function (node) {
      return node.nodeType === 1;
    });

    if (!hasElementChildren) {
      link.textContent = label;
      return;
    }

    Array.prototype.slice.call(link.childNodes).forEach(function (node) {
      if (node.nodeType === 3) {
        link.removeChild(node);
      }
    });

    link.appendChild(document.createTextNode(' ' + label));
  }

  function decorateHome() {
    if (!document.body.classList.contains('home')) {
      return;
    }

    addClass(document.querySelector('.site-header'), 'y-site-header');
    addClass(document.querySelector('.main-navigation'), 'y-site-nav');
    addClass(document.querySelector('.rek-menu'), 'y-site-menu');
    addClass(document.querySelector('footer'), 'y-site-footer');

    addClass(document.querySelector('.yh2'), 'y-home-hero');
    addClass(document.querySelector('.yh2 .yc'), 'y-home-hero__inner');
    addClass(document.querySelector('.yhero-btns'), 'y-home-hero__actions');

    document.querySelectorAll('.ysec').forEach(function (section, index) {
      addClasses(section, ['y-home-section', 'y-home-section--' + (index + 1)]);
      addClass(section.querySelector('.yc'), 'y-home-shell');
      addClass(section.querySelector('.ygrid4'), 'y-home-grid-steps');
      addClass(section.querySelector('.ygrid2'), 'y-home-grid-split');
    });
  }

  function decorateStaticPage() {
    var article = document.querySelector('body.page article.page');
    if (!article) {
      return;
    }

    addClass(article, 'y-page-article');
    addClass(article.querySelector('.post-header'), 'y-page-header');
    addClass(article.querySelector('.post-content'), 'y-page-content');

    if (article.querySelector('.post-content > .yombal-ui')) {
      addClass(document.body, 'y-has-custom-shell');
      addClass(article, 'y-page-article--custom-shell');
    }
  }

  function decorateGlobalShell() {
    addClass(document.body, 'yombal-js-ready');
    addClass(document.querySelector('#site-header, .site-header'), 'y-site-header');
    addClass(document.querySelector('.main-navigation'), 'y-site-nav');
    addClass(document.querySelector('.rek-menu'), 'y-site-menu');
    addClass(document.querySelector('footer'), 'y-site-footer');

    document.querySelectorAll('.post-content table, .yombal-rich-content table').forEach(function (table) {
      addClass(table, 'yombal-table');
    });

    document.querySelectorAll('.post-content blockquote, .yombal-rich-content blockquote').forEach(function (quote) {
      addClass(quote, 'yombal-quote');
    });

    document.querySelectorAll('.post-content .wp-block-button__link, .post-content .button, .post-content .woocommerce-button').forEach(function (button) {
      addClass(button, 'yombal-inline-button');
    });
  }

  function rewriteLegacyLinks() {
    var loggedIn = document.body.classList.contains('yombal-site--logged-in');
    var accountHref = 'https://yombal.sn/connexion/';
    var accountText = 'Mon compte';
    var partnerHref = 'https://yombal.sn/devenir-partenaire-yombal/';
    var partnerText = 'Devenir partenaire';
    var rewrites = [
      {
        match: /\/devenir-partenaire\/?$/,
        href: 'https://yombal.sn/devenir-partenaire-yombal/',
        text: 'Devenir Partenaire'
      },
      {
        match: /\/devenir-vendeur-tissus\/?$/,
        href: 'https://yombal.sn/devenir-partenaire-yombal/?partner_type=fabric_vendor',
        text: 'Vendre des Tissus'
      },
      {
        match: /\/devenir-tailleur\/?$/,
        href: 'https://yombal.sn/devenir-partenaire-yombal/?partner_type=tailor',
        text: 'Devenir Tailleur'
      },
      {
        match: /\/dashboard-partenaire\/?$/,
        href: 'https://yombal.sn/espace-partenaire-yombal/',
        text: 'Espace partenaire'
      },
      {
        match: /\/mes-messages\/?$/,
        href: 'https://yombal.sn/messages-yombal/',
        text: 'Messages'
      },
      {
        match: /\/modeles\/?$/,
        href: 'https://yombal.sn/catalogue-modeles/',
        text: 'Modèles'
      },
      {
        match: /\/support-tickets\/?$|\/aide-litige\/?$/,
        href: 'https://yombal.sn/litiges-yombal/',
        text: 'Aide et litiges'
      },
    ];

    if (!loggedIn) {
      rewrites.push(
        {
          match: /\/store-manager\/?$/,
          href: partnerHref,
          text: partnerText
        },
        {
          match: /\/mon-compte\/?$/,
          href: accountHref,
          text: accountText
        }
      );
    }

    document.querySelectorAll('a[href]').forEach(function (link) {
      var href = link.getAttribute('href') || '';
      var text = (link.textContent || '').trim();

      if (text.indexOf('Devenir Tailleur') !== -1) {
        link.setAttribute('href', 'https://yombal.sn/devenir-partenaire-yombal/?partner_type=tailor');
        return;
      }

      rewrites.forEach(function (rewrite) {
        if (!rewrite.match.test(href)) {
          return;
        }

        link.setAttribute('href', rewrite.href);
        if (text !== '') {
          setLinkLabel(link, rewrite.text);
        }
      });

    });
  }

  function normalizeCanonicalLabels() {
    document.querySelectorAll('a[href]').forEach(function (link) {
      var href = link.getAttribute('href') || '';
      var text = (link.textContent || '').trim();

      if (/\/catalogue-modeles\/?$/i.test(href) && /mod[eè]les/i.test(text)) {
        setLinkLabel(link, 'Modèles');
      }

      if (/\/espace-partenaire-yombal\/?$/i.test(href) && /dashboard partenaire/i.test(text)) {
        setLinkLabel(link, 'Espace partenaire');
      }

      if (/\/connexion\/?$/i.test(href) && /connexion|mon compte/i.test(text)) {
        setLinkLabel(link, 'Mon compte');
      }
    });
  }

  function bindSiteHeader() {
    document.querySelectorAll('[data-yhr-site-header]').forEach(function (header) {
      var toggle = header.querySelector('.yhr-site-header__toggle');
      var nav = header.querySelector('[data-yhr-site-nav]');
      if (!toggle || !nav) {
        return;
      }

      function closeMenu() {
        header.classList.remove('is-open');
        toggle.setAttribute('aria-expanded', 'false');
      }

      toggle.addEventListener('click', function () {
        var isOpen = header.classList.toggle('is-open');
        toggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
      });

      nav.querySelectorAll('a').forEach(function (link) {
        link.addEventListener('click', closeMenu);
      });

      document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') {
          closeMenu();
        }
      });

      document.addEventListener('click', function (event) {
        if (!header.contains(event.target)) {
          closeMenu();
        }
      });

      window.addEventListener('resize', function () {
        if (window.innerWidth > 980) {
          closeMenu();
        }
      });
    });
  }

  function refinePrimaryNavigation() {
    var homePattern = /^https?:\/\/yombal\.sn\/?$/i;
    var labelRules = [
      { match: /\/catalogue-tailleurs\/?$/i, label: 'Tailleurs' },
      { match: /\/catalogue-tissus\/?$/i, label: 'Tissus' },
      { match: /\/catalogue-modeles\/?$/i, label: 'Modèles' },
      { match: /\/devenir-partenaire-yombal\/?(?:\?|$)/i, label: 'Devenir partenaire' },
      { match: /\/(?:connexion|mon-compte|espace-client-yombal|espace-partenaire-yombal)\/?(?:\?|$)/i, label: 'Mon compte' }
    ];

    document.querySelectorAll('.site-header .rek-menu > li').forEach(function (item) {
      var link = item.querySelector('a[href]');
      if (!link) {
        return;
      }

      var href = link.href || '';
      if (homePattern.test(href)) {
        item.classList.add('yombal-menu-item--home');
        item.setAttribute('hidden', 'hidden');
        item.style.display = 'none';
        return;
      }

      labelRules.forEach(function (rule) {
        if (rule.match.test(href)) {
          link.textContent = rule.label;
        }
      });

      if (/\/(?:connexion|mon-compte|espace-client-yombal|espace-partenaire-yombal)\/?(?:\?|$)/i.test(href)) {
        item.classList.add('yombal-menu-item--account');
      }
    });
  }

  function bindLegacySiteHeader() {
    document.querySelectorAll('.site-header').forEach(function (header) {
      if (header.querySelector('[data-yhr-site-nav]')) {
        return;
      }

      var nav = header.querySelector('.main-navigation');
      if (!nav) {
        return;
      }

      var toggle = header.querySelector('.yombal-menu-toggle');
      if (!toggle) {
        toggle = document.createElement('button');
        toggle.type = 'button';
        toggle.className = 'yombal-menu-toggle';
        toggle.setAttribute('aria-expanded', 'false');
        toggle.setAttribute('aria-label', 'Ouvrir le menu');
        toggle.innerHTML = '<span></span><span></span><span></span>';

        var logo = header.querySelector('.site-logo');
        if (logo && logo.nextSibling) {
          header.insertBefore(toggle, logo.nextSibling);
        } else {
          header.insertBefore(toggle, nav);
        }
      }

      function closeMenu() {
        header.classList.remove('is-open');
        toggle.setAttribute('aria-expanded', 'false');
      }

      toggle.addEventListener('click', function () {
        var isOpen = header.classList.toggle('is-open');
        toggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
      });

      nav.querySelectorAll('a').forEach(function (link) {
        link.addEventListener('click', closeMenu);
      });

      document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') {
          closeMenu();
        }
      });

      window.addEventListener('resize', function () {
        if (window.innerWidth > 980) {
          closeMenu();
        }
      });
    });
  }

  function removeLegacyBrandStrips() {
    document.querySelectorAll('.yombal-brand-strip').forEach(function (strip) {
      strip.remove();
    });
  }

  function schedulePrimaryNavigationRefresh() {
    refinePrimaryNavigation();
    [120, 400, 900, 1800].forEach(function (delay) {
      window.setTimeout(refinePrimaryNavigation, delay);
    });
  }

  function bindStepForms() {
    document.querySelectorAll('[data-yombal-step-form]').forEach(function (form) {
      var panes = Array.prototype.slice.call(form.querySelectorAll('[data-step-pane]'));
      var markers = Array.prototype.slice.call(form.querySelectorAll('[data-step-marker]'));
      if (!panes.length) {
        return;
      }

      function showStep(step) {
        panes.forEach(function (pane) {
          var isActive = pane.getAttribute('data-step-pane') === String(step);
          pane.hidden = !isActive;
          pane.classList.toggle('is-active', isActive);
          pane.setAttribute('aria-hidden', isActive ? 'false' : 'true');
        });

        markers.forEach(function (marker) {
          var isActive = marker.getAttribute('data-step-marker') === String(step);
          marker.classList.toggle('is-active', isActive);
          if (isActive) {
            marker.setAttribute('aria-current', 'step');
          } else {
            marker.removeAttribute('aria-current');
          }
        });
      }

      form.querySelectorAll('[data-step-next]').forEach(function (button) {
        button.addEventListener('click', function () {
          var currentStep = Number(button.getAttribute('data-step-next'));
          var currentPane = form.querySelector('[data-step-pane="' + currentStep + '"]');
          if (currentPane) {
            var fields = Array.prototype.slice.call(currentPane.querySelectorAll('input, select, textarea'));
            var firstInvalid = null;
            var invalid = fields.some(function (field) {
              if (typeof field.checkValidity !== 'function') {
                return false;
              }

              var isValid = field.checkValidity();
              if (!isValid && !firstInvalid) {
                firstInvalid = field;
              }

              return !isValid;
            });

            if (invalid) {
              if (typeof firstInvalid.reportValidity === 'function') {
                firstInvalid.reportValidity();
              }
              firstInvalid.focus();
              return;
            }
          }

          showStep(currentStep + 1);
        });
      });

      form.querySelectorAll('[data-step-prev]').forEach(function (button) {
        button.addEventListener('click', function () {
          showStep(Number(button.getAttribute('data-step-prev')) - 1);
        });
      });

      showStep(1);
    });
  }

  function decorateTailorCatalog() {
    if (!document.body.classList.contains('page-id-114')) {
      return;
    }

    var article = document.querySelector('article.page');
    var root = article ? article.querySelector('.post-content > div') : null;
    if (!root) {
      return;
    }

    addClass(root, 'y-catalog');
    addClass(root.querySelector('form'), 'y-catalog-filters');

    var count = root.querySelector('form + p');
    addClass(count, 'y-catalog-count');

    var grid = count ? count.nextElementSibling : null;
    if (!grid) {
      return;
    }

    addClasses(grid, ['y-catalog-grid', 'y-catalog-grid--tailors']);
    Array.prototype.forEach.call(grid.children, function (card) {
      if (card.tagName) {
        addClasses(card, ['y-catalog-card', 'y-catalog-card--tailor']);
      }
    });
  }

  function decorateModelsPage() {
    if (!document.body.classList.contains('page-id-806')) {
      return;
    }

    var article = document.querySelector('article.page');
    var root = article ? article.querySelector('.post-content > div') : null;
    if (!root) {
      return;
    }

    addClasses(root, ['y-models-page', 'y-catalog']);
    var children = Array.prototype.filter.call(root.children, function (el) {
      return el.tagName;
    });

    if (children[0]) {
      addClass(children[0], 'y-page-intro');
    }
    if (children[1] && children[1].tagName === 'FORM') {
      addClass(children[1], 'y-catalog-filters');
    }
    if (children[2]) {
      addClasses(children[2], ['y-catalog-grid', 'y-catalog-grid--models']);
      Array.prototype.forEach.call(children[2].children, function (card) {
        if (card.tagName) {
          addClasses(card, ['y-catalog-card', 'y-catalog-card--model']);
        }
      });
    }
  }

  ready(function () {
    decorateGlobalShell();
      rewriteLegacyLinks();
      normalizeCanonicalLabels();
      schedulePrimaryNavigationRefresh();
      bindSiteHeader();
      removeLegacyBrandStrips();
      decorateHome();
      decorateStaticPage();
      decorateTailorCatalog();
    decorateModelsPage();
    bindStepForms();
    window.setTimeout(normalizeCanonicalLabels, 150);
    window.addEventListener('load', normalizeCanonicalLabels, { once: true });
    window.addEventListener('load', schedulePrimaryNavigationRefresh, { once: true });
  });
})();
