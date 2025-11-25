import './bootstrap'; // Jetstream bootstrapping

import Alpine from 'alpinejs'; // Alpine.js for interactivity
window.Alpine = Alpine;
Alpine.start();

// CSS imports
import '@fortawesome/fontawesome-free/css/all.min.css'; // ✅ Font Awesome
import '../css/app.css'; // ✅ Tailwind or custom styles
