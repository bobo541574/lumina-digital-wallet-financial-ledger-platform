import type { App } from 'vue';
import { config } from '@vue/test-utils';
import { beforeEach, vi } from 'vitest';

// Fetch is not implemented in jsdom; every test controls its own responses.
beforeEach(() => {
    vi.restoreAllMocks();
    globalThis.fetch = vi.fn();
});

config.global.mocks = {};

export type VueApp = App;
