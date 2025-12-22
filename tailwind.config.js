export default {
    content: [
        "./resources/**/*.blade.php",
        "./resources/**/*.js",
        "./vendor/filament/**/*.blade.php",
        './vendor/awcodes/filament-curator/resources/**/*.blade.php',

    ],
    theme: {
        fontFamily: {
            // теперь font-sans -> Montserrat
            sans: ["Intro", "ui-sans-serif", "system-ui", "sans-serif"],
        },
        extend: {
            fontSize: {
                body: ["13px", { lineHeight: "16px" }],
            },
            screens: {
                tablet: '768px',     // алиас, если удобнее
                desktop: '1344px',
            },
        },
        screens: {
            sm: '640px',
            md: '768px',         // планшет по макету
            lg: '1024px',
            xl: '1280px',
            'desk': '1344px',    // точный десктоп из Figma
            '2xl': '1536px',
        },
    },
    plugins: [require('@tailwindcss/typography')],
}
