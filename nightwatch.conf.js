'use strict';

/**
 * Nightwatch configuration for Duccinis V3.
 *
 * Runs against the live DDEV site at https://duccinisv3.ddev.site
 * using headless Chromium (snap build on the host machine).
 *
 * Uses the npm chromedriver package (non-snap) which can exec the snap
 * Chromium binary without snap confinement restrictions.
 *
 * Usage:
 *   npm run test:nightwatch                — run all test suites
 *   npm run test:nightwatch:archive        — run archive tests only
 */

const CHROME_BINARY = '/usr/bin/google-chrome';

// Prefer the npm chromedriver (non-snap) so it can exec the snap Chromium
// binary without snap confinement restrictions. The snap chromedriver cannot
// execvp its own snap wrapper (confinement issue).
let chromedriverPath;
try {
  chromedriverPath = require('chromedriver').path;
} catch {
  chromedriverPath = 'chromedriver'; // assume it is in PATH
}

module.exports = {
  src_folders: ['tests/Nightwatch/Tests'],
  output_folder: 'tests/Nightwatch/reports',
  page_objects_path: [],
  custom_commands_path: [],
  custom_assertions_path: [],

  test_settings: {
    default: {
      launch_url: 'https://duccinisv3.ddev.site',

      webdriver: {
        start_process: true,
        server_path: chromedriverPath,
        port: 9515,
        cli_args: [],
      },

      desiredCapabilities: {
        browserName: 'chrome',
        acceptInsecureCerts: true,
        'goog:chromeOptions': {
          binary: CHROME_BINARY,
          args: [
            '--headless=new',
            '--no-sandbox',
            '--disable-gpu',
            '--disable-dev-shm-usage',
            '--window-size=1280,800',
            '--ignore-certificate-errors',
            '--disable-extensions',
          ],
        },
      },

      screenshots: {
        enabled: true,
        on_failure: true,
        path: 'tests/Nightwatch/reports/screenshots',
      },

      globals: {
        waitForConditionTimeout: 8000,
        retryAssertionTimeout: 5000,
      },
    },
  },
};
