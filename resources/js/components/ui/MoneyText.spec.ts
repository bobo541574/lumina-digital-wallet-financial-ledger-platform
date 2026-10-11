import { describe, expect, it } from 'vitest';
import { mount } from '@vue/test-utils';
import MoneyText from '@/components/ui/MoneyText.vue';

describe('MoneyText', () => {
    it('formats minor units as currency', () => {
        const wrapper = mount(MoneyText, { props: { amount: 123456, currency: 'USD' } });
        expect(wrapper.text()).toBe('$1,234.56');
    });

    it('renders negative amounts with a minus sign', () => {
        const wrapper = mount(MoneyText, { props: { amount: -500, currency: 'USD' } });
        expect(wrapper.text()).toBe('−$5.00');
    });

    it('applies the destructive colour class to negative amounts when coloured', () => {
        const wrapper = mount(MoneyText, {
            props: { amount: -500, currency: 'USD', coloured: true },
        });
        expect(wrapper.classes()).toContain('text-destructive');
    });

    it('adds an explicit sign when signed is set', () => {
        const positive = mount(MoneyText, {
            props: { amount: 500, currency: 'USD', signed: true },
        });
        expect(positive.text()).toBe('+$5.00');
    });
});
