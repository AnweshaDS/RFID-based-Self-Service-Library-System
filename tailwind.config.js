import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['Public Sans', ...defaultTheme.fontFamily.sans],
                display: ['Fraunces', ...defaultTheme.fontFamily.serif],
            },
            colors: {
                'ink-navy': '#16233E',
                'signal-amber': '#E4A72E',
                'catalog-teal': '#1F6F78',
                paper: '#F6F7F5',
                ink: '#1A1D23',
                slate: '#5B6472',
            },
        },
    },

    plugins: [forms],
};