import autoprefixer from 'autoprefixer'

export default {
  build: {
    outDir: 'css',
    emptyOutDir: true,
    rollupOptions: {
      input: {
        style: './sass/style.scss'
      },
      output: {
        assetFileNames: '[name][extname]'
      }
    }
  },
  css: {
    preprocessorOptions: {
      scss: {
        api: 'modern-compiler',
        additionalData: '' +
          '@use "abstracts/variables.scss" as *;' +
          '@use "abstracts/functions.scss" as *;' +
          '@use "abstracts/mixins.scss" as *;'
      }
    }
  },
  postcss: {
    plugins: [
      autoprefixer()
    ]
  }
};
