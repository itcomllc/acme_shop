import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './resources/js/**/*.js',
    ],

    // bodyクラスベースのダークモード設定
    darkMode: ['class', 'body'],

    theme: {
        extend: {
            fontFamily: {
                sans: ['Figtree', ...defaultTheme.fontFamily.sans],
            },
            colors: {
                // カスタムカラーでダークモード対応
                'theme': {
                    'bg': {
                        'primary': 'rgb(var(--color-bg-primary) / <alpha-value>)',
                        'secondary': 'rgb(var(--color-bg-secondary) / <alpha-value>)',
                        'tertiary': 'rgb(var(--color-bg-tertiary) / <alpha-value>)',
                    },
                    'text': {
                        'primary': 'rgb(var(--color-text-primary) / <alpha-value>)',
                        'secondary': 'rgb(var(--color-text-secondary) / <alpha-value>)',
                        'tertiary': 'rgb(var(--color-text-tertiary) / <alpha-value>)',
                    },
                    'border': {
                        'primary': 'rgb(var(--color-border-primary) / <alpha-value>)',
                        'secondary': 'rgb(var(--color-border-secondary) / <alpha-value>)',
                    },
                    'accent': {
                        DEFAULT: 'rgb(var(--color-accent) / <alpha-value>)',
                        'hover': 'rgb(var(--color-accent-hover) / <alpha-value>)',
                    }
                }
            }
        },
    },

    plugins: [forms],
};