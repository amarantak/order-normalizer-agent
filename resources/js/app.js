import './bootstrap'; // configuración de axios que trae Laravel (se decide si se usa en el paso del Ajax)

import { createApp } from 'vue';
import NormalizerApp from './components/NormalizerApp.vue';

// Crea la app de Vue con el componente raíz y la monta en el <div id="app"> de la vista Blade
createApp(NormalizerApp).mount('#app');