import defaultTheme from 'tailwindcss/defaultTheme';

/** @type {import('tailwindcss').Config} */
export default {
    // Usa la preferencia del usuario: 'media' o cambia a 'class' si prefieres un toggle manual
    darkMode: 'media',

    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/**/*.blade.php',
        './resources/**/*.js',
        './resources/**/*.vue',
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['Figtree', ...defaultTheme.fontFamily.sans],
            },
            // Aquí puedes extender más tu paleta para modo oscuro, por ejemplo:
            colors: {
                primary: {
                    light: '#B00020',
                    dark:  '#FAA0A0',
                },
            },
        },
    },

    plugins: [],
};
