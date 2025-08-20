import preset from './vendor/filament/filament/tailwind.config.preset'
import forms from '@tailwindcss/forms'
import typography from '@tailwindcss/typography'

export default {
    presets: [preset],
    content: [
        './resources/views/**/*.blade.php',
        './resources/**/*.js',
        './resources/**/*.vue',
        './app/Filament/**/*.php',
        './app/Livewire/**/*.php',
        './vendor/filament/**/*.blade.php',
    ],
    theme: {
        extend: {},
    },
    plugins: [forms, typography],
}
