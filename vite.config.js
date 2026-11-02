import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue';
import path from 'node:path';

export default defineConfig({
	plugins: [
		laravel({
			input: ['resources/js/app.js', 'resources/css/app.css'],
			refresh: true
		}),
		vue()
	],
	resolve: {
		alias: {
			'@': path.resolve(__dirname, 'resources/js')
		}
	},
	server: {
		host: '127.0.0.1',
		port: 5174,
		hmr: { host: '127.0.0.1' }
	},
	build: {
		outDir: 'public/build'
	}
});
