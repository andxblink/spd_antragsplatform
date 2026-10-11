#!/usr/bin/env node

import fs from "node:fs/promises";
import { createRequire } from "node:module";
import path from "node:path";
import process from "node:process";

const root = path.resolve(new URL("..", import.meta.url).pathname);
const matrixPath = path.join(root, "scripts", "product-acceptance-matrix.json");
const baseUrl = process.env.BROWSER_TEST_BASE_URL?.replace(/\/$/, "");
const storageState = process.env.BROWSER_TEST_STORAGE_STATE;
const executablePath = process.env.BROWSER_TEST_EXECUTABLE_PATH;

if (!baseUrl) {
  console.error("BROWSER_TEST_BASE_URL is required for the browser matrix");
  process.exit(2);
}

let playwright;
try {
  playwright = createRequire(import.meta.url)("playwright");
} catch (error) {
  console.error("Playwright is required for the browser matrix");
  console.error(error instanceof Error ? error.message : String(error));
  process.exit(2);
}

const matrix = JSON.parse(await fs.readFile(matrixPath, "utf8"));
const routes = matrix.routes ?? [];
const viewports = matrix.viewports ?? [];
const authKinds = new Set(routes.map((route) => route.auth).filter((auth) => auth !== "anonymous"));

if (authKinds.size > 0 && !storageState) {
  console.error(
    `BROWSER_TEST_STORAGE_STATE is required for authenticated routes: ${[...authKinds].join(
      ", ",
    )}`,
  );
  process.exit(2);
}

const browser = await playwright.chromium.launch({
  headless: true,
  ...(executablePath ? { executablePath } : {}),
});
const context = await browser.newContext(storageState ? { storageState } : {});
const failures = [];
const results = [];

try {
  for (const viewport of viewports) {
    for (const route of routes) {
      const page = await context.newPage();
      await page.setViewportSize({ width: viewport.width, height: viewport.height });
      const url = `${baseUrl}${route.path}`;
      let response;

      try {
        response = await page.goto(url, { waitUntil: "domcontentloaded" });
        const result = await page.evaluate(() => {
          const ids = [...document.querySelectorAll("[id]")]
            .map((element) => element.id)
            .filter(Boolean);
          const interactive = [...document.querySelectorAll("a,button,input,select,textarea")];
          const unlabeled = interactive.filter((element) => {
            const style = getComputedStyle(element);
            const rect = element.getBoundingClientRect();
            if (style.display === "none" || style.visibility === "hidden" ||
              (rect.width === 0 && rect.height === 0)) {
              return false;
            }
            if (element.matches('input[type="hidden"], [hidden], [aria-hidden="true"]')) {
              return false;
            }
            if (element.matches("input,select,textarea")) {
              const hasAriaLabel = element.getAttribute("aria-label") ||
                element.getAttribute("aria-labelledby");
              const hasNativeLabel = element.labels?.length > 0;
              const hasTitle = element.getAttribute("title");
              return !hasAriaLabel && !hasNativeLabel && !hasTitle;
            }
            return !element.getAttribute("aria-label") &&
              !element.getAttribute("aria-labelledby") &&
              !element.getAttribute("title") &&
              !(element.textContent ?? "").trim();
          });
          return {
            overflow: document.documentElement.scrollWidth > document.documentElement.clientWidth,
            ids: { count: ids.length, unique: new Set(ids).size },
            unlabeledInteractive: unlabeled.length,
            landmarks: document.querySelectorAll("main,nav,header,footer").length,
          };
        });
        const status = response?.status() ?? 0;
        const passed = status >= 200 && status < 400 && !result.overflow &&
          result.ids.count === result.ids.unique && result.unlabeledInteractive === 0;
        const row = {
          viewport: viewport.name,
          path: route.path,
          productionPath: route.productionPath ?? null,
          risk: route.risk ?? null,
          status,
          ...result,
          passed,
        };
        results.push(row);
        if (!passed) failures.push(row);
      } catch (error) {
        const row = {
          viewport: viewport.name,
          path: route.path,
          productionPath: route.productionPath ?? null,
          risk: route.risk ?? null,
          error: error instanceof Error ? error.message : String(error),
          passed: false,
        };
        results.push(row);
        failures.push(row);
      } finally {
        await page.close();
      }
    }
  }
} finally {
  await context.close();
  await browser.close();
}

console.log(JSON.stringify({ baseUrl, viewports, routes: routes.length, results }, null, 2));
if (failures.length > 0) {
  console.error(`Browser matrix failed for ${failures.length} case(s)`);
  process.exit(2);
}
console.log("browser matrix: passed");
