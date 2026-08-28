const buildMode = process.env.TAILWIND_BUILD_MODE;

const content = buildMode === 'frontend-sevia'
    ? [
        "./packages/frontend-sevia/resources/**/*.blade.php",
        "./packages/frontend-sevia/resources/**/*.js",
    ]
    : (buildMode === 'frontend-3piroga'
        ? [
            "./packages/frontend-3piroga/resources/**/*.blade.php",
            "./packages/frontend-3piroga/resources/**/*.js",
        ]
        : [
            "./resources/**/*.blade.php",
            "./packages/frontend-3piroga/resources/**/*.blade.php",
            "./packages/frontend-sevia/resources/**/*.blade.php",
            "./resources/**/*.js",
            "./packages/frontend-3piroga/resources/**/*.js",
            "./packages/frontend-sevia/resources/**/*.js",
            "./vendor/filament/**/*.blade.php",
            './vendor/awcodes/filament-curator/resources/**/*.blade.php',
        ]);

export default {
    content,
    theme: {
        fontFamily: {
            // теперь font-sans -> Montserrat
            sans: ["Inter Tight", "Inter", "ui-sans-serif", "system-ui", "sans-serif"],
            cormorant: ["Cormorant Garamond", "serif"],
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
