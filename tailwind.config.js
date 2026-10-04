import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './app/Models/**/*.php',
        './app/Support/**/*.php',
    ],
    theme: {
        extend: {
            fontFamily: { sans: ['Inter', ...defaultTheme.fontFamily.sans] },
            colors: {
                brand: {
                    50:  '#eef6ff',
                    100: '#d9ebff',
                    200: '#bcdcff',
                    400: '#5aa9ee',
                    500: '#3b9ae8',   // light blue (ONE IT)
                    600: '#1e5fbf',
                    700: '#164a99',
                    800: '#0f2d6b',   // navy (JMS)
                    900: '#0a1f4d',
                },
            },
        },
    },
    plugins: [forms],
};
