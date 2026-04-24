const fs = require('fs');
const path = require('path');
const { chromium } = require('playwright');

const baseUrl = 'https://yombal.sn';
const outputDir = path.resolve('C:/Users/Administrator/Documents/YOMBAL/output/playwright/responsive-audit-20260422');

const pages = [
  { slug: 'home', url: `${baseUrl}/`, burger: true },
  { slug: 'catalogue-tailleurs', url: `${baseUrl}/catalogue-tailleurs/`, burger: true },
  { slug: 'catalogue-tissus', url: `${baseUrl}/catalogue-tissus/`, burger: true },
  { slug: 'catalogue-modeles', url: `${baseUrl}/catalogue-modeles/`, burger: true },
  { slug: 'store-ibrahima', url: `${baseUrl}/store/ibrahima_tailleur/`, burger: true },
  { slug: 'product-kaftan', url: `${baseUrl}/produit/kaftan-homme-bapteme/`, burger: true },
  { slug: 'devenir-partenaire', url: `${baseUrl}/devenir-partenaire-yombal/`, burger: true },
  { slug: 'connexion', url: `${baseUrl}/connexion/`, burger: true },
  { slug: 'messages', url: `${baseUrl}/messages-yombal/`, burger: true },
  { slug: 'litiges', url: `${baseUrl}/litiges-yombal/`, burger: true },
  { slug: 'espace-client', url: `${baseUrl}/espace-client-yombal/`, burger: true },
  { slug: 'espace-partenaire', url: `${baseUrl}/espace-partenaire-yombal/`, burger: true },
  { slug: 'contact', url: `${baseUrl}/contact/`, burger: true },
  { slug: 'mentions-legales', url: `${baseUrl}/mentions-legales/`, burger: true },
];

async function ensureDir(dir) {
  await fs.promises.mkdir(dir, { recursive: true });
}

async function detect(page) {
  return page.evaluate(() => {
    const html = document.documentElement;
    const body = document.body;
    const burger = document.querySelector('.yombal-menu-toggle, .yhr-site-header__toggle');
    const header = document.querySelector('.site-header, [data-yhr-site-header]');
    const visibleBurger = !!burger && !!(burger.offsetWidth || burger.offsetHeight || burger.getClientRects().length);

    return {
      title: document.title,
      innerWidth: window.innerWidth,
      innerHeight: window.innerHeight,
      scrollWidth: html.scrollWidth,
      bodyScrollWidth: body ? body.scrollWidth : 0,
      hasHorizontalOverflow: html.scrollWidth > window.innerWidth + 2 || (body && body.scrollWidth > window.innerWidth + 2),
      burgerVisible: visibleBurger,
      headerHeight: header ? Math.round(header.getBoundingClientRect().height) : 0,
    };
  });
}

async function clickBurgerIfPresent(page) {
  const burger = page.locator('.yombal-menu-toggle, .yhr-site-header__toggle').first();
  if (await burger.count()) {
    await burger.click();
    await page.waitForTimeout(300);
    return true;
  }
  return false;
}

async function auditPage(browser, entry) {
  const context = await browser.newContext({
    viewport: { width: 390, height: 844 },
    deviceScaleFactor: 2,
    isMobile: true,
    hasTouch: true,
  });
  const page = await context.newPage();
  page.setDefaultTimeout(30000);
  await page.goto(entry.url, { waitUntil: 'networkidle' });

  const before = await detect(page);
  const beforePath = path.join(outputDir, `${entry.slug}-mobile.png`);
  await page.screenshot({ path: beforePath, fullPage: true });

  let burgerOpened = false;
  let afterBurger = null;
  let burgerPath = null;
  if (entry.burger) {
    burgerOpened = await clickBurgerIfPresent(page);
    if (burgerOpened) {
      afterBurger = await detect(page);
      burgerPath = path.join(outputDir, `${entry.slug}-mobile-burger.png`);
      await page.screenshot({ path: burgerPath, fullPage: true });
    }
  }

  await context.close();

  return {
    page: entry.slug,
    url: entry.url,
    before,
    burgerOpened,
    afterBurger,
    screenshots: {
      page: beforePath,
      burger: burgerPath,
    },
  };
}

async function main() {
  await ensureDir(outputDir);
  const browser = await chromium.launch({ headless: true, channel: 'chrome' });
  const results = [];

  try {
    for (const entry of pages) {
      results.push(await auditPage(browser, entry));
    }
  } finally {
    await browser.close();
  }

  const reportPath = path.join(outputDir, 'report.json');
  await fs.promises.writeFile(reportPath, JSON.stringify(results, null, 2), 'utf8');

  for (const item of results) {
    const overflow = item.before.hasHorizontalOverflow ? 'OVERFLOW' : 'OK';
    const burger = item.burgerOpened ? 'BURGER' : 'NO_BURGER';
    console.log(`${item.page} :: ${overflow} :: ${burger} :: width=${item.before.innerWidth} scroll=${item.before.scrollWidth}`);
  }

  console.log(`REPORT ${reportPath}`);
}

main().catch((error) => {
  console.error(error);
  process.exit(1);
});
