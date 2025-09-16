export default {
    content: [
        "./resources/**/*.blade.php",
        "./resources/**/*.js",
        "./vendor/filament/**/*.blade.php",

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
        },
    },
    plugins: [],
}
