import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './resources/views/**/*.blade.php',
        './resources/js/**/*.js',
    ],

    theme: {
        extend: {
            colors: {
                navy: {
                    50: '#f2f6fb',
                    100: '#e1eaf4',
                    200: '#c3d3e7',
                    300: '#94b0d2',
                    400: '#5f86b8',
                    500: '#3d679c',
                    600: '#2c5182',
                    700: '#22406a',
                    800: '#163256',
                    900: '#0b2a4a',
                    950: '#061a30',
                },
                brand: {
                    50: '#effcfa',
                    100: '#d0f5ef',
                    200: '#a2eadf',
                    300: '#6cd8cb',
                    400: '#3cbfb2',
                    500: '#13a397',
                    600: '#0d847b',
                    700: '#0f6a64',
                    800: '#115552',
                    900: '#124745',
                },
                pending: '#eb6834',
            },
            fontFamily: {
                sans: ['Inter', 'InterVariable', 'ui-sans-serif', 'system-ui', ...defaultTheme.fontFamily.sans],
            },
            boxShadow: {
                card: '0 1px 2px rgba(11, 42, 74, 0.04), 0 1px 3px rgba(11, 42, 74, 0.06)',
                pop: '0 10px 30px -10px rgba(11, 42, 74, 0.25)',
            },
        },
    },

    plugins: [forms],
};
