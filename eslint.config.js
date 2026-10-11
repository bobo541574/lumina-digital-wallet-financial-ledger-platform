import pluginVue from 'eslint-plugin-vue';
import { defineConfigWithVueTs, vueTsConfigs } from '@vue/eslint-config-typescript';
import skipFormatting from '@vue/eslint-config-prettier/skip-formatting';

export default defineConfigWithVueTs(
    {
        name: 'app/files-to-lint',
        files: ['**/*.{ts,mts,tsx,vue}'],
    },
    {
        name: 'app/files-to-ignore',
        ignores: [
            '**/node_modules/**',
            '**/vendor/**',
            '**/public/build/**',
            '**/storage/**',
            'resources/js/types/generated/**',
        ],
    },
    pluginVue.configs['flat/recommended'],
    vueTsConfigs.recommended,
    {
        name: 'app/ui-primitives',
        files: ['resources/js/components/ui/**/*.vue'],
        rules: {
            // Base design-system primitives are intentionally single-word.
            'vue/multi-word-component-names': 'off',
        },
    },
    skipFormatting,
);
