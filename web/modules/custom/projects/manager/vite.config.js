import autoprefixer from 'autoprefixer';
import postcssUrl from 'postcss-url';

export default {
  build: {
    outDir: 'build',
    emptyOutDir: true,
    rollupOptions: {
      input: {
        'js/context-pane': './js/context-pane.js',
        'css/context-pane': './css/context-pane.css',
        'css/icons': './css/icons.css',
        'css/loader': './css/loader.css',
        'css/manager': './css/manager.css',
        'css/rule': './css/rule.css',
      },
      output: {
        assetFileNames: '[name][extname]',
        entryFileNames: '[name].js',
      },
    },
  },
  css: {
    postcss: {
      plugins: [
        autoprefixer(),
        postcssUrl({
          url: 'inline',
          encodeType: 'encodeURIComponent',
          filter: /\.svg$/,
        }),
      ],
    },
  },
};
